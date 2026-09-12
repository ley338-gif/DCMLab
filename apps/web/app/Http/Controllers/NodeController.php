<?php

namespace App\Http\Controllers;

use App\Content\ContentRepository;
use App\Content\MarkdownRenderer;
use App\Content\NodeSections;
use App\Models\Node;
use App\Models\NodeAttempt;
use App\Services\EngineClient;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class NodeController extends Controller
{
    public function show(Node $node, ContentRepository $content, EngineClient $engine): Response
    {
        $nodeContent = $content->nodes()[$node->slug] ?? null;
        abort_unless($nodeContent !== null && $nodeContent['def'] !== null, 404);

        $def = $nodeContent['def'];
        $sections = NodeSections::parse($nodeContent['body'] ?? '');
        $renderer = new MarkdownRenderer($content->glossary());

        $attempt = $this->attemptFor($node, $engine);
        $engineState = $engine->state($attempt->engine_session_id);

        $hintsUsed = $engineState['hints_used'];

        /** @var array<int, array<string, mixed>> $hintDefinitions */
        $hintDefinitions = $def['hints'] ?? [];
        $hints = collect($hintDefinitions)->map(fn (array $hint) => [
            'id' => $hint['id'],
            'cost' => $hint['cost'],
            'used' => in_array($hint['id'], $hintsUsed, true),
            'text_html' => in_array($hint['id'], $hintsUsed, true)
                ? $renderer->render($sections['hints'][$hint['id']] ?? '')
                : null,
        ])->values();

        return Inertia::render('Nodes/Show', [
            'node' => [
                'slug' => $node->slug,
                'title' => $node->title['de'] ?? $node->slug,
                'scenario_title' => $node->scenario_title['de'] ?? '',
                'difficulty' => $node->difficulty,
                'points' => $node->points,
                'estimated_minutes' => $node->estimated_minutes,
            ],
            'briefing_html' => $renderer->render($sections['briefing']),
            'hints' => $hints,
            // Nach dem Loesen wird das Write-up automatisch gezeigt, ganz
            // ohne die "kostet Punkte"-Warnung (Abschnitt 5.3: "vorab").
            'write_up_html' => ($engineState['write_up_seen'] || $engineState['solved'])
                ? $renderer->render($sections['write_up'])
                : null,
            'templates' => $def['environment']['templates'] ?? [],
            'placeholders' => $def['environment']['placeholders'] ?? [],
            'state' => $engineState,
            'attempt' => [
                'status' => $attempt->status,
            ],
        ]);
    }

    public function state(Node $node, EngineClient $engine): JsonResponse
    {
        $attempt = $this->attemptFor($node, $engine);

        return response()->json($engine->state($attempt->engine_session_id));
    }

    public function exec(Request $request, Node $node, EngineClient $engine): JsonResponse
    {
        $data = $request->validate(['host' => 'required|string', 'command' => 'required|string']);
        $attempt = $this->attemptFor($node, $engine);

        return response()->json($engine->exec($attempt->engine_session_id, $data['host'], $data['command']));
    }

    public function setConfig(Request $request, Node $node, EngineClient $engine): JsonResponse
    {
        $data = $request->validate([
            'host' => 'required|string',
            'field' => 'required|string',
            'value' => 'required|string',
        ]);
        $attempt = $this->attemptFor($node, $engine);

        return response()->json(
            $engine->setConfig($attempt->engine_session_id, $data['host'], $data['field'], $data['value']),
        );
    }

    public function triggerAction(Request $request, Node $node, EngineClient $engine): JsonResponse
    {
        $data = $request->validate(['host' => 'required|string', 'action' => 'required|string']);
        $attempt = $this->attemptFor($node, $engine);

        return response()->json(
            $engine->triggerAction($attempt->engine_session_id, $data['host'], $data['action']),
        );
    }

    public function useHint(Request $request, Node $node, ContentRepository $content, EngineClient $engine): JsonResponse
    {
        $data = $request->validate(['hint_id' => 'required|string']);
        $attempt = $this->attemptFor($node, $engine);

        $result = $engine->useHint($attempt->engine_session_id, $data['hint_id']);
        $this->syncAttempt($attempt, $engine);

        $nodeContent = $content->nodes()[$node->slug] ?? null;
        $sections = NodeSections::parse($nodeContent['body'] ?? '');
        $renderer = new MarkdownRenderer($content->glossary());

        return response()->json([
            ...$result,
            'text_html' => isset($result['error']) ? null : $renderer->render($sections['hints'][$data['hint_id']] ?? ''),
        ]);
    }

    public function viewWriteUp(Node $node, ContentRepository $content, EngineClient $engine): JsonResponse
    {
        $attempt = $this->attemptFor($node, $engine);
        $result = $engine->viewWriteUp($attempt->engine_session_id);
        $this->syncAttempt($attempt, $engine);

        $nodeContent = $content->nodes()[$node->slug] ?? null;
        $sections = NodeSections::parse($nodeContent['body'] ?? '');
        $renderer = new MarkdownRenderer($content->glossary());

        return response()->json([
            ...$result,
            'write_up_html' => $renderer->render($sections['write_up']),
        ]);
    }

    public function submitFlag(Request $request, Node $node, EngineClient $engine, ProfileService $profiles): JsonResponse
    {
        $data = $request->validate(['value' => 'required|string']);
        $attempt = $this->attemptFor($node, $engine);

        $result = $engine->submitFlag($attempt->engine_session_id, $data['value']);

        if ($result['correct']) {
            $attempt->status = 'solved';
            $attempt->flag_submitted_at = now();
        }

        $this->syncAttempt($attempt, $engine, save: true);

        if ($result['correct']) {
            $profiles->recomputeAfterSolve($request->user(), $node);
        }

        return response()->json($result);
    }

    private function attemptFor(Node $node, EngineClient $engine): NodeAttempt
    {
        $attempt = NodeAttempt::firstOrNew(['user_id' => Auth::id(), 'node_id' => $node->id]);

        if (! $attempt->exists) {
            $session = $engine->createSession($node->slug);
            $attempt->engine_session_id = $session['session_id'];
            $attempt->status = 'started';
            $attempt->started_at = now();
            $attempt->save();
        }

        return $attempt;
    }

    private function syncAttempt(NodeAttempt $attempt, EngineClient $engine, bool $save = true): void
    {
        $state = $engine->state($attempt->engine_session_id);
        $attempt->hints_used = $state['hints_used'];
        $attempt->points = $state['points'];

        if ($save) {
            $attempt->save();
        }
    }
}
