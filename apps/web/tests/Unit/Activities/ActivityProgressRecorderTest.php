<?php

namespace Tests\Unit\Activities;

use App\Activities\ActivityProgressRecorder;
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
}
