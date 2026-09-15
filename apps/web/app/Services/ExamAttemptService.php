<?php

namespace App\Services;

use App\Activities\ActivityProgressRecorder;
use App\Content\AnswerGrader;
use App\Content\ContentRepository;
use App\Content\ExamContent;
use App\Content\MarkdownRenderer;
use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\Track;
use App\Models\TrackBadge;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Zieht und fuehrt einen Versuch der Track-Abschlusspruefung (P10.60). Die
 * Fragen selbst bleiben in content/exams/<track>/{exam.yml,de.md} -- diese
 * Klasse liest sie live und schreibt nur den Versuchs-Zustand in
 * exam_attempts. Kein zweiter Kartenstapel: jede beantwortete Frage, die
 * einer echten Lektion zugeordnet ist, fliesst zusaetzlich in dieselbe
 * quiz_reviews-Tabelle wie die Lektions-Wissenskarten (QuizSchedulerService).
 * Fuer `cross`-Fragen (keine einzelne Lektion) gilt pragmatisch: die erste
 * `review`-Lektion traegt die Wiederholungskarte -- lieber eine bewusst
 * gewaehlte Zuordnung als eine zweite Tabelle nur fuer diesen Randfall.
 */
final class ExamAttemptService
{
    private const CROSS_MIN = 4;

    private const CROSS_MAX = 6;

    private const PER_LESSON_MIN = 2;

    public function __construct(
        private readonly ContentRepository $content,
        private readonly QuizSchedulerService $scheduler,
        private readonly ProfileService $profiles,
        private readonly ActivityProgressRecorder $progressRecorder,
    ) {}

    /**
     * @param  list<string>|null  $onlyLessons  "gezielt wiederholen": Ziehung auf genau diese Lektionen einschraenken
     */
    public function start(User $user, Track $track, ?array $onlyLessons = null): ExamAttempt
    {
        $existing = ExamAttempt::query()
            ->where('user_id', $user->id)
            ->where('track_id', $track->id)
            ->where('status', 'in_progress')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $exam = $this->content->exams()[$track->slug] ?? null;
        abort_if($exam === null || $exam['meta'] === null, 404);

        /** @var array<string, mixed> $meta */
        $meta = $exam['meta'];
        /** @var array<int, array<string, mixed>> $pool */
        $pool = $meta['questions'] ?? [];
        $drawCount = (int) ($meta['draw'] ?? count($pool));
        $shuffle = (bool) ($meta['shuffle'] ?? true);

        $questionIds = $this->draw($pool, $drawCount, $shuffle, $onlyLessons);

        return ExamAttempt::create([
            'user_id' => $user->id,
            'track_id' => $track->id,
            'status' => 'in_progress',
            'question_ids' => $questionIds,
            'current_index' => 0,
            'answers' => [],
            'started_at' => now(),
        ]);
    }

    /**
     * Bewertet die aktuelle Frage, rueckt den Versuch vor und liefert
     * Erklaerung + Rueckverweise -- erst jetzt, nie vorher.
     *
     * @return array{correct: bool, correct_answer: mixed, explanation_html: ?string, review: list<array{lesson: string, anchor: string}>}
     */
    public function answerCurrent(ExamAttempt $attempt, mixed $submitted): array
    {
        abort_if($attempt->status !== 'in_progress', 409);

        $questionId = $attempt->currentQuestionId();
        abort_if($questionId === null, 409);

        [$exam, $entry] = $this->loadQuestion($attempt->track, $questionId);
        $lessons = $this->content->lessons();

        $correctAnswer = ExamContent::answerFor($exam['meta']['questions'], $questionId, $lessons);
        $correct = AnswerGrader::isCorrect(ExamContent::typeFor($entry, $lessons), $submitted, $correctAnswer);

        $answers = $attempt->answers;
        $answers[$questionId] = ['submitted' => $submitted, 'correct' => $correct];
        $attempt->answers = $answers;
        $attempt->current_index++;
        $attempt->save();

        $this->recordReviewCard($attempt->user, $entry, $questionId, $correct);

        $renderer = new MarkdownRenderer($this->content->glossary());

        return [
            'correct' => $correct,
            'correct_answer' => $correctAnswer,
            'explanation_html' => ExamContent::explanationFor((string) $exam['md_raw'], $questionId, $renderer),
            'review' => ExamContent::reviewTargets($entry),
        ];
    }

    /**
     * Schliesst den Versuch ab: Score, Bestehen, TrackBadge/Skill-Radar.
     */
    public function complete(ExamAttempt $attempt): ExamAttempt
    {
        abort_if($attempt->status !== 'in_progress', 409);

        $exam = $this->content->exams()[$attempt->track->slug] ?? null;
        abort_if($exam === null, 404);

        /** @var array<int, array<string, mixed>> $pool */
        $pool = $exam['meta']['questions'] ?? [];
        $poolById = collect($pool)->keyBy('id');

        $answers = $attempt->answers;
        $correctCount = collect($answers)->where('correct', true)->count();
        $total = count($attempt->question_ids);
        $passPercent = (int) ($exam['meta']['pass_percent'] ?? 80);
        $passed = $total > 0 && (($correctCount / $total) * 100) >= $passPercent;

        $answeredQuestions = [];
        foreach ($answers as $questionId => $answer) {
            $entry = $poolById->get($questionId);

            if ($entry === null) {
                continue;
            }

            $answeredQuestions[] = [
                'correct' => (bool) $answer['correct'],
                'tags' => array_values(Arr::wrap($entry['tags'] ?? [])),
            ];
        }

        $badgeNewlyAwarded = $this->profiles->recomputeAfterExamAttempt($attempt->user, $attempt->track, $passed, $answeredQuestions);

        $attempt->status = 'completed';
        $attempt->score_correct = $correctCount;
        $attempt->score_total = $total;
        $attempt->passed = $passed;
        $attempt->badge_awarded = $badgeNewlyAwarded;
        $attempt->completed_at = now();
        $attempt->save();

        $this->progressRecorder->record('exam', $attempt->track->slug, $attempt->user);

        return $attempt;
    }

