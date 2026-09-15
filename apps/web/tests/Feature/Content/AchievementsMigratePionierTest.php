<?php

namespace Tests\Feature\Content;

use App\Models\Achievement;
use App\Models\AchievementUnlock;
use App\Models\Activity;
use App\Models\Node;
use App\Models\Track;
use App\Models\TrackBadge;
use App\Models\User;
use Database\Seeders\AchievementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * ADR 0090b: Backfill der alten "Pionier"-Tabellen (achievements/
 * track_badges) nach achievement_unlocks, unter den neuen deklarativen
 * Slugs "trailblazer"/"track-<slug>".
 */
class AchievementsMigratePionierTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fails_clearly_without_the_achievement_definitions_seeded(): void
    {
        $exitCode = Artisan::call('achievements:migrate-pionier');

        $this->assertSame(1, $exitCode);
    }

    public function test_it_migrates_first_blood_and_track_badge_rows_preserving_the_original_winner_and_date(): void
    {
        $this->seed(AchievementSeeder::class);

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $node = Node::factory()->create(['slug' => 'silent-ct']);
        Activity::factory()->create(['type' => 'node', 'key' => 'silent-ct']);

        $track = Track::factory()->create(['slug' => 'fundamente']);
        $awardedAt = now()->subDays(3);

        Achievement::create([
            'user_id' => $firstUser->id, 'node_id' => $node->id, 'type' => 'first_blood', 'awarded_at' => $awardedAt,
        ]);
        TrackBadge::create([
            'user_id' => $secondUser->id, 'track_id' => $track->id, 'awarded_at' => $awardedAt,
        ]);

        $exitCode = Artisan::call('achievements:migrate-pionier');

        $this->assertSame(0, $exitCode);

        $trailblazerUnlock = AchievementUnlock::query()->whereRelation('definition', 'slug', 'trailblazer')->firstOrFail();
        $this->assertSame($firstUser->id, $trailblazerUnlock->user_id);
        $this->assertSame($awardedAt->toDateTimeString(), $trailblazerUnlock->unlocked_at->toDateTimeString());

        $trackUnlock = AchievementUnlock::query()->whereRelation('definition', 'slug', 'track-fundamente')->firstOrFail();
        $this->assertSame($secondUser->id, $trackUnlock->user_id);
        $this->assertSame($awardedAt->toDateTimeString(), $trackUnlock->unlocked_at->toDateTimeString());
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(AchievementSeeder::class);
        $user = User::factory()->create();
        $node = Node::factory()->create(['slug' => 'silent-ct']);
        Activity::factory()->create(['type' => 'node', 'key' => 'silent-ct']);
        Achievement::create(['user_id' => $user->id, 'node_id' => $node->id, 'type' => 'first_blood', 'awarded_at' => now()]);

        Artisan::call('achievements:migrate-pionier');
        $firstCount = AchievementUnlock::count();
        Artisan::call('achievements:migrate-pionier');

        $this->assertSame($firstCount, AchievementUnlock::count());
        $this->assertSame(1, AchievementUnlock::count());
    }

    public function test_it_skips_a_first_blood_row_whose_node_has_no_matching_activity_yet(): void
    {
        $this->seed(AchievementSeeder::class);
        $user = User::factory()->create();
        $node = Node::factory()->create(['slug' => 'no-activity-yet']);
        Achievement::create(['user_id' => $user->id, 'node_id' => $node->id, 'type' => 'first_blood', 'awarded_at' => now()]);

        $exitCode = Artisan::call('achievements:migrate-pionier');

        $this->assertSame(0, $exitCode);
        $this->assertSame(0, AchievementUnlock::count());
    }
}
