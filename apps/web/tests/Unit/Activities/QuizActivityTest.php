<?php

namespace Tests\Unit\Activities;

use App\Activities\QuizActivity;
use App\Content\ContentRepository;
use App\Models\Lesson;
use App\Models\QuizReview;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quiz haengt an seiner Lektion und hat deshalb keinen eigenen
 * `activities`-Verzeichniseintrag -- siehe QuizActivity Klassendoc.
 */
class QuizActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_supports_declares_grading_without_a_completion_state(): void
    {
        $activity = $this->makeActivity();

        $supports = $activity->supports();

        $this->assertTrue($supports->isGraded);
        $this->assertFalse($supports->tracksCompletion);
    }

    public function test_learner_view_counts_the_parsed_questions_and_due_cards(): void
    {
        $lesson = $this->makeLesson();
        $activity = new QuizActivity($lesson, $this->fixtureContent());
        $user = User::factory()->create();

        QuizReview::create([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'question_id' => 'q1',
            'due_at' => now()->subDay(),
        ]);

        $view = $activity->learnerView($user);

        $this->assertSame('quiz', $view['type']);
        $this->assertGreaterThan(0, $view['question_count']);
        $this->assertSame(1, $view['due_count']);
    }

    public function test_result_is_always_null_because_spaced_repetition_has_no_discrete_completion(): void
    {
        $activity = $this->makeActivity();
        $user = User::factory()->create();

        $this->assertNull($activity->result($user));
    }

    public function test_serialize_has_no_own_file_because_it_lives_inside_the_lesson(): void
    {
        $activity = $this->makeActivity();

        $this->assertSame([], $activity->serialize());
    }

    private function makeActivity(): QuizActivity
    {
        return new QuizActivity($this->makeLesson(), $this->fixtureContent());
    }

    private function makeLesson(): Lesson
    {
        $track = Track::factory()->create(['slug' => 'fundamente']);

        return Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id]);
    }

    private function fixtureContent(): ContentRepository
    {
        return new ContentRepository(base_path('tests/Fixtures/content-real'));
    }
}
