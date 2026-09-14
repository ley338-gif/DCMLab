<?php

namespace Tests\Unit\Services;

use App\Models\Achievement;
use App\Models\Node;
use App\Models\NodeAttempt;
use App\Models\Track;
use App\Models\TrackBadge;
use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rang, Skill-Radar und First Blood (Abschnitt 7, P8). Die konkreten
 * Punktschwellen sind eine umkehrbare Balance-Entscheidung, siehe ADR 0009.
 */
class ProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_for_creates_a_profile_with_a_unique_slug(): void
    {
        $user = User::factory()->create();

        $profile = (new ProfileService)->profileFor($user);

        $this->assertSame($user->id, $profile->user_id);
        $this->assertNotEmpty($profile->public_slug);
        $this->assertSame('novice', $profile->rank);
        $this->assertSame(
            ['netzwerk' => 0, 'datenmodell' => 0, 'bildgebung' => 0, 'integration' => 0, 'security' => 0],
            $profile->skill_vector,
        );
    }

    public function test_profile_for_is_idempotent(): void
    {
        $user = User::factory()->create();
        $service = new ProfileService;

        $first = $service->profileFor($user);
        $second = $service->profileFor($user);

        $this->assertSame($first->id, $second->id);
    }

    public function test_recompute_after_solve_sums_points_per_skill_and_assigns_a_rank(): void
    {
        $user = User::factory()->create();
        $netzwerkNode = Node::factory()->create(['skills' => ['netzwerk'], 'points' => 30]);
        $securityNode = Node::factory()->create(['skills' => ['security'], 'points' => 25]);

        $this->solve($user, $netzwerkNode, 30);
        $this->solve($user, $securityNode, 25);

        (new ProfileService)->recomputeAfterSolve($user, $securityNode);

        $profile = $user->profile()->firstOrFail();
        $this->assertSame(55, $profile->points);
        $this->assertSame('operator', $profile->rank);
        $this->assertSame(30, $profile->skill_vector['netzwerk']);
        $this->assertSame(25, $profile->skill_vector['security']);
    }

    public function test_recompute_after_solve_ignores_skills_outside_the_five_categories(): void
    {
        $user = User::factory()->create();
        $node = Node::factory()->create(['skills' => ['unbekannt'], 'points' => 10]);
        $this->solve($user, $node, 10);

        (new ProfileService)->recomputeAfterSolve($user, $node);

        $profile = $user->profile()->firstOrFail();
        $this->assertSame(10, $profile->points);
        $this->assertSame(0, array_sum($profile->skill_vector));
    }

    public function test_first_blood_is_awarded_once_per_node(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $node = Node::factory()->create(['skills' => ['netzwerk'], 'points' => 10]);

        $this->solve($firstUser, $node, 10);
        $this->solve($secondUser, $node, 10);

        $service = new ProfileService;
        $service->recomputeAfterSolve($firstUser, $node);
        $service->recomputeAfterSolve($secondUser, $node);

        $this->assertDatabaseHas('achievements', [
            'user_id' => $firstUser->id, 'node_id' => $node->id, 'type' => 'first_blood',
        ]);
        $this->assertDatabaseMissing('achievements', [
            'user_id' => $secondUser->id, 'node_id' => $node->id, 'type' => 'first_blood',
        ]);
        $this->assertSame(1, Achievement::where('node_id', $node->id)->count());
    }

    /**
     * ADR 0070: die gemeinsame Lesestelle fuer Dashboard und oeffentliches
     * Profil -- fasst first_blood-Achievements (pro Node) und TrackBadges
     * (pro Nutzer) zusammen, absteigend nach awarded_at sortiert.
     */
    public function test_achievements_for_merges_first_bloods_and_track_badges_sorted_by_date(): void
    {
        $user = User::factory()->create();
        $node = Node::factory()->create(['skills' => ['netzwerk'], 'points' => 10]);
        $track = Track::factory()->create(['title_key' => 'track.fundamente.title']);

        Achievement::create([
            'user_id' => $user->id,
            'node_id' => $node->id,
            'type' => 'first_blood',
            'awarded_at' => now()->subDay(),
        ]);
        TrackBadge::create([
            'user_id' => $user->id,
            'track_id' => $track->id,
            'awarded_at' => now(),
        ]);

        $achievements = (new ProfileService)->achievementsFor($user);

        $this->assertCount(2, $achievements);
        $this->assertSame('track_passed', $achievements[0]['kind']);
        $this->assertSame('track.fundamente.title', $achievements[0]['track_title_key']);
        $this->assertSame('first_blood', $achievements[1]['kind']);
        $this->assertSame($node->title['de'], $achievements[1]['node_title']);
    }

    public function test_leaderboard_only_returns_opted_in_profiles_sorted_by_points(): void
    {
        $service = new ProfileService;

        $optedIn = User::factory()->create();
        $service->profileFor($optedIn)->forceFill(['leaderboard_opt_in' => true, 'points' => 20])->save();

        $higher = User::factory()->create();
        $service->profileFor($higher)->forceFill(['leaderboard_opt_in' => true, 'points' => 80])->save();

        $optedOut = User::factory()->create();
        $service->profileFor($optedOut)->forceFill(['leaderboard_opt_in' => false, 'points' => 100])->save();

        $leaderboard = $service->leaderboard();

        $this->assertCount(2, $leaderboard);
        $this->assertSame($higher->id, $leaderboard->first()->user_id);
        $this->assertSame($optedIn->id, $leaderboard->last()->user_id);
    }

    private function solve(User $user, Node $node, int $points): void
    {
        NodeAttempt::create([
            'user_id' => $user->id,
            'node_id' => $node->id,
            'engine_session_id' => 'sess-'.$node->id,
            'status' => 'solved',
            'points' => $points,
            'started_at' => now(),
            'flag_submitted_at' => now(),
        ]);
    }
}
