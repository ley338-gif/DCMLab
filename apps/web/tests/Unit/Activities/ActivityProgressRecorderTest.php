<?php

namespace Tests\Unit\Activities;

use App\Activities\ActivityProgressRecorder;
use App\Models\AchievementDefinition;
use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\Node;
use App\Models\NodeAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityProgressRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_nothing_when_no_matching_activity_exists(): void
    {
        $user = User::factory()->create();

        app(ActivityProgressRecorder::class)->record('node', 'does-not-exist', $user);

        $this->assertSame(0, ActivityProgress::count());
    }

    public function test_it_does_nothing_when_the_activity_has_no_result_yet(): void
    {
        Node::factory()->create(['slug' => 'test-node']);
        Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        $user = User::factory()->create();

        app(ActivityProgressRecorder::class)->record('node', 'test-node', $user);

        $this->assertSame(0, ActivityProgress::count());
    }

    public function test_it_upserts_rather_than_duplicates_on_repeated_calls(): void
    {
        $node = Node::factory()->create(['slug' => 'test-node', 'points' => 10]);
        Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        $user = User::factory()->create();
        NodeAttempt::create([
            'user_id' => $user->id,
            'node_id' => $node->id,
            'engine_session_id' => 'session',
            'status' => 'solved',
            'points' => 10,
            'started_at' => now(),
            'flag_submitted_at' => now(),
        ]);

        $recorder = app(ActivityProgressRecorder::class);
        $recorder->record('node', 'test-node', $user);
        $recorder->record('node', 'test-node', $user);

        $this->assertSame(1, ActivityProgress::count());
    }

    /**
     * CMS-8d-Korrektur: record() bleibt fuer bestehende Aufrufer
     * unveraendert, ist intern aber nur noch persistProgress() +
     * evaluateAchievements() nacheinander -- ein Aufrufer mit eigener
     * DB::transaction() (z. B. LabController::exec()) ruft beide stattdessen
     * einzeln auf, siehe ActivityProgressContext-Klassendoc.
     */
    public function test_persist_progress_writes_activity_progress_and_returns_a_context(): void
    {
        $node = Node::factory()->create(['slug' => 'test-node', 'points' => 10]);
        Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        $user = User::factory()->create();
        NodeAttempt::create([
            'user_id' => $user->id,
            'node_id' => $node->id,
            'engine_session_id' => 'session',
            'status' => 'solved',
            'points' => 10,
            'started_at' => now(),
            'flag_submitted_at' => now(),
        ]);

        $context = app(ActivityProgressRecorder::class)->persistProgress('node', 'test-node', $user);

        $this->assertNotNull($context);
        $this->assertSame('node', $context->activity->type);
        $this->assertSame('test-node', $context->activity->key);
        $this->assertTrue($context->result->completed);
        $this->assertSame(1, ActivityProgress::count());
    }

    public function test_persist_progress_returns_null_when_no_matching_activity_exists(): void
    {
        $user = User::factory()->create();

        $context = app(ActivityProgressRecorder::class)->persistProgress('node', 'does-not-exist', $user);

        $this->assertNull($context);
    }

    public function test_persist_progress_returns_null_when_the_activity_has_no_result_yet(): void
    {
        Node::factory()->create(['slug' => 'test-node']);
        Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        $user = User::factory()->create();

        $context = app(ActivityProgressRecorder::class)->persistProgress('node', 'test-node', $user);

        $this->assertNull($context);
        $this->assertSame(0, ActivityProgress::count());
    }

    public function test_evaluate_achievements_unlocks_a_matching_achievement_from_a_persisted_context(): void
    {
        $node = Node::factory()->create(['slug' => 'test-node', 'points' => 10]);
        Activity::factory()->create(['type' => 'node', 'key' => 'test-node']);
        AchievementDefinition::create([
            'slug' => 'first-node', 'name' => 'first-node', 'description' => 'test',
            'image' => 'first-node.png', 'category' => 'test', 'scope' => 'personal',
            'unlock_when' => ['type' => 'activity_completed', 'activity_type' => 'node'],
            'points' => 0, 'is_hidden' => false, 'sort_order' => 0,
        ]);
        $user = User::factory()->create();
        NodeAttempt::create([
            'user_id' => $user->id,
            'node_id' => $node->id,
            'engine_session_id' => 'session',
            'status' => 'solved',
            'points' => 10,
            'started_at' => now(),
            'flag_submitted_at' => now(),
        ]);

        $recorder = app(ActivityProgressRecorder::class);
        $context = $recorder->persistProgress('node', 'test-node', $user);

        $unlocked = $recorder->evaluateAchievements($context);

        $this->assertSame(['first-node'], array_column($unlocked, 'slug'));
    }
}
