<?php

namespace Tests\Feature\Content;

use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Node;
use App\Models\NodeAttempt;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * ADR 0076: activity:backfill-progress fuellt activity_progress fuer
 * Bestandsnutzer, die vor dieser Aenderung schon gespielt haben.
 */
class ActivityBackfillProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_backfills_progress_from_all_three_historical_tables(): void
    {
        $user = User::factory()->create();
        $track = Track::factory()->create(['slug' => 'fundamente']);
        $lesson = Lesson::factory()->create(['lesson_id' => '1.0', 'track_id' => $track->id]);
        $node = Node::factory()->create(['slug' => 'silent-ct', 'points' => 10, 'skills' => ['netzwerk']]);

        Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);
        Activity::factory()->create(['type' => 'node', 'key' => 'silent-ct']);
        Activity::factory()->create(['type' => 'exam', 'key' => 'fundamente']);

        LessonProgress::create([
            'user_id' => $user->id, 'lesson_id' => $lesson->id, 'status' => 'completed',
            'started_at' => now(), 'completed_at' => now(),
        ]);
        NodeAttempt::create([
            'user_id' => $user->id, 'node_id' => $node->id, 'engine_session_id' => 'session',
            'status' => 'solved', 'points' => 10, 'started_at' => now(), 'flag_submitted_at' => now(),
        ]);
        ExamAttempt::create([
            'user_id' => $user->id, 'track_id' => $track->id, 'status' => 'completed',
            'question_ids' => ['f01'], 'current_index' => 1,
            'answers' => ['f01' => ['submitted' => 0, 'correct' => true]],
            'score_correct' => 1, 'score_total' => 1, 'passed' => true,
            'started_at' => now(), 'completed_at' => now(),
        ]);

        $exitCode = Artisan::call('activity:backfill-progress');

        $this->assertSame(0, $exitCode);
        $this->assertSame(3, ActivityProgress::where('user_id', $user->id)->where('completed', true)->count());
    }

    public function test_it_is_idempotent(): void
    {
        $user = User::factory()->create();
        $track = Track::factory()->create(['slug' => 'fundamente']);
        $node = Node::factory()->create(['slug' => 'silent-ct', 'points' => 10]);
        Activity::factory()->create(['type' => 'node', 'key' => 'silent-ct']);

        NodeAttempt::create([
            'user_id' => $user->id, 'node_id' => $node->id, 'engine_session_id' => 'session',
            'status' => 'solved', 'points' => 10, 'started_at' => now(), 'flag_submitted_at' => now(),
        ]);

        Artisan::call('activity:backfill-progress');
        $firstCount = ActivityProgress::count();
        Artisan::call('activity:backfill-progress');

        $this->assertSame($firstCount, ActivityProgress::count());
    }
}
