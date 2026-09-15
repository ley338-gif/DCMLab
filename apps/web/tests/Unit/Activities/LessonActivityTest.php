<?php

namespace Tests\Unit\Activities;

use App\Activities\LessonActivity;
use App\Content\ContentRepository;
use App\Content\FrontMatter;
use App\Content\QuizContent;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_supports_declares_completion_tracking_without_grading(): void
    {
        $activity = $this->makeActivity();

        $supports = $activity->supports();

        $this->assertTrue($supports->tracksCompletion);
        $this->assertFalse($supports->isGraded);
        $this->assertFalse($supports->needsContainer);
        $this->assertTrue($supports->authorable);
    }

    public function test_learner_view_reflects_not_started_without_progress(): void
    {
        $activity = $this->makeActivity();
        $user = User::factory()->create();

        $view = $activity->learnerView($user);

        $this->assertSame('lesson', $view['type']);
        $this->assertSame('1.0', $view['lesson_id']);
        $this->assertSame('not_started', $view['status']);
    }

    public function test_result_is_null_before_any_progress_exists(): void
    {
        $activity = $this->makeActivity();
        $user = User::factory()->create();

        $this->assertNull($activity->result($user));
    }

    public function test_result_reflects_completed_lesson_progress(): void
    {
        $lesson = $this->makeLesson();
        $activity = new LessonActivity($lesson, $this->fixtureContent());
        $user = User::factory()->create();

        LessonProgress::create([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'status' => 'completed',
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
        ]);

        $result = $activity->result($user);

        $this->assertNotNull($result);
        $this->assertTrue($result->completed);
        $this->assertNotNull($result->completedAt);
    }

    public function test_serialize_returns_the_current_meta_and_markdown_files_unchanged(): void
    {
        $activity = $this->makeActivity();

        $files = $activity->serialize();

        $this->assertCount(2, $files);
        $this->assertSame('lessons/1.0/meta.yml', $files[0]['path']);
        $this->assertStringContainsString('track: fundamente', $files[0]['contents']);
        $this->assertSame('lessons/1.0/de.md', $files[1]['path']);
    }

    public function test_validate_delegates_to_the_shared_content_validator_scoped_to_this_lesson(): void
    {
        $activity = $this->makeActivity();

        $issues = $activity->validate();

        foreach ($issues as $issue) {
            $this->assertStringStartsWith('lessons/1.0/', $issue->file);
        }
    }

    public function test_serialize_with_a_quiz_draft_regenerates_the_quiz_block_and_section(): void
    {
        $activity = $this->makeActivity();
        $draft = ['quiz' => [
            ['id' => 'q1', 'type' => 'single', 'answer' => 0, 'question' => 'Neue Frage?', 'options' => ['Ja', 'Nein']],
        ]];

        $files = $activity->serialize($draft);

        $this->assertStringContainsString("quiz:\n  - id: q1\n    type: single\n    answer: 0", $files[0]['contents']);
        $this->assertStringContainsString('**q1 — Neue Frage?**', $files[1]['contents']);
    }

    public function test_validate_with_a_quiz_draft_catches_an_out_of_range_answer(): void
    {
        $activity = $this->makeActivity();
        $draft = ['quiz' => [
            ['id' => 'q1', 'type' => 'single', 'answer' => 5, 'question' => 'Neue Frage?', 'options' => ['Ja', 'Nein']],
        ]];

        $issues = $activity->validate($draft);

        $this->assertTrue(collect($issues)->contains(
            fn ($issue) => str_contains($issue->message, 'answer-Index liegt ausserhalb'),
        ));
    }

    public function test_validate_with_a_valid_quiz_draft_raises_no_quiz_issue(): void
    {
        $activity = $this->makeActivity();
        $draft = ['quiz' => [
            ['id' => 'q1', 'type' => 'single', 'answer' => 0, 'question' => 'Neue Frage?', 'options' => ['Ja', 'Nein']],
        ]];

        $issues = $activity->validate($draft);

        $this->assertFalse(collect($issues)->contains(
            fn ($issue) => str_contains($issue->message, 'Quiz-Frage'),
        ));
    }

    public function test_validate_without_a_draft_still_checks_the_current_state(): void
    {
        $activity = $this->makeActivity();

        $withDraft = $activity->validate(null);
        $withoutArgument = $activity->validate();

        $this->assertEquals($withoutArgument, $withDraft);
    }

    public function test_serialize_with_a_meta_draft_regenerates_only_the_named_fields(): void
    {
        $activity = $this->makeActivity();

        $files = $activity->serialize([
            'level' => 'aufbau',
            'duration_minutes' => 20,
            'tools' => ['dcmftest'],
            'requires' => [],
            'glossary_terms' => ['dicom'],
            'title' => 'Neuer Titel',
            'teaser' => 'Neuer Teaser',
        ]);

        $this->assertStringContainsString('level: aufbau', $files[0]['contents']);
        $this->assertStringContainsString('duration_minutes: 20', $files[0]['contents']);

        $frontMatter = FrontMatter::parse($files[1]['contents']);
        $this->assertSame('Neuer Titel', $frontMatter['attributes']['title']);
        $this->assertSame('Neuer Teaser', $frontMatter['attributes']['teaser']);
    }

    public function test_serialize_with_a_body_draft_preserves_the_existing_quiz_section(): void
    {
        $lesson = $this->makeLesson();
        $content = $this->fixtureContent();
        $activity = new LessonActivity($lesson, $content);
        $originalBody = $content->lessons()['1.0']['body'];
        $originalQuizRaw = QuizContent::splitBody($originalBody)['quiz_raw'];

        $files = $activity->serialize(['body' => '## Neue Einleitung'.PHP_EOL.PHP_EOL.'Neuer Text.']);

        $this->assertStringContainsString('Neue Einleitung', $files[1]['contents']);
        $this->assertStringContainsString('Neuer Text.', $files[1]['contents']);
        $this->assertStringContainsString(trim($originalQuizRaw), $files[1]['contents']);
    }

    public function test_deserialize_normalizes_the_current_fields(): void
    {
        $activity = $this->makeActivity();

        $draft = $activity->deserialize();

        $this->assertSame('1.0', $draft['lesson_id']);
        $this->assertSame('fundamente', $draft['track']);
        $this->assertIsString($draft['body']);
    }

    private function makeActivity(): LessonActivity
    {
        return new LessonActivity($this->makeLesson(), $this->fixtureContent());
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
