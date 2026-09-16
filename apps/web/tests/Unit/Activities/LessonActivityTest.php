<?php

namespace Tests\Unit\Activities;

use App\Activities\LessonActivity;
use App\Content\ContentRepository;
use App\Content\FrontMatter;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Yaml\Yaml;
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
        $this->assertNull($supports->runtimeType);
        $this->assertTrue($supports->versionable);
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

    /**
     * CMS-7d.3: `rich_content` (DB) ist die kanonische Prosa-Quelle, nicht
     * mehr `content/**` -- ein `rich_content`-Entwurf regeneriert deshalb
     * keine Prosa mehr in `de.md` (weder vor noch nach dem Quiz-Abschnitt);
     * die Datei bleibt insofern exakt so stehen, wie sie war.
     */
    public function test_serialize_with_a_rich_content_draft_does_not_touch_the_markdown_body(): void
    {
        $lesson = $this->makeLesson();
        $content = $this->fixtureContent();
        $activity = new LessonActivity($lesson, $content);
        $originalMdRaw = $content->lessons()['1.0']['md_raw'];

        $files = $activity->serialize(['rich_content' => ['type' => 'doc', 'version' => 1, 'content' => []]]);

        $this->assertSame($originalMdRaw, $files[1]['contents']);
    }

    /**
     * `syntheticEntry()` (intern von validate($draft) benutzt) muss den
     * Entwurf trotzdem als `rich_content` sehen, damit ContentValidator die
     * RichContentDocument-Fassung seiner Regeln pruefen kann.
     */
    public function test_validate_with_a_rich_content_draft_checks_the_rich_content_document(): void
    {
        $activity = $this->makeActivity();

        $issues = $activity->validate([
            'rich_content' => ['type' => 'doc', 'version' => 1, 'content' => []],
        ]);

        $this->assertTrue(collect($issues)->contains(
            fn ($issue) => str_contains($issue->message, 'keinen einzigen Codeblock'),
        ));
    }

    public function test_serialize_with_a_sandbox_draft_regenerates_only_the_sandbox_block(): void
    {
        $activity = $this->makeActivity();

        $files = $activity->serialize(['sandbox' => ['required' => false]]);
        $parsed = Yaml::parse($files[0]['contents']);

        $this->assertFalse($parsed['sandbox']['required']);
        $this->assertArrayNotHasKey('dataset', $parsed['sandbox']);
        $this->assertSame(['node' => null, 'optional' => true], $parsed['related_node'], 'lab darf unangetastet bleiben.');
    }

    public function test_serialize_with_a_lab_draft_regenerates_only_the_lab_block(): void
    {
        $activity = $this->makeActivity();

        $files = $activity->serialize(['related_node' => ['node' => 'silent-ct', 'optional' => false]]);
        $parsed = Yaml::parse($files[0]['contents']);

        $this->assertSame('silent-ct', $parsed['related_node']['node']);
        $this->assertFalse($parsed['related_node']['optional']);
        $this->assertTrue($parsed['sandbox']['required'], 'sandbox darf unangetastet bleiben.');
    }

    public function test_serialize_with_an_objectives_draft_keeps_objectives_count_in_sync(): void
    {
        $activity = $this->makeActivity();

        $files = $activity->serialize(['objectives' => ['Eins', 'Zwei']]);
        $parsedMeta = Yaml::parse($files[0]['contents']);
        $frontMatter = FrontMatter::parse($files[1]['contents']);

        $this->assertSame(['Eins', 'Zwei'], $frontMatter['attributes']['objectives']);
        $this->assertSame(2, $parsedMeta['objectives_count']);
    }

    public function test_validate_with_an_objectives_draft_that_mismatches_the_count_field_is_impossible_by_construction(): void
    {
        // objectives_count wird von serialize() immer aus der Listenlaenge
        // von "objectives" abgeleitet (nie eigenstaendig aus dem Entwurf
        // uebernommen) -- ein Mismatch kann ueber diesen Weg gar nicht erst
        // entstehen. Dieser Test dokumentiert genau das.
        $activity = $this->makeActivity();

        $issues = $activity->validate(['objectives' => ['Nur eins']]);

        $this->assertFalse(collect($issues)->contains(
            fn ($issue) => str_contains($issue->message, 'objectives_count'),
        ));
    }

    public function test_deserialize_normalizes_the_current_fields(): void
    {
        $activity = $this->makeActivity();

        $draft = $activity->deserialize();

        $this->assertSame('1.0', $draft['lesson_id']);
        $this->assertSame('fundamente', $draft['track']);
        $this->assertSame('doc', $draft['rich_content']['type']);
        $this->assertSame(1, $draft['rich_content']['version']);
        $this->assertNotEmpty($draft['rich_content']['content']);
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
