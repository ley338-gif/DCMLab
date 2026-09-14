<?php

namespace App\Http\Controllers;

use App\Content\ContentRepository;
use App\Content\ExamContent;
use App\Content\MarkdownRenderer;
use App\Models\ExamAttempt;
use App\Models\Track;
use App\Services\ExamAttemptService;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Track-Abschlusspruefung (P10.60): eine Frage pro Bildschirm, sofortiges
 * Feedback samt Rueckverweis, Ergebnisseite mit Fehlerverteilung nach
 * Lektion. Kein harter Zugangsschutz -- wer nicht alle Lektionen gelesen
 * hat, bekommt nur einen Hinweis, keine Sperre (Abschnitt 4).
 */
class ExamController extends Controller
{
    public function start(Request $request, Track $track, ExamAttemptService $exams): RedirectResponse
    {
        $onlyLessons = $request->query('from');
        $onlyLessons = is_string($onlyLessons) && $onlyLessons !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $onlyLessons))))
            : null;

        $attempt = $exams->start($request->user(), $track, $onlyLessons);

        return redirect()->route('tracks.exam.show', [$track, $attempt]);
    }

    public function show(Track $track, ExamAttempt $attempt, ContentRepository $content, ExamAttemptService $exams): Response|RedirectResponse
    {
        $this->authorizeAttempt($track, $attempt);

        if ($attempt->status === 'in_progress' && $attempt->current_index >= count($attempt->question_ids)) {
            $exams->complete($attempt);
        }

        if ($attempt->status === 'completed') {
            return redirect()->route('tracks.exam.result', [$track, $attempt]);
        }

        $exam = $content->exams()[$track->slug] ?? null;
        abort_if($exam === null, 404);

        $renderer = new MarkdownRenderer($content->glossary());
        $questionId = $attempt->currentQuestionId();
        $questions = ExamContent::parseQuestions((string) $exam['md_raw'], $exam['meta']['questions'], $renderer);
        $current = collect($questions)->firstWhere('id', $questionId);

        abort_if($current === null, 404);

        return Inertia::render('Exams/Show', [
            'track' => ['slug' => $track->slug, 'title_key' => $track->title_key],
            'attempt' => [
                'id' => $attempt->id,
                'current_index' => $attempt->current_index,
                'total' => count($attempt->question_ids),
            ],
            'question' => $current,
        ]);
    }

    public function answer(Request $request, Track $track, ExamAttempt $attempt, ExamAttemptService $exams): JsonResponse
    {
        $this->authorizeAttempt($track, $attempt);

        $data = $request->validate(['value' => 'required']);

        $result = $exams->answerCurrent($attempt, $data['value']);

        return response()->json([
            ...$result,
            'next_index' => $attempt->current_index,
            'total' => count($attempt->question_ids),
        ]);
    }

    public function result(Track $track, ExamAttempt $attempt, ContentRepository $content): Response
    {
        $this->authorizeAttempt($track, $attempt);
        abort_if($attempt->status !== 'completed', 409);

        $exam = $content->exams()[$track->slug] ?? null;
        abort_if($exam === null, 404);

        $renderer = new MarkdownRenderer($content->glossary());
        /** @var array<int, array<string, mixed>> $pool */
        $pool = $exam['meta']['questions'];
        $poolById = collect($pool)->keyBy('id');
        $lessons = $content->lessons();

        $wrongByLesson = [];
        $wrongQuestions = [];

        foreach ($attempt->answers as $questionId => $answer) {
            $entry = $poolById->get($questionId);

            if ($entry === null || $answer['correct']) {
                continue;
            }

            $lessonId = (string) $entry['lesson'];
            $wrongByLesson[$lessonId] = ($wrongByLesson[$lessonId] ?? 0) + 1;

            $wrongQuestions[] = [
                'id' => $questionId,
                'question_html' => collect(ExamContent::parseQuestions((string) $exam['md_raw'], $pool, $renderer))
                    ->firstWhere('id', $questionId)['question_html'] ?? '',
                'explanation_html' => ExamContent::explanationFor((string) $exam['md_raw'], $questionId, $renderer),
                'review' => ExamContent::reviewTargets($entry),
            ];
        }

        $errorBreakdown = collect($wrongByLesson)
            ->map(fn (int $count, string $lessonId) => [
                'lesson_id' => $lessonId,
                'lesson_title' => $lessonId === 'cross'
                    ? 'Übergreifend'
                    : ($lessons[$lessonId]['frontmatter']['title'] ?? $lessonId),
                'wrong_count' => $count,
            ])
            ->sortByDesc('wrong_count')
            ->values();

        return Inertia::render('Exams/Result', [
            'track' => ['slug' => $track->slug, 'title_key' => $track->title_key],
            'attempt' => [
                'id' => $attempt->id,
                'score_correct' => $attempt->score_correct,
                'score_total' => $attempt->score_total,
                'passed' => $attempt->passed,
                'badge_awarded' => $attempt->badge_awarded,
                'points_awarded' => $attempt->badge_awarded ? ProfileService::TRACK_PASS_POINTS : null,
            ],
            'error_breakdown' => $errorBreakdown,
            'wrong_questions' => $wrongQuestions,
            'retry_lessons' => $errorBreakdown->pluck('lesson_id')->reject(fn ($id) => $id === 'cross')->values(),
        ]);
    }

    private function authorizeAttempt(Track $track, ExamAttempt $attempt): void
    {
        abort_unless($attempt->track_id === $track->id, 404);
        abort_unless($attempt->user_id === Auth::id(), 403);
    }
}
