<?php

namespace Tests\Feature;

use App\Content\ContentRepository;
use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\Node;
use App\Models\NodeAttempt;
use App\Models\Track;
use App\Models\User;
use App\Services\ExamAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ADR 0076 (Vorstufe zu W4): die realen Schreibwege (Node loesen, Lektion
 * abschliessen, Pruefung abschliessen) fuellen ab jetzt zusaetzlich
 * `activity_progress` -- additiv, ohne die bestehenden Tabellen zu
 * veraendern. Existiert (noch) kein `activities`-Eintrag fuer die
 * betroffene Lektion/Node/Pruefung (z. B. weil `content:sync` nie lief),
 * bleibt das stillschweigend wirkungslos, siehe
 * `ActivityProgressRecorderTest` fuer den Nachweis dieses Falls.
 */
class ActivityProgressDualWriteTest extends TestCase
{
    use RefreshDatabase;

    private string $contentDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->contentDir = storage_path('framework/testing/dual-write-'.Str::random(12));
        $this->app->instance(ContentRepository::class, new ContentRepository($this->contentDir));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->contentDir);

        parent::tearDown();
    }

    public function test_solving_a_node_writes_its_activity_progress(): void
    {
        $node = Node::factory()->create(['slug' => 'test-node', 'points' => 10, 'skills' => ['netzwerk']]);
        Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        $user = User::factory()->create();

        NodeAttempt::create([
            'user_id' => $user->id,
            'node_id' => $node->id,
            'engine_session_id' => 'existing-session',
            'status' => 'started',
            'started_at' => now(),
        ]);

        Http::fake([
            '*/v1/sessions/existing-session/flag' => Http::response(['correct' => true, 'points' => 10]),
            '*/v1/sessions/existing-session/state' => Http::response([
                'hosts' => [], 'config' => [], 'hints_used' => [], 'solved' => true, 'points' => 10,
            ]),
        ]);

        $this->actingAs($user)->postJson('/de/nodes/test-node/flag', ['value' => 'Testflag'])->assertOk();

        $activity = Activity::query()->where('type', 'node')->where('key', 'test-node')->firstOrFail();
        $progress = ActivityProgress::where('user_id', $user->id)->where('activity_id', $activity->id)->firstOrFail();

        $this->assertTrue($progress->completed);
        $this->assertSame(10, $progress->score);
        $this->assertSame(10, $progress->max_score);
        $this->assertSame(['netzwerk'], $progress->skills);
    }

    public function test_completing_a_lesson_writes_its_activity_progress(): void
    {
        $track = Track::factory()->create();
        $lesson = Lesson::factory()->create(['lesson_id' => '1.1', 'track_id' => $track->id, 'order' => 0]);
        Activity::factory()->create(['type' => 'lesson', 'key' => '1.1']);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/de/lessons/1.1/complete')->assertRedirect();

        $activity = Activity::query()->where('type', 'lesson')->where('key', '1.1')->firstOrFail();
        $progress = ActivityProgress::where('user_id', $user->id)->where('activity_id', $activity->id)->firstOrFail();

        $this->assertTrue($progress->completed);
        $this->assertNotNull($progress->completed_at);
    }

    public function test_reopening_a_lesson_updates_its_activity_progress_to_not_completed(): void
    {
        $track = Track::factory()->create();
        Lesson::factory()->create(['lesson_id' => '1.1', 'track_id' => $track->id, 'order' => 0]);
        Activity::factory()->create(['type' => 'lesson', 'key' => '1.1']);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/de/lessons/1.1/complete');
        $this->actingAs($user)->post('/de/lessons/1.1/reopen')->assertRedirect();

        $activity = Activity::query()->where('type', 'lesson')->where('key', '1.1')->firstOrFail();
        $progress = ActivityProgress::where('user_id', $user->id)->where('activity_id', $activity->id)->firstOrFail();

        $this->assertFalse($progress->completed);
    }

    public function test_completing_an_exam_writes_its_activity_progress(): void
    {
        $track = Track::factory()->create(['slug' => 'fundamente']);
        Activity::factory()->create(['type' => 'exam', 'key' => 'fundamente']);
        $user = User::factory()->create();

        File::ensureDirectoryExists($this->contentDir.'/exams/fundamente');
        File::put($this->contentDir.'/exams/fundamente/exam.yml', <<<'YAML'
        track: fundamente
        title_key: exam.fundamente.title
        pass_percent: 50
        draw: 1
        duration_minutes: 5
        shuffle: false
        questions:
          - id: f01
            type: single
            answer: 0
            lesson: "1.0"
            tags: [netzwerk]
        YAML);
        File::put($this->contentDir.'/exams/fundamente/de.md', "---\ntitle: Test\n---\n\n### f01 — Frage?\n\n1. Ja\n2. Nein\n");

        $attempt = ExamAttempt::create([
            'user_id' => $user->id,
            'track_id' => $track->id,
            'status' => 'in_progress',
            'question_ids' => ['f01'],
            'current_index' => 1,
            'answers' => ['f01' => ['submitted' => 0, 'correct' => true]],
            'started_at' => now(),
        ]);

        app(ExamAttemptService::class)->complete($attempt);

        $activity = Activity::query()->where('type', 'exam')->where('key', 'fundamente')->firstOrFail();
        $progress = ActivityProgress::where('user_id', $user->id)->where('activity_id', $activity->id)->firstOrFail();

        $this->assertTrue($progress->completed);
        $this->assertSame(1, $progress->score);
        $this->assertSame(1, $progress->max_score);
    }
}
