<?php

namespace App\Http\Controllers;

use App\Activities\ActivityRegistry;
use App\Content\ContentRepository;
use App\Content\ContentVersioningService;
use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\Lab;
use App\Models\SandboxTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lab-Verwaltung in Studio (CMS-8c, nach StudioNodeController-Vorbild):
 * `edit()`/`update()` verwenden denselben `content_versions`-Kreislauf wie
 * Node/Lesson/Quiz (`ContentVersioningService`, generische
 * `ContentVersionController::submit()/publish()`), kein Lab-eigener
 * Freigabeweg.
 *
 * `index()`/`store()`/`archive()`/`restore()` sind -- wie bei Node --
 * strukturelle Eingriffe auf der Lab-Ressource selbst und deshalb ueber
 * `LabPolicy` (Reviewer/Administrator) gegated. `edit()`/`update()`/
 * `validateDraft()` bearbeiten den eigentlichen Content-Entwurf und
 * pruefen deshalb gegen die `type=lab`-Activity via `ActivityPolicy::
 * update()` -- ein zugewiesener Autor darf hier ran, auch wenn er das Lab
 * selbst nicht archivieren darf.
 *
 * Kein Themenfeld-Aequivalent (Lab hat keine Themenfeld-Zuordnung), keine
 * `duplicate()`/`preview()`-Routen (nicht angefordert, CMS-8c-Scope).
 */
