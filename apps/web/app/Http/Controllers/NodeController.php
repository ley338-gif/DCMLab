<?php

namespace App\Http\Controllers;

use App\Content\ContentRepository;
use App\Content\MarkdownRenderer;
use App\Content\NodeSections;
use App\Models\Lesson;
use App\Models\Node;
use App\Models\NodeAttempt;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\EngineClientContract;
use App\Services\EngineClientResolver;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class NodeController extends Controller
{
    /**
     * Oeffentlicher Katalog aller Nodes (Abschnitt 6) -- wie Tracks/Index
     * ohne Login sichtbar, ein Klick auf eine Node fuehrt Gaeste zum Login.
     * `status` (draft|review|published) ist bei Nodes anders als bei Track
     * bislang reine Redaktionsmarkierung, kein Zugriffsfilter -- show()
     * selbst prueft nur, ob Content existiert, nicht den Status. Deshalb
     * werden hier ebenfalls alle Nodes gelistet, nicht nur "published".
     */
    public function index(): Response
    {
        $solvedNodeIds = Auth::check()
            ? NodeAttempt::query()
                ->where('user_id', Auth::id())
                ->where('status', 'solved')
                ->pluck('node_id')
                ->all()
            : [];

        // "begonnen" (Abschnitt 5): jeder Attempt, der noch nicht geloest ist
        // -- ein Attempt entsteht bereits beim reinen Aufrufen der Node
        // (attemptFor()), das Feld ist also ein echter Besuchs-Indikator.
        $startedNodeIds = Auth::check()
            ? NodeAttempt::query()
                ->where('user_id', Auth::id())
                ->where('status', 'started')
                ->pluck('node_id')
                ->all()
            : [];

        $relatedLessonsByNodeId = $this->relatedLessonForNodes(Node::query()->get());

        $nodes = $this->orderedNodes()
            ->map(fn (Node $node) => [
                'slug' => $node->slug,
                'title' => $node->title['de'] ?? $node->slug,
                'difficulty' => $node->difficulty,
                'points' => $node->points,
                'category' => $node->category,
                'themenfeld' => $this->themenfeldSlug($node),
                'estimated_minutes' => $node->estimated_minutes,
                'solved' => in_array($node->id, $solvedNodeIds, true),
                'status' => match (true) {
                    in_array($node->id, $solvedNodeIds, true) => 'abgeschlossen',
                    in_array($node->id, $startedNodeIds, true) => 'begonnen',
                    default => 'offen',
                },
                'related_lesson' => $relatedLessonsByNodeId[$node->id] ?? null,
            ])
            ->values();

        return Inertia::render('Nodes/Index', [
            'nodes' => $nodes,
        ]);
    }

    public function show(Node $node, ContentRepository $content, EngineClientResolver $engineResolver): Response
    {
        $engine = $engineResolver->for($node);
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

        $order = $this->orderedNodes()->values();
        $position = $order->search(fn (Node $candidate) => $candidate->id === $node->id);
        $previousNode = $position !== false && $position > 0 ? $order->get($position - 1) : null;
        $nextNode = $position !== false ? $order->get($position + 1) : null;

        return Inertia::render('Nodes/Show', [
            'node' => [
                'slug' => $node->slug,
                'title' => $node->title['de'] ?? $node->slug,
                'scenario_title' => $node->scenario_title['de'] ?? '',
                'difficulty' => $node->difficulty,
                'points' => $node->points,
                'category' => $node->category,
                'interaction' => $node->interaction,
                'estimated_minutes' => $node->estimated_minutes,
            ],
            'prev' => $previousNode !== null ? [
                'slug' => $previousNode->slug,
                'title' => $previousNode->title['de'] ?? $previousNode->slug,
            ] : null,
            'next' => $nextNode !== null ? [
                'slug' => $nextNode->slug,
                'title' => $nextNode->title['de'] ?? $nextNode->slug,
            ] : null,
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

    public function state(Node $node, EngineClientResolver $engineResolver): JsonResponse
    {
        $engine = $engineResolver->for($node);
        $attempt = $this->attemptFor($node, $engine);

        return response()->json($engine->state($attempt->engine_session_id));
    }

    public function exec(Request $request, Node $node, EngineClientResolver $engineResolver): JsonResponse
    {
        $engine = $engineResolver->for($node);
        $data = $request->validate(['host' => 'required|string', 'command' => 'required|string']);
        $attempt = $this->attemptFor($node, $engine);

        return response()->json($engine->exec($attempt->engine_session_id, $data['host'], $data['command']));
    }

    public function setConfig(Request $request, Node $node, EngineClientResolver $engineResolver): JsonResponse
    {
        $engine = $engineResolver->for($node);
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

    public function triggerAction(Request $request, Node $node, EngineClientResolver $engineResolver): JsonResponse
    {
        $engine = $engineResolver->for($node);
        $data = $request->validate(['host' => 'required|string', 'action' => 'required|string']);
        $attempt = $this->attemptFor($node, $engine);

        return response()->json(
            $engine->triggerAction($attempt->engine_session_id, $data['host'], $data['action']),
        );
    }

    public function useHint(Request $request, Node $node, ContentRepository $content, EngineClientResolver $engineResolver): JsonResponse
    {
        $engine = $engineResolver->for($node);
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

    public function viewWriteUp(Node $node, ContentRepository $content, EngineClientResolver $engineResolver): JsonResponse
    {
        $engine = $engineResolver->for($node);
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

    public function submitFlag(
        Request $request,
        Node $node,
        ContentRepository $content,
        EngineClientResolver $engineResolver,
        ProfileService $profiles,
        AchievementService $achievements,
    ): JsonResponse {
        $engine = $engineResolver->for($node);
        $data = $request->validate(['value' => 'required|string']);
        $attempt = $this->attemptFor($node, $engine);

        $result = $engine->submitFlag($attempt->engine_session_id, $data['value']);

        if ($result['correct']) {
            $attempt->status = 'solved';
            $attempt->flag_submitted_at = now();
        }

        $this->syncAttempt($attempt, $engine, save: true);

        $unlockedAchievements = [];

        if ($result['correct']) {
            $user = $request->user();
            $profiles->recomputeAfterSolve($user, $node);
            $unlockedAchievements = $this->unlockNodeAchievements($user, $node, $content, $achievements);
        }

        return response()->json([...$result, 'unlocked_achievements' => $unlockedAchievements]);
    }

    /**
     * Achievement-System (Auftrag Abschnitt 6/7): "first-blood" ist die
     * persoenliche erste geloeste Node ueberhaupt, unabhaengig davon, wer
     * sie zuerst geloest hat (nicht zu verwechseln mit dem aelteren,
     * global-pro-Node "first_blood"-Achievement aus ProfileService). Die
     * uebrigen DICOM-Achievements sind an bestimmte Nodes gekoppelt, weil
     * die simulierte Engine kein separates "C-ECHO erfolgreich"-Ereignis
     * kennt -- geloest = korrekte Flag, siehe docs/achievements.md.
     *
     * @return list<array<string, mixed>>
     */
    private function unlockNodeAchievements(User $user, Node $node, ContentRepository $content, AchievementService $achievements): array
    {
        $solvedCount = NodeAttempt::query()
            ->where('user_id', $user->id)
            ->where('status', 'solved')
            ->count();

        $slugs = $solvedCount === 1 ? ['first-blood'] : [];

        $nodeContent = $content->nodes()[$node->slug] ?? null;
        foreach (data_get($nodeContent, 'def.achievements', []) as $slug) {
            $slugs[] = (string) $slug;
        }

        $unlocked = [];

        foreach (array_unique($slugs) as $slug) {
            $unlockResult = $achievements->unlock($user, $slug, ['node' => $node->slug, 'source' => 'node_completed']);

            if ($unlockResult->isNewlyUnlocked() && $unlockResult->definition !== null) {
                $unlocked[] = $achievements->toArray($unlockResult->definition, $unlockResult->unlock);
            }
        }

        return $unlocked;
    }

    /**
     * Katalog-Reihenfolge (Abschnitt 5+6+13): Themenfeld-Reihenfolge (aus
     * themenfelder.yml, nicht alphabetisch nach Slug -- sonst stuende
     * "datenschutz" vor "dicom"), dann Kategorie, dann Schwierigkeit, dann
     * Slug -- dieselbe Sortierung fuer index() und die Prev/Next-Navigation
     * in show(), damit beide konsistent bleiben.
     *
     * @return Collection<int, Node>
     */
    private function orderedNodes(): Collection
    {
        $difficultyRank = ['easy' => 0, 'medium' => 1, 'hard' => 2, 'insane' => 3];

        return Node::query()
            ->with('themenfeld')
            ->get()
            ->sortBy(fn (Node $node) => sprintf(
                '%02d-%s-%s-%d-%s',
                $this->themenfeldOrder($node),
                $this->themenfeldSlug($node),
                $node->category,
                $difficultyRank[$node->difficulty] ?? 99,
                $node->slug,
            ))
            ->values();
    }

    /**
     * Themenfeld-Slug einer Node, mit "dicom" als Fallback (Abschnitt 13):
     * `themenfeld_id` ist nullable (siehe Migration), praktisch aber immer
     * gesetzt, sobald content:sync gelaufen ist -- der Fallback greift nur
     * in der Luecke zwischen migrate und content:sync sowie bei ueber
     * Node::factory() erzeugten Test-Nodes ohne themenfeld_id.
     */
    private function themenfeldSlug(Node $node): string
    {
        return $node->themenfeld_id === null ? 'dicom' : $node->themenfeld->slug;
    }

    /**
     * @see self::themenfeldSlug() -- 1 ist dicoms tatsaechlicher Wert in
     * themenfelder.yml, deshalb derselbe Fallback wie dort.
     */
    private function themenfeldOrder(Node $node): int
    {
        return $node->themenfeld_id === null ? 1 : $node->themenfeld->order;
    }

    /**
     * "Passende Lektion" (Abschnitt 5): nutzt die echte, aus dem Content
     * befuellte Node->related_lessons-Relation, keine erfundene Verknuepfung.
     *
     * @param  Collection<int, Node>  $nodes
     * @return array<int, array{lesson_id: string, title: string}>
     */
    private function relatedLessonForNodes(Collection $nodes): array
    {
        $lessonIds = $nodes->pluck('related_lessons')->flatten()->unique()->values()->all();

        $lessonsById = Lesson::query()
            ->whereIn('lesson_id', $lessonIds)
            ->get()
            ->keyBy('lesson_id');

        $result = [];

        foreach ($nodes as $node) {
            $firstRelatedId = $node->related_lessons[0] ?? null;
            $lesson = $firstRelatedId !== null ? $lessonsById->get($firstRelatedId) : null;

            if ($lesson !== null) {
                $result[$node->id] = [
                    'lesson_id' => $lesson->lesson_id,
                    'title' => $lesson->title['de'] ?? $lesson->lesson_id,
                ];
            }
        }

        return $result;
    }

    private function attemptFor(Node $node, EngineClientContract $engine): NodeAttempt
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

    private function syncAttempt(NodeAttempt $attempt, EngineClientContract $engine, bool $save = true): void
    {
        $state = $engine->state($attempt->engine_session_id);
        $attempt->hints_used = $state['hints_used'];
        $attempt->points = $state['points'];

        if ($save) {
            $attempt->save();
        }
    }
}
