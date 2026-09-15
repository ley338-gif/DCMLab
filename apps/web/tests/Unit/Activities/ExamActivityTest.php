<?php

namespace Tests\Unit\Activities;

use App\Activities\ExamActivity;
use App\Content\ContentRepository;
use App\Content\FrontMatter;
use App\Models\ExamAttempt;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class ExamActivityTest extends TestCase
{
    use RefreshDatabase;

    private string $contentDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentDir = storage_path('framework/testing/exam-activity-'.uniqid());

        File::ensureDirectoryExists($this->contentDir.'/exams/fundamente');
        File::put(
            $this->contentDir.'/exams/fundamente/exam.yml',
            "track: fundamente\ntitle_key: exam.fundamente.title\npass_percent: 80\ndraw: 2\nduration_minutes: 10\nshuffle: false\nquestions:\n  - id: f01\n    type: single\n    answer: 0\n    lesson: \"1.0\"\n    tags: [netzwerk]\n",
        );
        File::put(
            $this->contentDir.'/exams/fundamente/de.md',
            "---\ntitle: Abschlusspruefung — Fundamente\n---\n\n### f01 — Frage?\n\n1. Ja\n2. Nein\n",
        );
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentDir);

        parent::tearDown();
    }

    public function test_supports_declares_grading_without_a_container(): void
    {
        $activity = $this->makeActivity();

        $supports = $activity->supports();

        $this->assertTrue($supports->isGraded);
        $this->assertFalse($supports->needsContainer);
        $this->assertFalse($supports->freelyPlaceable);
    }

    public function test_result_is_null_before_any_completed_attempt(): void
    {
        $activity = $this->makeActivity();
        $user = User::factory()->create();

        $this->assertNull($activity->result($user));
    }

    public function test_result_reflects_the_latest_completed_attempt(): void
    {
        $track = $this->makeTrack();
        $activity = new ExamActivity($track, new ContentRepository($this->contentDir));
        $user = User::factory()->create();

        ExamAttempt::create([
            'user_id' => $user->id,
            'track_id' => $track->id,
            'status' => 'completed',
            'question_ids' => ['f01'],
            'current_index' => 1,
            'answers' => ['f01' => ['submitted' => 0, 'correct' => true]],
            'score_correct' => 1,
            'score_total' => 1,
            'passed' => true,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
        ]);

        $result = $activity->result($user);

        $this->assertNotNull($result);
        $this->assertTrue($result->completed);
        $this->assertSame(1, $result->score);
        $this->assertSame(1, $result->maxScore);
    }

    public function test_validate_delegates_to_the_shared_content_validator_scoped_to_this_exam(): void
    {
        $activity = $this->makeActivity();

        $issues = $activity->validate();

        foreach ($issues as $issue) {
            $this->assertStringStartsWith('exams/fundamente/', $issue->file);
        }
    }

    public function test_serialize_returns_the_exam_definition_and_markdown_unchanged(): void
    {
        $activity = $this->makeActivity();

        $files = $activity->serialize();

        $this->assertCount(2, $files);
        $this->assertSame('exams/fundamente/exam.yml', $files[0]['path']);
        $this->assertStringContainsString('track: fundamente', $files[0]['contents']);
    }

    public function test_serialize_with_a_settings_draft_regenerates_only_the_named_fields(): void
    {
        $activity = $this->makeActivity();

        $files = $activity->serialize([
            'pass_percent' => 90,
            'draw' => 1,
            'duration_minutes' => 5,
            'shuffle' => true,
            'title' => 'Neuer Titel',
            'intro' => 'Neuer Intro',
        ]);

        $this->assertStringContainsString('pass_percent: 90', $files[0]['contents']);
        $this->assertStringContainsString('draw: 1', $files[0]['contents']);
        $this->assertStringContainsString('id: f01', $files[0]['contents']);
        $frontMatter = FrontMatter::parse($files[1]['contents']);
        $this->assertSame('Neuer Titel', $frontMatter['attributes']['title']);
        $this->assertStringContainsString('### f01 — Frage?', $files[1]['contents']);
    }

    public function test_validate_with_an_invalid_settings_draft_reports_the_issue(): void
    {
        $activity = $this->makeActivity();

        $issues = $activity->validate(['pass_percent' => 30]);

        $messages = array_map(fn ($issue) => (string) $issue, $issues);
        $this->assertTrue(
            (bool) array_filter($messages, fn (string $m) => str_contains($m, 'pass_percent muss zwischen 50 und 100 liegen')),
        );
    }

    public function test_validate_without_a_draft_is_unaffected_by_this_change(): void
    {
        $activity = $this->makeActivity();

        $issues = $activity->validate();

        foreach ($issues as $issue) {
            $this->assertStringStartsWith('exams/fundamente/', $issue->file);
        }
    }

    public function test_serialize_with_a_questions_draft_replaces_the_entire_pool(): void
    {
        $activity = $this->makeActivity();

        $files = $activity->serialize(['questions' => [
            [
                'id' => 'f01', 'is_ref' => false, 'type' => 'truefalse', 'question' => 'Neue Aussage.',
                'explanation' => 'Neue Erklärung.', 'answer' => true, 'lesson' => '1.0',
                'review' => [['lesson' => '1.0', 'anchor' => 'intro']], 'difficulty' => 2, 'tags' => [],
            ],
        ]]);

        $parsedMeta = Yaml::parse($files[0]['contents']);
        $this->assertCount(1, $parsedMeta['questions']);
        $this->assertSame('truefalse', $parsedMeta['questions'][0]['type']);
        $this->assertStringContainsString('### f01 — Neue Aussage.', $files[1]['contents']);
        $this->assertStringNotContainsString('Frage?', $files[1]['contents'], 'die alte Frage muss ersetzt sein.');
    }

    public function test_validate_with_a_questions_draft_checks_the_regenerated_pool(): void
    {
        $activity = $this->makeActivity();

        $issues = $activity->validate(['questions' => [
            [
                'id' => 'f01', 'is_ref' => false, 'type' => 'single', 'question' => 'Neue Frage?',
                'options' => ['A'], 'explanation' => 'X.', 'answer' => 5, 'lesson' => '1.0',
                'review' => [['lesson' => '1.0', 'anchor' => 'intro']], 'difficulty' => 1, 'tags' => [],
            ],
        ]]);

        $messages = array_map(fn ($issue) => (string) $issue, $issues);
        $this->assertTrue(
            (bool) array_filter($messages, fn (string $m) => str_contains($m, 'answer-Index liegt ausserhalb')
                || str_contains($m, 'answer')),
        );
    }

    private function makeActivity(): ExamActivity
    {
        return new ExamActivity($this->makeTrack(), new ContentRepository($this->contentDir));
    }

    private function makeTrack(): Track
    {
        return Track::factory()->create(['slug' => 'fundamente']);
    }
}
