<?php

namespace App\Http\Controllers;

use App\Activities\ActivityRegistry;
use App\Content\ContentRepository;
use App\Content\ContentVersioningService;
use App\Content\QuizContent;
use App\Models\Activity;
use App\Models\Lesson;
use App\Models\Node;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Zweiter Autoren-Editor (ADR 0071/0081/0089, W6.2): Metadaten und
 * Lektionstext. Bearbeitet nur die Prosa vor einem bestehenden
 * `## Quiz`-Abschnitt -- dieser bleibt Sache des Quiz-Editors (ADR 0080)
 * und wird beim Serialisieren automatisch unangetastet erhalten
 * (`LessonActivity::serialize()`). `sandbox`/`lab` (verschachtelte
 * YAML-Bloecke) und `objectives` (mehrzeilige Liste in der Frontmatter)
 * sind seit ADR 0089 ebenfalls Teil dieses Editors --
 * `objectives_count` in `meta.yml` wird dabei nie direkt entgegengenommen,
 * sondern von `LessonActivity::serialize()` aus der Listenlaenge von
 * `objectives` abgeleitet (muss laut `ContentValidator::
 * checkLessonStructure()` immer uebereinstimmen).
 */
class LessonEditorController extends Controller
{
    public function edit(Lesson $lesson, ContentRepository $content): Response
    {
        $activity = $this->activityFor($lesson);
        Gate::authorize('update', $activity);

        $pendingVersion = $activity->contentVersions()
            ->whereIn('status', ['draft', 'review'])
            ->latest()
            ->first();

        return Inertia::render('Author/LessonEditor', [
            'lesson' => [
                'lesson_id' => $lesson->lesson_id,
                'title' => $lesson->title['de'] ?? $lesson->lesson_id,
            ],
            'fields' => $pendingVersion !== null ? $pendingVersion->payload : $this->currentFields($lesson, $content),
            'catalog' => [
                'tools' => array_keys($content->tools()),
                'glossary_terms' => array_keys($content->glossary()),
                'datasets' => array_keys($content->datasets()),
                // Seit CMS-6d Teil 3 (ADR 0109) auch Nodes, die nur in der DB
                // existieren (per Studio angelegt, kein content/nodes/**) --
                // vorher sah der Composer nur den Datei-Bestand.
                'nodes' => Node::query()->pluck('slug')->merge(array_keys($content->nodes()))->unique()->sort()->values()->all(),
            ],
            'pending_version' => $pendingVersion === null ? null : [
                'id' => $pendingVersion->id,
                'status' => $pendingVersion->status,
            ],
            'can_publish' => Gate::allows('publish', $activity),
        ]);
    }

    public function validateDraft(Request $request, Lesson $lesson): JsonResponse
    {
        $activity = $this->activityFor($lesson);
        Gate::authorize('update', $activity);

        $issues = app(ActivityRegistry::class)->resolve($activity)->validate($this->validatedFields($request));

        return response()->json([
            'issues' => array_map(fn ($issue) => (string) $issue, $issues),
        ]);
    }

    public function storeDraft(Request $request, Lesson $lesson, ContentVersioningService $versions): RedirectResponse
    {
        $activity = $this->activityFor($lesson);
        Gate::authorize('update', $activity);

        $versions->createDraft($activity, $this->validatedFields($request), $request->user());

        return back()->with('status', 'Entwurf gespeichert.');
    }

    private function activityFor(Lesson $lesson): Activity
    {
        return Activity::query()->where('type', 'lesson')->where('key', $lesson->lesson_id)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFields(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string',
            'teaser' => 'required|string',
            'level' => 'required|string|in:einsteiger,aufbau,fortgeschritten',
            'duration_minutes' => 'required|integer|min:1',
            'tools' => 'array',
            'tools.*' => 'string',
            'requires' => 'array',
            'requires.*' => 'string',
            'glossary_terms' => 'array',
            'glossary_terms.*' => 'string',
            'objectives' => 'required|array|min:1',
            'objectives.*' => 'required|string',
            'sandbox' => 'required|array',
            'sandbox.required' => 'required|boolean',
            'sandbox.dataset' => 'nullable|string',
            'sandbox.note' => 'nullable|string',
            'lab' => 'required|array',
            'lab.node' => 'nullable|string',
            'lab.optional' => 'required|boolean',
            'body' => 'required|string',
        ]);
    }

    /**
     * Ist-Zustand seit ADR 0102 (CMS-5b) bevorzugt aus der DB: eine
     * Lektionsfeld-Freigabe schreibt jetzt direkt in die `lessons`-Zeile,
     * nicht mehr nach `content/` -- ein Datei-Ist-Zustand waere fuer eine
     * bereits so veroeffentlichte Lektion veraltet. `content/` bleibt nur
     * noch Fallback fuer eine Lektion, deren naechster `content:sync`-Lauf
     * noch aussteht (siehe LessonController::show(), ADR 0101). Die Prosa
     * wird auf den Teil vor einem bestehenden Quiz-Abschnitt eingegrenzt,
     * siehe Klassendoc.
     *
     * @return array<string, mixed>
     */
    private function currentFields(Lesson $lesson, ContentRepository $content): array
    {
        if ($lesson->body !== null) {
            return [
                'title' => $lesson->title['de'] ?? '',
                'teaser' => $lesson->teaser['de'] ?? '',
                'level' => $lesson->level,
                'duration_minutes' => $lesson->duration_minutes,
                'tools' => $lesson->tools,
                'requires' => $lesson->requires,
                'glossary_terms' => $lesson->glossary_terms,
                'objectives' => $lesson->objectives ?? [],
                'sandbox' => [
                    'required' => $lesson->sandbox['required'] ?? false,
                    'dataset' => $lesson->sandbox['dataset'] ?? null,
                    'note' => $lesson->sandbox['note'] ?? null,
                ],
                'lab' => [
                    'node' => $lesson->lab['node'] ?? null,
                    'optional' => $lesson->lab['optional'] ?? true,
                ],
                'body' => QuizContent::splitBody($lesson->body)['before'],
            ];
        }

        $entry = $content->lessons()[$lesson->lesson_id] ?? null;

        if ($entry === null) {
            return [
                'title' => '', 'teaser' => '', 'level' => 'einsteiger', 'duration_minutes' => 1,
                'tools' => [], 'requires' => [], 'glossary_terms' => [], 'objectives' => [],
                'sandbox' => ['required' => false, 'dataset' => null, 'note' => null],
                'lab' => ['node' => null, 'optional' => true],
                'body' => '',
            ];
        }

        $meta = $entry['meta'] ?? [];
        $frontmatter = $entry['frontmatter'] ?? [];

        return [
            'title' => $frontmatter['title'] ?? '',
            'teaser' => $frontmatter['teaser'] ?? '',
            'level' => $meta['level'] ?? 'einsteiger',
            'duration_minutes' => $meta['duration_minutes'] ?? 1,
            'tools' => $meta['tools'] ?? [],
            'requires' => $meta['requires'] ?? [],
            'glossary_terms' => $meta['glossary_terms'] ?? [],
            'objectives' => $frontmatter['objectives'] ?? [],
            'sandbox' => [
                'required' => $meta['sandbox']['required'] ?? false,
                'dataset' => $meta['sandbox']['dataset'] ?? null,
                'note' => $meta['sandbox']['note'] ?? null,
            ],
            'lab' => [
                'node' => $meta['lab']['node'] ?? null,
                'optional' => $meta['lab']['optional'] ?? true,
            ],
            'body' => QuizContent::splitBody((string) ($entry['body'] ?? ''))['before'],
        ];
    }
}
