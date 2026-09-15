<?php

namespace App\Http\Controllers;

use App\Activities\ActivityRegistry;
use App\Content\ContentRepository;
use App\Content\ContentVersioningService;
use App\Content\ExamContent;
use App\Content\HeadingSlug;
use App\Models\Activity;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dritter Autoren-Editor (ADR 0071/0082/0090, W6.3): Einstellungen einer
 * Track-Abschlusspruefung UND (seit ADR 0090) der komplette Fragenpool.
 * `ExamQuestionGenerator` ersetzt bei jedem Speichern die GESAMTE
 * `questions:`-Liste -- der Pool ist eine Einheit (die statistischen
 * Anforderungen aus `ContentValidator::checkExamStructure()` gelten fuer
 * den gesamten Pool, nicht eine einzelne Frage), deshalb liefert
 * `coverage()` die Pool-Uebersicht IMMER gegen den laufenden Entwurf
 * (nicht den gespeicherten Ist-Zustand) -- Typmischung/Abdeckung als
 * Fortschrittsanzeige waehrend der Bearbeitung, wie
 * `dcm-lab-lms-agent-prompt.md` Abschnitt 5 fuer W6.3 fordert.
 */
class ExamEditorController extends Controller
{
    public function edit(Track $track, ContentRepository $content): Response
    {
        $activity = $this->activityFor($track);
        Gate::authorize('update', $activity);

        $pendingVersion = $activity->contentVersions()
            ->whereIn('status', ['draft', 'review'])
            ->latest()
            ->first();

        $fields = $pendingVersion !== null ? $pendingVersion->payload : $this->currentFields($track, $content);

        return Inertia::render('Author/ExamEditor', [
            'track' => [
                'slug' => $track->slug,
                'title_key' => $track->title_key,
            ],
            'fields' => $fields,
            'coverage' => $this->coverage($track, $fields['questions'] ?? [], $content),
            'catalog' => $this->catalog($track, $content),
            'pending_version' => $pendingVersion === null ? null : [
                'id' => $pendingVersion->id,
                'status' => $pendingVersion->status,
            ],
            'can_publish' => Gate::allows('publish', $activity),
        ]);
    }

    public function validateDraft(Request $request, Track $track, ContentRepository $content): JsonResponse
    {
        $activity = $this->activityFor($track);
        Gate::authorize('update', $activity);

        $fields = $this->validatedFields($request);
        $issues = app(ActivityRegistry::class)->resolve($activity)->validate($fields);

        return response()->json([
            'issues' => array_map(fn ($issue) => (string) $issue, $issues),
            'coverage' => $this->coverage($track, $fields['questions'] ?? [], $content),
        ]);
    }

    public function storeDraft(Request $request, Track $track, ContentVersioningService $versions): RedirectResponse
    {
        $activity = $this->activityFor($track);
        Gate::authorize('update', $activity);

        $versions->createDraft($activity, $this->validatedFields($request), $request->user());

        return back()->with('status', 'Entwurf gespeichert.');
    }

