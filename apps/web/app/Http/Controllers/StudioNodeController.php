<?php

namespace App\Http\Controllers;

use App\Activities\ActivityRegistry;
use App\Content\ContentRepository;
use App\Content\ContentVersioningService;
use App\Content\LearnerViewBuilder;
use App\Content\RichContent\NodePayloadNormalizer;
use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\Node;
use App\Models\Themenfeld;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Node-Verwaltung in Studio (ADR 0109, CMS-6d Teil 3) -- der Editor, den
 * ADR 0107/0108 vorbereitet haben: `edit()`/`update()` verwenden denselben
 * `content_versions`-Kreislauf wie Lesson/Quiz (`ContentVersioningService`,
 * generische `ContentVersionController::submit()/publish()`), nicht einen
 * Node-eigenen Freigabeweg.
 *
 * `index()`/`store()`/`duplicate()`/`archive()`/`restore()`/
 * `updateThemenfeld()` sind -- wie bei Track (ADR 0100) -- strukturelle
 * Eingriffe auf der Node-Ressource selbst und deshalb ueber `NodePolicy`
 * (Reviewer/Administrator) gegated. `edit()`/`update()` bearbeiten den
 * eigentlichen Content-Entwurf und pruefen deshalb gegen die `type=node`-
 * Activity via `ActivityPolicy::update()` -- ein zugewiesener Autor darf
 * hier ran, auch wenn er die Node selbst nicht archivieren/duplizieren darf.
 *
 * Runtime-/Sicherheitsparameter (`environment`, `flag`) sind hier nirgends
 * editierbar (ADR 0107/0108) -- `has_runtime_config` zeigt dem Editor nur
 * an, ob `content/nodes/<slug>/` ueberhaupt existiert, damit "Vorschau"
 * nicht blind gegen eine fehlende Engine-Konfiguration laeuft.
 */
