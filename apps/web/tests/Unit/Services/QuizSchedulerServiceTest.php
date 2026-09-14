<?php

namespace Tests\Unit\Services;

use App\Models\Lesson;
use App\Models\QuizReview;
use App\Models\User;
use App\Services\QuizSchedulerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vereinfachter, binaerer SM-2 (Abschnitt 4.7, "quiz:"). Kein 1:1-SM-2 --
 * unsere Fragen sind binaer richtig/falsch, siehe Docblock des Service.
 */
class QuizSchedulerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_correct_answer_sets_a_one_day_interval(): void
    {
        $user = User::factory()->create();
        $lesson = Lesson::factory()->create();

        $review = (new QuizSchedulerService)->recordAnswer($user, $lesson, 'q1', true);

        $this->assertSame(1, $review->repetitions);
        $this->assertSame(1, $review->interval_days);
        $this->assertSame('correct', $review->last_result);
        $this->assertTrue($review->due_at->isSameDay(now()->addDay()));
    }

    public function test_second_consecutive_correct_answer_sets_a_six_day_interval(): void
    {
        $user = User::factory()->create();
        $lesson = Lesson::factory()->create();
        $service = new QuizSchedulerService;

        $service->recordAnswer($user, $lesson, 'q1', true);
        $review = $service->recordAnswer($user, $lesson, 'q1', true);

        $this->assertSame(2, $review->repetitions);
        $this->assertSame(6, $review->interval_days);
    }

    public function test_third_consecutive_correct_answer_multiplies_by_ease_factor(): void
    {
        $user = User::factory()->create();
        $lesson = Lesson::factory()->create();
        $service = new QuizSchedulerService;

        $service->recordAnswer($user, $lesson, 'q1', true);
        $second = $service->recordAnswer($user, $lesson, 'q1', true);
        $third = $service->recordAnswer($user, $lesson, 'q1', true);

        $this->assertSame(3, $third->repetitions);
        $this->assertSame((int) round(6 * $second->ease_factor), $third->interval_days);
    }

    public function test_incorrect_answer_resets_repetitions_and_interval(): void
    {
        $user = User::factory()->create();
        $lesson = Lesson::factory()->create();
        $service = new QuizSchedulerService;

        $service->recordAnswer($user, $lesson, 'q1', true);
        $service->recordAnswer($user, $lesson, 'q1', true);
        $review = $service->recordAnswer($user, $lesson, 'q1', false);

        $this->assertSame(0, $review->repetitions);
        $this->assertSame(1, $review->interval_days);
        $this->assertSame('incorrect', $review->last_result);
    }

    public function test_ease_factor_stays_within_bounds(): void
    {
        $user = User::factory()->create();
        $lesson = Lesson::factory()->create();
        $service = new QuizSchedulerService;

        // Viele Fehler hintereinander duerfen ease_factor nicht unter 1.3 druecken.
        $review = null;
        for ($i = 0; $i < 20; $i++) {
            $review = $service->recordAnswer($user, $lesson, 'q1', false);
        }
        $this->assertGreaterThanOrEqual(1.3, $review->ease_factor);

        // Viele Erfolge hintereinander duerfen ease_factor nicht ueber 2.5
        // heben, und interval_days darf nicht unbegrenzt (exponentiell)
        // wachsen -- sonst ueberschreitet due_at irgendwann den Bereich, den
        // Carbon noch parsen kann (siehe Docblock der MAX_INTERVAL_DAYS-Konstante).
        for ($i = 0; $i < 20; $i++) {
            $review = $service->recordAnswer($user, $lesson, 'q1', true);
        }
        $this->assertLessThanOrEqual(2.5, $review->ease_factor);
        $this->assertLessThanOrEqual(3650, $review->interval_days);
    }

    public function test_reviews_are_scoped_per_question_within_a_lesson(): void
    {
        $user = User::factory()->create();
        $lesson = Lesson::factory()->create();
        $service = new QuizSchedulerService;

        $service->recordAnswer($user, $lesson, 'q1', true);
        $q2 = $service->recordAnswer($user, $lesson, 'q2', false);

        $this->assertSame(0, $q2->repetitions);
        $this->assertSame(2, QuizReview::where('user_id', $user->id)->count());
    }
}
