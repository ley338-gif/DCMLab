<?php

namespace App\Http\Controllers;

use App\Activities\ActivityRegistry;
use App\Content\ContentRepository;
use App\Content\ContentVersioningService;
use App\Content\QuizContent;
use App\Models\Activity;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Zweiter Autoren-Editor (ADR 0071/0081, W6.2): Metadaten und Lektionstext.
 * Bearbeitet nur die Prosa vor einem bestehenden `## Quiz`-Abschnitt --
 * dieser bleibt Sache des Quiz-Editors (ADR 0080) und wird beim
 * Serialisieren automatisch unangetastet erhalten
 * (`LessonActivity::serialize()`). `sandbox`/`lab` (verschachtelte
 * YAML-Bloecke) und `objectives` (mehrzeilige Liste in der Frontmatter)
 * sind bewusst noch nicht Teil dieses Editors, siehe
 * docs/offene-fragen.md.
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
            'body' => 'required|string',
        ]);
    }

    /**
     * Ist-Zustand direkt aus `content/` gelesen (wie
     * `QuizEditorController::currentQuestions()`, ADR 0080) statt ueber die
     * DB-Modelle -- diese werden erst durch `content:sync` aktualisiert und
     * sind daher keine verlaessliche Quelle fuer den aktuellen Editor-Stand.
     * Die Prosa wird auf den Teil vor einem bestehenden Quiz-Abschnitt
     * eingegrenzt, siehe Klassendoc.
     *
     * @return array<string, mixed>
     */
    private function currentFields(Lesson $lesson, ContentRepository $content): array
    {
        $entry = $content->lessons()[$lesson->lesson_id] ?? null;

        if ($entry === null) {
            return [
                'title' => '', 'teaser' => '', 'level' => 'einsteiger', 'duration_minutes' => 1,
                'tools' => [], 'requires' => [], 'glossary_terms' => [], 'body' => '',
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
            'body' => QuizContent::splitBody((string) ($entry['body'] ?? ''))['before'],
        ];
    }
}