    private function activityFor(Track $track): Activity
    {
        return Activity::query()->where('type', 'exam')->where('key', $track->slug)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFields(Request $request): array
    {
        $fields = $request->validate([
            'title' => 'required|string',
            'intro' => 'required|string',
            'pass_percent' => 'required|integer|min:50|max:100',
            'draw' => 'required|integer|min:1',
            'duration_minutes' => 'required|integer|min:1',
            'shuffle' => 'boolean',
            'min_per_lesson' => 'nullable|integer|min:0',
            'questions' => 'nullable|array',
            'questions.*.id' => 'required|string',
            'questions.*.is_ref' => 'boolean',
            'questions.*.ref_lesson' => 'nullable|string',
            'questions.*.ref_question' => 'nullable|string',
            'questions.*.type' => 'nullable|string|in:single,multi,truefalse,input',
            'questions.*.question' => 'nullable|string',
            'questions.*.options' => 'nullable|array',
            'questions.*.options.*' => 'string',
            'questions.*.answer' => 'nullable',
            'questions.*.explanation' => 'nullable|string',
            'questions.*.lesson' => 'required|string',
            'questions.*.review' => 'required|array|min:1',
            'questions.*.review.*.lesson' => 'required|string',
            'questions.*.review.*.anchor' => 'required|string',
            'questions.*.difficulty' => 'required|integer|in:1,2,3',
            'questions.*.tags' => 'array',
            'questions.*.tags.*' => 'string',
        ]);

        return $fields;
    }

    /**
     * Ist-Zustand direkt aus `content/` (wie LessonEditorController), nicht
     * aus DB-Modellen.
     *
     * @return array<string, mixed>
     */
    private function currentFields(Track $track, ContentRepository $content): array
    {
        $entry = $content->exams()[$track->slug] ?? null;

        if ($entry === null) {
            return [
                'title' => '', 'intro' => '', 'pass_percent' => 80, 'draw' => 1,
                'duration_minutes' => 10, 'shuffle' => false, 'min_per_lesson' => 4, 'questions' => [],
            ];
        }

        $meta = $entry['meta'] ?? [];
        $frontmatter = $entry['frontmatter'] ?? [];

        return [
            'title' => $frontmatter['title'] ?? '',
            'intro' => $frontmatter['intro'] ?? '',
            'pass_percent' => $meta['pass_percent'] ?? 80,
            'draw' => $meta['draw'] ?? 1,
            'duration_minutes' => $meta['duration_minutes'] ?? 10,
            'shuffle' => $meta['shuffle'] ?? false,
            'min_per_lesson' => $meta['min_per_lesson'] ?? 4,
            'questions' => $this->currentQuestions($entry, $content),
        ];
    }

    /**
     * Anders als `ExamContent::parseQuestions()` (Lernenden-Ansicht, die
     * `answer` nie ausliefert) zeigt der Editor Autor:innen Antwort und
     * Erklaerung offen -- sie pflegen genau diese Felder.
     *
     * @param  array<string, mixed>  $entry
     * @return list<array<string, mixed>>
     */
    private function currentQuestions(array $entry, ContentRepository $content): array
    {
        $meta = $entry['meta']['questions'] ?? [];
        $blocks = ExamContent::blocksFor((string) ($entry['body'] ?? ''));
        $questions = [];

        foreach ($meta as $item) {
            $ref = $item['ref'] ?? null;

            if ($ref !== null) {
                $questions[] = [
                    'id' => $item['id'],
                    'is_ref' => true,
                    'ref_lesson' => $ref['lesson'],
                    'ref_question' => $ref['question'],
                    'lesson' => $item['lesson'],
                    'review' => ExamContent::reviewTargets($item),
                    'difficulty' => $item['difficulty'] ?? 1,
                    'tags' => $item['tags'] ?? [],
                ];

                continue;
            }

            $block = $blocks[$item['id']] ?? null;
            $type = (string) ($item['type'] ?? 'single');
            $explanationRaw = '';

            if ($block !== null && preg_match('/\*\*Erklärung:\*\*\s*(.+)$/mu', $block['body'], $match) === 1) {
                $explanationRaw = trim($match[1]);
            }

            $questions[] = [
                'id' => $item['id'],
                'is_ref' => false,
                'type' => $type,
                'question' => $block['question'] ?? '',
                'options' => $block !== null ? ExamContent::extractOptions($block['body']) : [],
                'answer' => $item['answer'] ?? null,
                'explanation' => $explanationRaw,
                'lesson' => $item['lesson'],
                'review' => ExamContent::reviewTargets($item),
                'difficulty' => $item['difficulty'] ?? 1,
                'tags' => $item['tags'] ?? [],
            ];
        }

        return $questions;
    }

    /**
     * Kataloge fuer den Fragen-Editor: Lektionen des Tracks (plus "cross"),
     * je Lektion ihre echten Ueberschriften (fuer den Anker-Dropdown, ADR
     * 0090) und ihre Quiz-Fragen-IDs (fuer die `ref`-Auswahl), sowie das
     * kontrollierte Tag-Vokabular.
     *
     * @return array<string, mixed>
     */
    private function catalog(Track $track, ContentRepository $content): array
    {
        $lessons = $content->lessons();
        $trackLessonIds = array_keys(array_filter(
            $lessons,
            fn (array $lesson) => ($lesson['meta']['track'] ?? null) === $track->slug,
        ));

        $headingsByLesson = [];
        $quizQuestionsByLesson = [];

        foreach ($trackLessonIds as $lessonId) {
            $lesson = $lessons[$lessonId];
            $headings = HeadingSlug::headingsIn((string) ($lesson['md_raw'] ?? ''));
            $headingsByLesson[$lessonId] = HeadingSlug::uniqueSlugs($headings);

            $quizQuestionsByLesson[$lessonId] = array_values(array_map(
                fn (array $question): string => (string) $question['id'],
                $lesson['meta']['quiz'] ?? [],
            ));
        }

        return [
            'lessons' => $trackLessonIds,
            'lesson_headings' => $headingsByLesson,
            'lesson_quiz_questions' => $quizQuestionsByLesson,
            'tags' => array_map(fn (array $skill): string => (string) $skill['slug'], $content->skills()),
        ];
    }

    /**
     * Live-Uebersicht IMMER gegen die uebergebene Fragenliste (den
     * laufenden Entwurf, ADR 0090) -- Abdeckung je Lektion gegen
     * `min_per_lesson`, Cross-Fragen gegen die feste Mindestzahl 4,
     * Typmischung und Schwierigkeitsanteil gegen dieselben Bandbreiten wie
     * `ContentValidator::checkExamStructure()`. Eine Anzeige zur
     * Orientierung, keine zweite Quelle der Wahrheit; die tatsaechliche
     * Pruefung bleibt bei `ContentValidator`.
     *
     * @param  list<array<string, mixed>>  $questions
     * @return array<string, mixed>
     */
    private function coverage(Track $track, array $questions, ContentRepository $content): array
    {
        $lessons = $content->lessons();
        $minPerLesson = $content->exams()[$track->slug]['meta']['min_per_lesson'] ?? 4;

        $trackLessonIds = array_keys(array_filter(
            $lessons,
            fn (array $lesson) => ($lesson['meta']['track'] ?? null) === $track->slug,
        ));

        $countsByLesson = [];
        $typeCounts = ['single' => 0, 'multi' => 0, 'truefalse' => 0, 'input' => 0];
        $difficulty3Count = 0;

        foreach ($questions as $question) {
            $lessonId = (string) ($question['lesson'] ?? '');
            $countsByLesson[$lessonId] = ($countsByLesson[$lessonId] ?? 0) + 1;

            $type = ! empty($question['is_ref'])
                ? ExamContent::typeFor(['ref' => ['lesson' => $question['ref_lesson'] ?? '', 'question' => $question['ref_question'] ?? '']], $lessons)
                : (string) ($question['type'] ?? '');

            if (array_key_exists($type, $typeCounts)) {
                $typeCounts[$type]++;
            }

            if ((int) ($question['difficulty'] ?? 0) === 3) {
                $difficulty3Count++;
            }
        }

        $poolSize = count($questions);

        return [
            'pool_size' => $poolSize,
            'min_per_lesson' => $minPerLesson,
            'lessons' => array_map(fn (string $lessonId) => [
                'lesson_id' => $lessonId,
                'count' => $countsByLesson[$lessonId] ?? 0,
                'ok' => ($countsByLesson[$lessonId] ?? 0) >= $minPerLesson,
            ], $trackLessonIds),
            'cross_count' => $countsByLesson['cross'] ?? 0,
            'cross_ok' => ($countsByLesson['cross'] ?? 0) >= 4,
            'type_shares' => $poolSize === 0 ? [] : [
                ['type' => 'single', 'percent' => round($typeCounts['single'] / $poolSize * 100, 1), 'min' => 35, 'max' => 40],
                ['type' => 'multi', 'percent' => round($typeCounts['multi'] / $poolSize * 100, 1), 'min' => 20, 'max' => 25],
                ['type' => 'truefalse', 'percent' => round($typeCounts['truefalse'] / $poolSize * 100, 1), 'min' => 20, 'max' => 25],
                ['type' => 'input', 'percent' => round($typeCounts['input'] / $poolSize * 100, 1), 'min' => 10, 'max' => 15],
            ],
            'difficulty3_share' => $poolSize === 0 ? 0.0 : round($difficulty3Count / $poolSize * 100, 1),
        ];
    }
}