class StudioLabController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Lab::class);

        $labs = Lab::query()
            ->orderBy('slug')
            ->get()
            ->map(fn (Lab $lab) => [
                'slug' => $lab->slug,
                'title' => $lab->title['de'] ?? $lab->slug,
                'difficulty' => $lab->difficulty,
                'status' => $lab->status,
                'points' => $lab->points,
                'estimated_minutes' => $lab->estimated_minutes,
                'runtime_template' => $lab->runtime_template,
                'dataset' => $lab->dataset,
            ])
            ->values();

        return Inertia::render('Studio/Labs/Index', [
            'labs' => $labs,
            'can_manage' => Gate::allows('manage', Lab::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage', Lab::class);

        $data = $request->validate([
            'slug' => 'required|string|max:255|alpha_dash|unique:labs,slug',
            'title' => 'required|string|max:255',
            'difficulty' => 'required|string|in:easy,medium,hard,insane',
        ]);

        DB::transaction(function () use ($data): void {
            $sourceHash = hash('sha256', 'studio:'.$data['slug'].':'.now()->toIso8601String());

            Lab::query()->create([
                'slug' => $data['slug'],
                'difficulty' => $data['difficulty'],
                'points' => 0,
                'estimated_minutes' => 0,
                'runtime_template' => null,
                'dataset' => null,
                'assertions' => [],
                'status' => 'draft',
                'title' => ['de' => $data['title']],
                'scenario_title' => ['de' => ''],
                // Betreiber-Korrektur (CMS-8c): RichContentEditor.vue
                // erwartet ein echtes Dokument, nie null -- ein frisch
                // angelegtes Lab bekommt deshalb sofort ein leeres, aber
                // gueltiges Rich-Content-Dokument statt null.
                'rich_content' => ['type' => 'doc', 'version' => 1, 'content' => []],
            ]);

            Activity::query()->create([
                'type' => 'lab',
                'key' => $data['slug'],
                'status' => 'draft',
                'title' => ['de' => $data['title']],
                'source_hash' => $sourceHash,
            ]);
        });

        return to_route('studio.labs.edit', ['lab' => $data['slug']])->with('status', 'Lab angelegt.');
    }

    public function edit(Lab $lab, ContentRepository $content, ActivityRegistry $registry): Response
    {
        $activity = $this->activityFor($lab);
        Gate::authorize('update', $activity);

        $pendingVersion = $activity->contentVersions()
            ->whereIn('status', ['draft', 'review'])
            ->latest()
            ->first();

        $fields = $pendingVersion !== null ? $pendingVersion->payload : $registry->resolve($activity)->deserialize();

        // Betreiber-Review: die Katalogoptionen (inklusive der "nicht mehr
        // verfuegbar"-Markierung fuer einen archivierten/entfernten Wert,
        // siehe templateOptions()/datasetOptions()) muessen zu den Feldern
        // passen, die der Editor tatsaechlich anzeigt -- bei einem
        // pending Draft/Review ist das $fields (der Entwurf), NICHT mehr
        // die Live-Lab-Zeile. Sonst faellt ein archivierter/entfernter
        // Entwurfswert aus dem <select>, ohne als "nicht mehr verfuegbar"
        // markiert zu werden.
        $selectedTemplate = is_string($fields['runtime_template'] ?? null) ? $fields['runtime_template'] : null;
        $selectedDataset = is_string($fields['dataset'] ?? null) ? $fields['dataset'] : null;

        return Inertia::render('Studio/Labs/Edit', [
            'lab' => [
                'slug' => $lab->slug,
                'title' => $lab->title['de'] ?? $lab->slug,
                'status' => $lab->status,
            ],
            'fields' => $fields,
            'sandbox_templates' => $this->templateOptions($selectedTemplate),
            'datasets' => $this->datasetOptions($content, $selectedDataset),
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
            'can_manage' => Gate::allows('manage', Lab::class),
            'can_publish' => Gate::allows('publish', $activity),
        ]);
    }

    public function validateDraft(Request $request, Lab $lab, ActivityRegistry $registry): JsonResponse
    {
        $activity = $this->activityFor($lab);
        Gate::authorize('update', $activity);

        $issues = $registry->resolve($activity)->validate($this->validatedFields($request));

        return response()->json([
            'issues' => array_map(fn ($issue) => (string) $issue, $issues),
        ]);
    }

    public function update(Request $request, Lab $lab, ContentVersioningService $versions): RedirectResponse
    {
        $activity = $this->activityFor($lab);
        Gate::authorize('update', $activity);

        $versions->createDraft($activity, $this->validatedFields($request), $request->user());

        return back()->with('status', 'Entwurf gespeichert.');
    }

    public function archive(Lab $lab): RedirectResponse
    {
        Gate::authorize('manage', Lab::class);

        $lab->update(['status' => 'archived']);

        return back()->with('status', 'Lab archiviert.');
    }

    public function restore(Lab $lab): RedirectResponse
    {
        Gate::authorize('manage', Lab::class);

        $lab->update(['status' => 'draft']);

        return back()->with('status', 'Lab wiederhergestellt.');
    }

    private function activityFor(Lab $lab): Activity
    {
        return Activity::query()->where('type', 'lab')->where('key', $lab->slug)->firstOrFail();
    }

    /**
     * Betreiber-Korrektur (CMS-8c, UX): der Katalog zeigt nur veroeffentlichte
     * Vorlagen, aber ein bereits gesetzter Slug darf nicht kommentarlos aus
     * der Auswahl verschwinden, nur weil er inzwischen archiviert/geloescht
     * wurde -- sonst sieht der Autor ein leeres Dropdown ohne zu wissen,
     * warum. Der aktuelle Slug wird deshalb zusaetzlich mit `available:
     * false` durchgereicht, wenn er nicht (mehr) im published-Katalog steht.
     *
     * @return list<array{slug: string, name: string, available: bool}>
     */
    private function templateOptions(?string $selectedSlug): array
    {
        $published = SandboxTemplate::query()->where('status', 'published')->orderBy('slug')->get(['slug', 'name']);

        $options = [];

        foreach ($published as $template) {
            $options[] = ['slug' => $template->slug, 'name' => $template->name, 'available' => true];
        }

        if ($selectedSlug !== null && ! $published->contains('slug', $selectedSlug)) {
            $stale = SandboxTemplate::query()->where('slug', $selectedSlug)->first(['slug', 'name']);
            $name = $stale === null ? $selectedSlug : $stale->name;

            $options[] = ['slug' => $selectedSlug, 'name' => $name, 'available' => false];
        }

        return $options;
    }

    /**
     * Dasselbe Muster wie `templateOptions()`, fuer `content/datasets.yml`.
     *
     * @return list<array{slug: string, available: bool}>
     */
    private function datasetOptions(ContentRepository $content, ?string $selectedSlug): array
    {
        $slugs = array_keys($content->datasets());

        $options = [];

        foreach ($slugs as $slug) {
            $options[] = ['slug' => $slug, 'available' => true];
        }

        if ($selectedSlug !== null && ! in_array($selectedSlug, $slugs, true)) {
            $options[] = ['slug' => $selectedSlug, 'available' => false];
        }

        return $options;
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
            'estimated_minutes' => 'required|integer|min:0',
            'runtime_template' => 'nullable|string',
            'dataset' => 'nullable|string',
            'assertions' => 'array',
            'assertions.*.type' => 'required|string',
            'assertions.*.prefix' => 'nullable|string',
            // Nur die grobe Form -- die eigentliche Schema-/Katalogpruefung
            // (SandboxTemplate/datasets.yml, Assertion-Feldform pro Typ,
            // RichContentValidator) laeuft ueber activity->validate($draft)
            // (siehe LabActivity::validate()), bewusst KEINE weiteren
            // `rich_content.*`-Regeln hier (analog StudioNodeController).
            'rich_content' => 'required|array',
        ]);
    }
}