    /**
     * Pruefungsstatus je Track fuer einen Nutzer -- die eine Stelle fuer die
     * Verfuegbarkeitslogik (P10.65), gemeinsam genutzt von TrackController
     * und DashboardController statt an zwei Stellen dupliziert.
     *
     * @param  iterable<Track>  $tracks
     * @return array<int, array{exam_defined: bool, all_lessons_completed: bool, in_progress_attempt_id: ?int, passed: bool, passed_at: ?string}>
     */
    public function statusForTracks(?User $user, iterable $tracks): array
    {
        $tracks = $tracks instanceof Collection ? $tracks : collect($tracks);
        $trackIds = $tracks->pluck('id')->all();
        $examSlugs = array_keys($this->content->exams());

        $lessonTotals = Lesson::query()
            ->whereIn('track_id', $trackIds)
            ->select('track_id', DB::raw('count(*) as total'))
            ->groupBy('track_id')
            ->pluck('total', 'track_id');

        $completedTotals = $user === null ? collect() : Lesson::query()
            ->whereIn('track_id', $trackIds)
            ->whereHas('progress', fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('status', 'completed'),
            )
            ->select('track_id', DB::raw('count(*) as completed'))
            ->groupBy('track_id')
            ->pluck('completed', 'track_id');

        $inProgressAttempts = $user === null ? collect() : ExamAttempt::query()
            ->where('user_id', $user->id)
            ->whereIn('track_id', $trackIds)
            ->where('status', 'in_progress')
            ->pluck('id', 'track_id');

        $badges = $user === null ? collect() : TrackBadge::query()
            ->where('user_id', $user->id)
            ->whereIn('track_id', $trackIds)
            ->get()
            ->keyBy('track_id');

        $status = [];

        foreach ($tracks as $track) {
            $total = (int) ($lessonTotals[$track->id] ?? 0);
            $completed = (int) ($completedTotals[$track->id] ?? 0);
            $badge = $badges->get($track->id);

            $status[$track->id] = [
                'exam_defined' => in_array($track->slug, $examSlugs, true),
                'all_lessons_completed' => $total > 0 && $completed === $total,
                'in_progress_attempt_id' => $inProgressAttempts[$track->id] ?? null,
                'passed' => $badge !== null,
                'passed_at' => $badge?->awarded_at->toDateString(),
            ];
        }

        return $status;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pool
     * @param  list<string>|null  $onlyLessons
     * @return list<string>
     */
    private function draw(array $pool, int $drawCount, bool $shuffle, ?array $onlyLessons): array
    {
        if ($onlyLessons !== null) {
            $pool = array_values(array_filter(
                $pool,
                fn (array $question): bool => in_array((string) $question['lesson'], $onlyLessons, true),
            ));
        }

        $byLesson = [];
        foreach ($pool as $question) {
            $byLesson[(string) $question['lesson']][] = (string) $question['id'];
        }

        $picked = [];

        foreach ($byLesson as $lesson => $ids) {
            if ($lesson === 'cross') {
                continue;
            }

            shuffle($ids);
            foreach (array_slice($ids, 0, self::PER_LESSON_MIN) as $id) {
                $picked[$id] = true;
            }
        }

        $crossIds = $byLesson['cross'] ?? [];
        shuffle($crossIds);
        $crossTake = min(count($crossIds), self::CROSS_MAX);
        $crossTake = max($crossTake, min(count($crossIds), self::CROSS_MIN));
        foreach (array_slice($crossIds, 0, $crossTake) as $id) {
            $picked[$id] = true;
        }

        $remainingPool = array_values(array_filter(
            array_map(fn (array $question): string => (string) $question['id'], $pool),
            fn (string $id): bool => ! isset($picked[$id]),
        ));
        shuffle($remainingPool);

        foreach ($remainingPool as $id) {
            if (count($picked) >= $drawCount) {
                break;
            }
            $picked[$id] = true;
        }

        $result = array_slice(array_keys($picked), 0, $drawCount);

        if ($shuffle) {
            shuffle($result);
        }

        return $result;
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function loadQuestion(Track $track, string $questionId): array
    {
        $exam = $this->content->exams()[$track->slug] ?? null;
        abort_if($exam === null || $exam['meta'] === null, 404);

        /** @var array<int, array<string, mixed>> $pool */
        $pool = $exam['meta']['questions'] ?? [];

        foreach ($pool as $candidate) {
            if ((string) $candidate['id'] === $questionId) {
                return [$exam, $candidate];
            }
        }

        abort(404);
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function recordReviewCard(User $user, array $entry, string $questionId, bool $correct): void
    {
        $lessonId = (string) $entry['lesson'];

        if ($lessonId === 'cross') {
            $targets = ExamContent::reviewTargets($entry);
            $lessonId = $targets[0]['lesson'] ?? null;
        }

        if ($lessonId === null) {
            return;
        }

        $lesson = Lesson::where('lesson_id', $lessonId)->first();

        if ($lesson !== null) {
            $this->scheduler->recordAnswer($user, $lesson, $questionId, $correct);
        }
    }
}