class StudioNodeController extends Controller
{
    public function index(ContentRepository $content): Response
    {
        Gate::authorize('viewAny', Node::class);

        $nodes = Node::query()
            ->with('themenfeld')
            ->orderBy('slug')
            ->get()
            ->map(fn (Node $node) => [
                'slug' => $node->slug,
                'title' => $node->title['de'] ?? $node->slug,
                'difficulty' => $node->difficulty,
                'category' => $node->category,
                'status' => $node->status,
                'themenfeld_id' => $node->themenfeld_id,
                'themenfeld_slug' => $node->themenfeld?->slug,
                'points' => $node->points,
                'estimated_minutes' => $node->estimated_minutes,
            ])
            ->values();

        $themenfelder = Themenfeld::query()
            ->orderBy('order')
            ->get(['id', 'slug'])
            ->map(fn (Themenfeld $themenfeld) => ['id' => $themenfeld->id, 'slug' => $themenfeld->slug]);

        return Inertia::render('Studio/Nodes/Index', [
            'nodes' => $nodes,
            'themenfelder' => $themenfelder,
            'can_manage' => Gate::allows('manage', Node::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage', Node::class);

        $data = $request->validate([
            'slug' => 'required|string|max:255|alpha_dash|unique:nodes,slug',
            'title' => 'required|string|max:255',
            'themenfeld_id' => 'required|integer|exists:themenfelder,id',
            'difficulty' => 'required|string|in:easy,medium,hard,insane',
            'category' => 'required|string|max:255',
            'interaction' => 'required|string|in:terminal,scenario',
        ]);

        DB::transaction(function () use ($data): void {
            $sourceHash = hash('sha256', 'studio:'.$data['slug'].':'.now()->toIso8601String());

            Node::query()->create([
                'slug' => $data['slug'],
                'difficulty' => $data['difficulty'],
                'points' => 0,
                'category' => $data['category'],
                'themenfeld_id' => $data['themenfeld_id'],
                'interaction' => $data['interaction'],
                'skills' => [],
                'related_lessons' => [],
                'estimated_minutes' => 0,
                'status' => 'draft',
                'title' => ['de' => $data['title']],
                'scenario_title' => ['de' => ''],
                'body' => '',
                'hints' => [],
                'source_hash' => $sourceHash,
            ]);

            Activity::query()->create([
                'type' => 'node',
                'key' => $data['slug'],
                'status' => 'draft',
                'title' => ['de' => $data['title']],
                'source_hash' => $sourceHash,
            ]);
        });

        return to_route('studio.nodes.edit', ['node' => $data['slug']])->with('status', 'Node angelegt.');
    }

    public function edit(Node $node, ContentRepository $content, ActivityRegistry $registry): Response
    {
        $activity = $this->activityFor($node);
        Gate::authorize('update', $activity);

        $pendingVersion = $activity->contentVersions()
            ->whereIn('status', ['draft', 'review'])
            ->latest()
            ->first();

        $themenfelder = Themenfeld::query()
            ->orderBy('order')
            ->get(['id', 'slug'])
            ->map(fn (Themenfeld $themenfeld) => ['id' => $themenfeld->id, 'slug' => $themenfeld->slug]);

        return Inertia::render('Studio/Nodes/Edit', [
            'node' => [
                'slug' => $node->slug,
                'title' => $node->title['de'] ?? $node->slug,
                'status' => $node->status,
                'themenfeld_id' => $node->themenfeld_id,
                // Ein Studio-erzeugter Runtime-Rumpf existiert nicht -- ohne
                // eine passende content/nodes/<slug>/-Datei laeuft "Vorschau"
                // gegen keine echte Engine-Konfiguration (ADR 0107/0109).
                'has_runtime_config' => ($content->nodes()[$node->slug] ?? null) !== null,
            ],
            // Betreiber-Review vor #126: ein VOR dem Cutover angelegter
            // Entwurf traegt noch `payload.body` (Legacy-Shape) statt
            // `rich_content` -- ohne Normalisierung bekaeme der neue Editor
            // ein Feld, das er nicht versteht. Derselbe Normalizer wie bei
            // Publish/Restore/Preview.
            'fields' => $pendingVersion !== null
                ? (new NodePayloadNormalizer)->normalize($pendingVersion->payload)
                : $registry->resolve($activity)->deserialize(),
            'themenfelder' => $themenfelder,
            'skills_catalog' => ProfileService::SKILL_CATEGORIES,
            // Fuer das Slash-Menue des RichContentEditor ("/glossary", ADR
            // 0114/0118), analog LessonEditorController.
            'glossary' => collect($content->glossary())
                ->map(fn (array $entry, string $slug): array => ['slug' => $slug, 'term' => $entry['term'] ?? $slug])
                ->values(),
            'pending_version' => $pendingVersion === null ? null : [
                'id' => $pendingVersion->id,
                'status' => $pendingVersion->status,
            ],
            'versions' => $activity->contentVersions()
                ->with('author')
                ->latest()
                ->get()
                ->map(fn (ContentVersion $version) => [
                    'id' => $version->id,
                    'status' => $version->status,
                    'is_current' => $version->is_current,
                    'author_name' => $version->author?->name,
                    'published_at' => $version->published_at?->toIso8601String(),
                ]),
            'can_manage' => Gate::allows('manage', Node::class),
            'can_publish' => Gate::allows('publish', $activity),
            // Echte Learner View des ungespeicherten Entwurfs (CMS-7d.3
            // Phase 6, ADR 0118) statt eines Links auf die veroeffentlichte
            // Node -- siehe preview() unten. Ein per Studio angelegter
            // Entwurf hat noch keine `route('nodes.show', ...)`, die fuer
            // Lernende sichtbar waere (ADR 0110), die alte Verlinkung war
            // fuer diesen Fall ohnehin schon nicht brauchbar.
            'preview_url' => route('studio.nodes.preview', $node),
        ]);
    }

    /**
     * Echte Learner View eines ungespeicherten Node-Entwurfs (CMS-7d.3
     * Phase 6, ADR 0118): dieselbe `Nodes/Show`-Seite wie
     * `NodeController::show()`, aber ohne echten `NodeAttempt`/echte
     * Engine-Session -- Briefing/Hinweise/Write-up werden VOLLSTAENDIG
     * gezeigt (kein Punkt-/Klick-Gating), Sandbox/Terminal werden von
     * `Nodes/Show.vue` hinter `preview: true` durch einen Platzhalter
     * ersetzt (Betreiber-Vorgabe: "kein echter NodeAttempt").
     */
    public function preview(Node $node, ActivityRegistry $registry, LearnerViewBuilder $builder): Response
    {
        $activity = $this->activityFor($node);
        Gate::authorize('update', $activity);

        $pendingVersion = $activity->contentVersions()
            ->whereIn('status', ['draft', 'review'])
            ->latest()
            ->first();

        $draft = (new NodePayloadNormalizer)->normalize(
            $pendingVersion !== null ? $pendingVersion->payload : $registry->resolve($activity)->deserialize(),
        );

        return Inertia::render('Nodes/Show', $builder->nodePreviewProps($node, $draft));
    }

    public function validateDraft(Request $request, Node $node, ActivityRegistry $registry): JsonResponse
    {
        $activity = $this->activityFor($node);
        Gate::authorize('update', $activity);

        $issues = $registry->resolve($activity)->validate($this->validatedFields($request));

        return response()->json([
            'issues' => array_map(fn ($issue) => (string) $issue, $issues),
        ]);
    }

    public function update(Request $request, Node $node, ContentVersioningService $versions): RedirectResponse
    {
        $activity = $this->activityFor($node);
        Gate::authorize('update', $activity);

        $versions->createDraft($activity, $this->validatedFields($request), $request->user());

        return back()->with('status', 'Entwurf gespeichert.');
    }

    /**
     * Themenfeld bleibt -- wie track_id bei Lesson und wie bei Track selbst
     * -- eine strukturelle Zuordnung ausserhalb des content_versions-
     * Entwurfs (ADR 0108 schliesst sie aus dem Draft-Schema bewusst aus):
     * ein eigener, sofort wirksamer Endpunkt statt eines Felds im
     * Freigabe-Kreislauf.
     */
    public function updateThemenfeld(Request $request, Node $node): RedirectResponse
    {
        Gate::authorize('manage', Node::class);

        $data = $request->validate([
            'themenfeld_id' => 'required|integer|exists:themenfelder,id',
        ]);

        $node->update(['themenfeld_id' => $data['themenfeld_id']]);

        return back()->with('status', 'Themenfeld aktualisiert.');
    }

    /**
     * Neue Node mit neuem Slug/neuer Activity (Betreiberauftrag: "keine
     * alten Attempts oder Progress-Daten uebernehmen") -- kopiert nur die
     * aktuellen Feldwerte, nie `node_attempts` (die haengen an der alten
     * `node_id`) und nie die `content_versions`-Historie (die Kopie startet
     * bei `draft` wie jede neu angelegte Node).
     */
    public function duplicate(Node $node): RedirectResponse
    {
        Gate::authorize('manage', Node::class);

        $newSlug = $this->uniqueSlugFor($node->slug);

        DB::transaction(function () use ($node, $newSlug): void {
            $sourceHash = hash('sha256', 'studio:'.$newSlug.':'.now()->toIso8601String());
            $title = ($node->title['de'] ?? $node->slug).' (Kopie)';

            Node::query()->create([
                'slug' => $newSlug,
                'difficulty' => $node->difficulty,
                'points' => $node->points,
                'category' => $node->category,
                'themenfeld_id' => $node->themenfeld_id,
                'interaction' => $node->interaction,
                'skills' => $node->skills,
                'related_lessons' => $node->related_lessons,
                'estimated_minutes' => $node->estimated_minutes,
                'status' => 'draft',
                'title' => ['de' => $title],
                'scenario_title' => $node->scenario_title,
                // Betreiber-Review vor #126: `rich_content` ist seit dem
                // Cutover die kanonische Quelle -- `body` bleibt nach dem
                // ersten Rich-Content-Publish absichtlich stale (ADR 0118,
                // "keine zwei schreibenden Sources of Truth"). Ohne diese
                // Zeile haette eine danach duplizierte Node veraltete
                // Inhalte bekommen. `body` bleibt zusaetzlich als
                // Legacy-Fallback stehen.
                'body' => $node->body,
                'rich_content' => $node->rich_content,
                'hints' => $node->hints,
                'source_hash' => $sourceHash,
            ]);

            Activity::query()->create([
                'type' => 'node',
                'key' => $newSlug,
                'status' => 'draft',
                'title' => ['de' => $title],
                'source_hash' => $sourceHash,
            ]);
        });

        return to_route('studio.nodes.edit', ['node' => $newSlug])->with('status', 'Node dupliziert.');
    }

    public function archive(Node $node): RedirectResponse
    {
        Gate::authorize('manage', Node::class);

        $node->update(['status' => 'archived']);

        return back()->with('status', 'Node archiviert.');
    }

    public function restore(Node $node): RedirectResponse
    {
        Gate::authorize('manage', Node::class);

        $node->update(['status' => 'draft']);

        return back()->with('status', 'Node wiederhergestellt.');
    }

    private function activityFor(Node $node): Activity
    {
        return Activity::query()->where('type', 'node')->where('key', $node->slug)->firstOrFail();
    }

    private function uniqueSlugFor(string $slug): string
    {
        $base = $slug.'-kopie';
        $candidate = $base;
        $suffix = 2;

        while (Node::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFields(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string',
            'scenario_title' => 'required|string',
            'difficulty' => 'required|string|in:easy,medium,hard,insane',
            'points' => 'required|integer|min:0',
            'category' => 'required|string',
            'interaction' => 'required|string|in:terminal,scenario',
            'estimated_minutes' => 'required|integer|min:0',
            'skills' => 'array',
            'skills.*' => 'string|in:'.implode(',', ProfileService::SKILL_CATEGORIES),
            'related_lessons' => 'array',
            'related_lessons.*' => 'string',
            'hints' => 'array',
            'hints.*.id' => 'required|string',
            'hints.*.cost' => 'required|integer|min:0',
            // Nur die grobe Form -- die eigentliche Schema-/Inhaltspruefung
            // (RichContentValidator, Hint-ID-Konsistenz) laeuft ueber
            // activity->validate($draft) (CMS-7d.3, ADR 0118). Bewusst KEINE
            // weiteren `rich_content.*`-Regeln, siehe LessonEditorController.
            'rich_content' => 'required|array',
        ]);
    }
}
