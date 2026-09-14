<?php

namespace Tests\Unit\Activities;

use App\Activities\ExamActivity;
use App\Content\ContentRepository;
use App\Models\ExamAttempt;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
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

    public function test_serialize_returns_the_exam_definition_and_markdown_unchanged(): void
    {
        $activity = $this->makeActivity();

        $files = $activity->serialize();

        $this->assertCount(2, $files);
        $this->assertSame('exams/fundamente/exam.yml', $files[0]['path']);
        $this->assertStringContainsString('track: fundamente', $files[0]['contents']);
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
