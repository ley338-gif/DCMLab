<?php

namespace App\Http\Controllers;

use App\Activities\ActivityRegistry;
use App\Content\ContentRepository;
use App\Content\ContentVersioningService;
use App\Content\LearnerViewBuilder;
use App\Content\QuizContent;
use App\Content\RichContent\LessonPayloadNormalizer;
use App\Models\Activity;
use App\Models\Lesson;
use App\Models\Node;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            // Betreiber-Review vor #126: ein VOR dem Cutover angelegter
            // Entwurf traegt noch `payload.body` (Legacy-Shape) statt
            // `rich_content` -- ohne Normalisierung bekaeme der neue Editor
            // ein Feld, das er nicht versteht. Derselbe Normalizer wie bei
            // Publish/Restore/Preview, damit alle vier Wege dasselbe
            // Ergebnis zeigen.
            'fields' => $pendingVersion !== null
                ? (new LessonPayloadNormalizer)->normalize($pendingVersion->payload, $lesson->body)
                : $this->currentFields($lesson, $content),
            'catalog' => [
                'tools' => array_keys($content->tools()),
                'glossary_terms' => array_keys($content->glossary()),
                // Fuer das Slash-Menue des RichContentEditor ("/glossary",
                // ADR 0114/0118) -- {slug, term}-Paare statt nur Slugs,
                // damit die Suche nach dem lesbaren Begriff funktioniert.
                'glossary' => collect($content->glossary())
                    ->map(fn (array $entry, string $slug): array => ['slug' => $slug, 'term' => $entry['term'] ?? $slug])
                    ->values(),
                'datasets' => array_keys($content->datasets()),
                // Seit CMS-6d Teil 3 (ADR 0109) aus der DB statt aus
                // ContentRepository -- damit sieht der Composer auch eine
                // rein per Studio angelegte Node (kein content/nodes/**).
                // Seit ADR 0110 (CMS-6d Haertung) nur "published": ein
                // Lab-Verweis auf einen noch nicht freigegebenen Entwurf
                // waere fuer Lernende ein gesperrter Link. Jede real
                // existierende Datei-Node traegt diesen Status ohnehin schon
                // in der DB, sobald ihr naechster content:sync gelaufen ist.
                'nodes' => Node::query()->where('status', 'published')->pluck('slug')->sort()->values()->all(),
            ],
            'pending_version' => $pendingVersion === null ? null : [
                'id' => $pendingVersion->id,
                'status' => $pendingVersion->status,
            ],
            'can_publish' => Gate::allows('publish', $activity),
            // Echte Learner View des ungespeicherten Entwurfs (CMS-7d.3
            // Phase 6, ADR 0118) statt eines Links auf die veroeffentlichte
            // Lektion -- siehe preview() unten.
            'preview_url' => route('author.lessons.edit.preview', $lesson),
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

    /**
     * Echte Learner View eines ungespeicherten Entwurfs (CMS-7d.3 Phase 6,
     * ADR 0118): dieselbe `Lessons/Show`-Seite wie `LessonController::
     * show()`, aber mit den entwurfsbetroffenen Feldern einer NIE
     * gespeicherten Kopie ueberschrieben (`clone`, kein `save()`) --
     * `body`, Track-Zugehoerigkeit, Quiz und `lesson_elements` bleiben
     * unveraendert (der Rich-Content-Cutover fasst sie nicht an). Kein
     * zweiter Renderer, `LearnerViewBuilder::lessonProps(...,
     * trackProgress: false)` verhindert dabei, dass das blosse Ansehen
     * eines Entwurfs echten Lernfortschritt fuer den Autor anlegt.
     */
    public function preview(Lesson $lesson, ContentRepository $content, LearnerViewBuilder $builder): Response
    {
        $activity = $this->activityFor($lesson);
        Gate::authorize('update', $activity);

        $pendingVersion = $activity->contentVersions()
            ->whereIn('status', ['draft', 'review'])
            ->latest()
            ->first();

        $draft = $pendingVersion !== null
            ? (new LessonPayloadNormalizer)->normalize($pendingVersion->payload, $lesson->body)
            : (new LessonPayloadNormalizer)->normalize($this->currentFields($lesson, $content));

        $previewLesson = clone $lesson;
        $previewLesson->title = ['de' => $draft['title']];
        $previewLesson->teaser = ['de' => $draft['teaser']];
        $previewLesson->level = $draft['level'];
        $previewLesson->duration_minutes = $draft['duration_minutes'];
        $previewLesson->tools = $draft['tools'];
        $previewLesson->requires = $draft['requires'];
        $previewLesson->glossary_terms = $draft['glossary_terms'];
        $previewLesson->objectives = $draft['objectives'];
        $previewLesson->sandbox = $draft['sandbox'];
        $previewLesson->related_node = $draft['related_node'];
        $previewLesson->rich_content = $draft['rich_content'];

        return Inertia::render('Lessons/Show', $builder->lessonProps($previewLesson, Auth::user(), trackProgress: false));
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
            'related_node' => 'required|array',
            'related_node.node' => 'nullable|string',
            'related_node.optional' => 'required|boolean',
            // Nur die grobe Form (ein Objekt) wird hier erzwungen -- die
            // eigentliche Schema-/Inhaltspruefung (RichContentValidator,
            // Leseanleitung/Glossar/Werkzeug-Regeln) laeuft ueber
            // activity->validate($draft), nicht ueber Formular-Regeln
            // (CMS-7d.3, ADR 0118). Bewusst KEINE weiteren
            // `rich_content.*`-Regeln: Laravels validate() liesse sonst nur
            // die explizit benannten Unterschluessel durch und wuerde
            // `content` (und alles andere) aus dem validierten Ergebnis
            // stillschweigend herausfiltern.
            'rich_content' => 'required|array',
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
        if ($lesson->body !== null || $lesson->rich_content !== null) {
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
                'related_node' => [
                    'node' => $lesson->related_node['node'] ?? null,
                    'optional' => $lesson->related_node['optional'] ?? true,
                ],
                // CMS-7d.3: rich_content ist die kanonische Prosa-Quelle.
                // Ist die Spalte noch nicht befuellt, normalisiert derselbe
                // Normalizer wie ueberall sonst den Legacy-Body -- nur die
                // Prosa (before+after), nie den Quiz-Abschnitt selbst
                // (der bleibt Sache des Quiz-Editors).
                'rich_content' => $lesson->rich_content
                    ?? (new LessonPayloadNormalizer)->normalize(['body' => $this->legacyProse($lesson->body ?? '')])['rich_content'],
            ];
        }

        $entry = $content->lessons()[$lesson->lesson_id] ?? null;

        if ($entry === null) {
            return [
                'title' => '', 'teaser' => '', 'level' => 'einsteiger', 'duration_minutes' => 1,
                'tools' => [], 'requires' => [], 'glossary_terms' => [], 'objectives' => [],
                'sandbox' => ['required' => false, 'dataset' => null, 'note' => null],
                'related_node' => ['node' => null, 'optional' => true],
                'rich_content' => ['type' => 'doc', 'version' => 1, 'content' => []],
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
            'related_node' => [
                'node' => $meta['related_node']['node'] ?? null,
                'optional' => $meta['related_node']['optional'] ?? true,
            ],
            'rich_content' => (new LessonPayloadNormalizer)->normalize(['body' => $this->legacyProse((string) ($entry['body'] ?? ''))])['rich_content'],
        ];
    }

    /**
     * `before` und `after` (`QuizContent::splitBody()`) zusammen, ohne den
     * Quiz-Abschnitt selbst -- derselbe Ausschnitt, den `rich-content:
     * migrate` (CMS-7d.2) und `LessonController::show()` als EIN
     * Content-Element behandeln.
     */
    private function legacyProse(string $body): string
    {
        $split = QuizContent::splitBody($body);

        return trim($split['before']."\n\n".$split['after']);
    }
}
