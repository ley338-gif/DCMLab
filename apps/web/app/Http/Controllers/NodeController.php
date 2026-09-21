<?php

namespace App\Http\Controllers;

use App\Activities\ActivityProgressRecorder;
use App\Content\ContentRepository;
use App\Content\MarkdownRenderer;
use App\Content\NodeSections;
use App\Content\RichContent\RichContentRenderer;
use App\Models\Lesson;
use App\Models\Node;
use App\Models\NodeAttempt;
use App\Models\NodePreviewSession;
use App\Services\EngineClientContract;
use App\Services\EngineClientResolver;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class NodeController extends Controller
{
    /**
     * Oeffentlicher Katalog aller veroeffentlichten Nodes (Abschnitt 6) --
     * wie Tracks/Index ohne Login sichtbar, ein Klick auf eine Node fuehrt
     * Gaeste zum Login. Seit ADR 0110 (CMS-6d Haertung) zeigt der Katalog
     * nur `status: published` -- ein per Studio angelegter Entwurf (ADR
     * 0109) ist damit erst nach echter Freigabe fuer Lernende sichtbar, ein
     * Autor sieht ihn vorher ueber "Vorschau" (siehe show()).
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
        $this->authorizeAccess($node);

        $engine = $engineResolver->for($node);
        $nodeContent = $content->nodes()[$node->slug] ?? null;

        // ADR 0107 (CMS-6d): body/hints bevorzugt aus der DB (von
        // content:sync befuellt) -- ContentRepository bleibt nur noch
        // Fallback fuer eine Node, deren naechster Sync-Lauf noch aussteht
        // (derselbe Fallback-Mechanismus wie bei Lesson, ADR 0101).
        // `environment` (Runtime-Konfiguration: Templates/Platzhalter fuer
        // die Engine) bleibt bewusst datei-gefuehrt -- kein Autorenfeld,
        // siehe Node-Klassendoc.
        $body = $node->body ?? $nodeContent['body'] ?? null;
        /** @var array<int, array<string, mixed>> $hintDefinitions */
        $hintDefinitions = $node->hints ?? $nodeContent['def']['hints'] ?? [];
        $environment = $nodeContent['def']['environment'] ?? [];

        // CMS-7d.4 (Betreiber-Review): eine reine Rich-Content-Ressource
        // (rich_content gesetzt, body zufaellig null) ist genauso gueltig
        // -- der Existenzcheck darf sich nicht mehr allein auf die
        // (praktisch immer wahre, aber nicht erzwungene) Konvention
        // verlassen, dass `body` nie null ist.
        abort_unless($node->rich_content !== null || $body !== null, 404);

        $sections = NodeSections::parse($this->bodyFor($node, $content));

        $attempt = $this->attemptFor($node, $engine);
        $engineState = $engine->state($attempt->engine_session_id);

        $hintsUsed = $engineState['hints_used'];

        $hints = collect($hintDefinitions)->map(fn (array $hint) => [
            'id' => $hint['id'],
            'cost' => $hint['cost'],
            'used' => in_array($hint['id'], $hintsUsed, true),
            'text_html' => in_array($hint['id'], $hintsUsed, true)
                ? $this->renderNodeSection($node, $content, $sections['hints'][$hint['id']] ?? '', 'hints', $hint['id'])
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
            'briefing_html' => $this->renderNodeSection($node, $content, $sections['briefing'], 'briefing'),
            'hints' => $hints,
            // Nach dem Loesen wird das Write-up automatisch gezeigt, ganz
            // ohne die "kostet Punkte"-Warnung (Abschnitt 5.3: "vorab").
            'write_up_html' => ($engineState['write_up_seen'] || $engineState['solved'])
                ? $this->renderNodeSection($node, $content, $sections['write_up'], 'write_up')
                : null,
            'templates' => $environment['templates'] ?? [],
            'placeholders' => $environment['placeholders'] ?? [],
            'state' => $engineState,
            'attempt' => [
                'status' => $attempt->status,
            ],
            // Serverseitig ermittelt, nie clientseitig ableitbar (Abschnitt
            // 5): jede Session auf einer nicht veroeffentlichten Node ist
            // per Konstruktion eine autorisierte Vorschau (siehe
            // NodePolicy::view()/attemptFor()) -- Nodes/Show.vue zeigt dafuer
            // ein Banner, aendert aber sonst nichts an der echten,
            // interaktiven Lernendenansicht.
            'draft_preview' => ! $node->isPublished(),
        ]);
    }

    public function state(Node $node, EngineClientResolver $engineResolver): JsonResponse
    {
        $this->authorizeAccess($node);
        $engine = $engineResolver->for($node);
        $attempt = $this->attemptFor($node, $engine);

        return response()->json($engine->state($attempt->engine_session_id));
    }

    public function exec(Request $request, Node $node, EngineClientResolver $engineResolver): JsonResponse
    {
        $this->authorizeAccess($node);
        $engine = $engineResolver->for($node);
        $data = $request->validate(['host' => 'required|string', 'command' => 'required|string']);
        $attempt = $this->attemptFor($node, $engine);

        return response()->json($engine->exec($attempt->engine_session_id, $data['host'], $data['command']));
    }

    public function setConfig(Request $request, Node $node, EngineClientResolver $engineResolver): JsonResponse
    {
        $this->authorizeAccess($node);
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
        $this->authorizeAccess($node);
        $engine = $engineResolver->for($node);
        $data = $request->validate(['host' => 'required|string', 'action' => 'required|string']);
        $attempt = $this->attemptFor($node, $engine);

        return response()->json(
            $engine->triggerAction($attempt->engine_session_id, $data['host'], $data['action']),
        );
    }

    public function useHint(Request $request, Node $node, ContentRepository $content, EngineClientResolver $engineResolver): JsonResponse
    {
        $this->authorizeAccess($node);
        $engine = $engineResolver->for($node);
        $data = $request->validate(['hint_id' => 'required|string']);
        $attempt = $this->attemptFor($node, $engine);

        $result = $engine->useHint($attempt->engine_session_id, $data['hint_id']);
        $this->syncAttempt($attempt, $engine);

        $sections = NodeSections::parse($this->bodyFor($node, $content));

        return response()->json([
            ...$result,
            'text_html' => isset($result['error']) ? null : $this->renderNodeSection($node, $content, $sections['hints'][$data['hint_id']] ?? '', 'hints', $data['hint_id']),
        ]);
    }

    public function viewWriteUp(Node $node, ContentRepository $content, EngineClientResolver $engineResolver): JsonResponse
    {
        $this->authorizeAccess($node);
        $engine = $engineResolver->for($node);
        $attempt = $this->attemptFor($node, $engine);
        $result = $engine->viewWriteUp($attempt->engine_session_id);
        $this->syncAttempt($attempt, $engine);

        $sections = NodeSections::parse($this->bodyFor($node, $content));

        return response()->json([
            ...$result,
            'write_up_html' => $this->renderNodeSection($node, $content, $sections['write_up'], 'write_up'),
        ]);
    }

    public function submitFlag(
        Request $request,
        Node $node,
        EngineClientResolver $engineResolver,
        ProfileService $profiles,
        ActivityProgressRecorder $progressRecorder,
    ): JsonResponse {
        $this->authorizeAccess($node);
        $engine = $engineResolver->for($node);
        $data = $request->validate(['value' => 'required|string']);
        $attempt = $this->attemptFor($node, $engine);

        $result = $engine->submitFlag($attempt->engine_session_id, $data['value']);

        // Phase D.2: `correct` heisst seit dem Terminal-Engine-Umbau nur noch
        // "Flag-Wert stimmt", nicht mehr gleichzeitig "Node geloest" (siehe
        // `evaluate_flag()`, services/engine/app/rules.py) -- ein korrekter
        // Flag bei nicht erfuellten `solve.requires` ist jetzt fachlich
        // korrekt, aber noch nicht geloest. Der separate Scenario-Engine-
        // Client (services/scenario-engine, unveraendert) kennt dieses Feld
        // nicht; `?? $result['correct']` erhaelt dessen altes Verhalten exakt
        // (dort faellt `correct` und `solved` weiterhin zusammen).
        $solved = $result['solved'] ?? $result['correct'];

        if ($solved) {
            $attempt->status = 'solved';
            $attempt->flag_submitted_at = now();
        }

        $this->syncAttempt($attempt, $engine, save: true);

        $unlockedAchievements = [];

        // Eine Draft-Vorschau (authorizeAccess() garantiert: nur autorisierte
        // Studio-/Content-Nutzer erreichen diesen Codepfad ueberhaupt fuer
        // eine nicht veroeffentlichte Node) darf niemals Profil-Punkte,
        // Activity-Progress oder Achievements ausloesen -- sonst koennte ein
        // Vorschau-Solve sogar einen globalen, einmaligen Achievement-Slot
        // (z. B. "trailblazer", ADR 0090b) verbrauchen, bevor die Node
        // ueberhaupt veroeffentlicht ist. Die Engine markiert den simulierten
        // Node intern trotzdem als geloest (fuer Write-up/UI), nur die
        // persistente Lernstatistik der Webanwendung bleibt unberuehrt.
        if ($solved && $node->isPublished()) {
            $user = $request->user();
            $profiles->recomputeAfterSolve($user, $node);
            // Achievement-Vergabe (Trailblazer -- globaler Wettlauf um die
            // Erstloesung dieser Node, ADR 0090b -- und alle node-gebundenen
            // Achievements) laeuft deklarativ ueber ActivityProgressRecorder
            // -> AchievementUnlockEvaluator gegen content/achievements.yml
            // (ADR 0077), nicht ueber Controller-Code.
            $unlockedAchievements = $progressRecorder->record('node', $node->slug, $user);
        }

        return response()->json([
            ...$result,
            'solved' => $solved,
            'unlocked_achievements' => $unlockedAchievements,
        ]);
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
            ->where('status', 'published')
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

    /**
     * ADR 0107 (CMS-6d): bevorzugt die DB, ContentRepository bleibt nur
     * noch Fallback fuer eine Node, deren naechster Sync-Lauf noch
     * aussteht (derselbe Fallback wie in show()).
     */
    private function bodyFor(Node $node, ContentRepository $content): string
    {
        return $node->body ?? $content->nodes()[$node->slug]['body'] ?? '';
    }

    /**
     * CMS-7d.3 (ADR 0118): bevorzugt `rich_content` (RichContentRenderer)
     * ueber die weiterhin gepflegten `$legacyMarkdown`-Abschnitte
     * (`NodeSections::parse()` + `MarkdownRenderer`) -- Legacy-Fallback fuer
     * eine noch nicht migrierte Node. `$key` ist `briefing`/`write_up`/
     * `hints`, `$hintId` nur bei `hints` gesetzt.
     */
    private function renderNodeSection(Node $node, ContentRepository $content, string $legacyMarkdown, string $key, ?string $hintId = null): string
    {
        $richContent = $node->rich_content;
        $document = match (true) {
            $richContent === null => null,
            $key === 'hints' => $richContent['hints'][$hintId] ?? null,
            default => $richContent[$key] ?? null,
        };

        if ($document !== null) {
            return (new RichContentRenderer($content->glossary()))->render($document);
        }

        // CMS-7d.4 (Phase 3): messbares Signal fuer den verbleibenden
        // Markdown-Fallback -- deckt sowohl "ganze Node ohne rich_content"
        // als auch den selteneren "einzelnes Feld fehlt in rich_content"-
        // Fall ab. Ziel ist, dass dieser Log-Eintrag im produktiven
        // Bestand nie feuert (siehe rich-content:coverage).
        Log::warning('learner_view.legacy_body_fallback', ['activity_type' => 'node', 'node_slug' => $node->slug, 'field' => $key, 'hint_id' => $hintId]);

        return (new MarkdownRenderer($content->glossary()))->render($legacyMarkdown);
    }

    /**
     * Zentraler Autorisierungsvertrag fuer JEDE Node-Session-Operation
     * (ADR 0110/CMS-6d-Haertung, Abschnitt 4): ein einzeiliger Aufruf pro
     * Endpunkt, die eigentliche Regel lebt ausschliesslich in
     * `NodePolicy::view()`. `abort_unless(..., 404)` statt
     * `Gate::authorize()` (das per Default 403 wirft), um die bestehende
     * Konvention zu erhalten -- eine nicht veroeffentlichte Node soll fuer
     * unberechtigte Nutzer wie "existiert nicht" aussehen, nicht wie
     * "existiert, aber gesperrt".
     */
    private function authorizeAccess(Node $node): void
    {
        abort_unless(Gate::allows('view', $node), 404);
    }

    private function attemptFor(Node $node, EngineClientContract $engine): NodeAttempt|NodePreviewSession
    {
        if (! $node->isPublished()) {
            return $this->previewSessionFor($node, $engine);
        }

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

    /**
     * `authorizeAccess()` laeuft vor jedem Aufruf dieser Methode und laesst
     * fuer eine nicht veroeffentlichte Node ausschliesslich autorisierte
     * Studio-/Content-Nutzer durch (`NodePolicy::view()`) -- jede hier
     * entstehende Session ist deshalb per Konstruktion eine Vorschau, nie
     * ein echter Lernfortschritt. Eine eigene, von `node_attempts` komplett
     * getrennte Tabelle (statt eines Zusatzfeldes dort) verhindert
     * strukturell, dass `ProfileService`/`ActivityProgressRecorder` (beide
     * lesen ausschliesslich `node_attempts`) eine Vorschau je sehen --
     * selbst derselbe Nutzer bekommt nach einer spaeteren Veroeffentlichung
     * einen frischen, echten `node_attempts`-Datensatz, keinen aus der
     * Vorschau wiederverwendeten.
     */
    private function previewSessionFor(Node $node, EngineClientContract $engine): NodePreviewSession
    {
        $preview = NodePreviewSession::firstOrNew(['user_id' => Auth::id(), 'node_id' => $node->id]);

        if (! $preview->exists) {
            $session = $engine->createSession($node->slug);
            $preview->engine_session_id = $session['session_id'];
            $preview->status = 'started';
            $preview->started_at = now();
            $preview->save();
        }

        return $preview;
    }

    private function syncAttempt(NodeAttempt|NodePreviewSession $attempt, EngineClientContract $engine, bool $save = true): void
    {
        $state = $engine->state($attempt->engine_session_id);
        $attempt->hints_used = $state['hints_used'];
        $attempt->points = $state['points'];

        if ($save) {
            $attempt->save();
        }
    }
}
