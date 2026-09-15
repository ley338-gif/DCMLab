<?php

namespace Tests\Unit\Services;

use App\Models\ExamAttempt;
use App\Models\Node;
use App\Models\NodeAttempt;
use App\Models\Track;
use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rang, Skill-Radar und Punktequellen (Abschnitt 7, P8). Die konkreten
 * Punktschwellen sind eine umkehrbare Balance-Entscheidung, siehe ADR 0009.
 * Die Vergabe der "Trailblazer"- und Track-Badge-Achievements selbst ist
 * seit ADR 0090b keine ProfileService-Zustaendigkeit mehr, siehe
 * AchievementUnlockEvaluatorTest.
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

    public function test_total_points_counts_each_passed_track_once_regardless_of_repeat_attempts(): void
    {
        $user = User::factory()->create();
        $track = Track::factory()->create();

        $this->passExam($user, $track, passed: false);
        $this->passExam($user, $track, passed: true);
        $this->passExam($user, $track, passed: true);

        $this->assertSame(ProfileService::TRACK_PASS_POINTS, (new ProfileService)->totalPoints($user));
    }

    public function test_total_points_adds_node_points_and_track_points(): void
    {
        $user = User::factory()->create();
        $node = Node::factory()->create(['skills' => ['netzwerk'], 'points' => 30]);
        $track = Track::factory()->create();
        $this->solve($user, $node, 30);
        $this->passExam($user, $track, passed: true);

        $this->assertSame(30 + ProfileService::TRACK_PASS_POINTS, (new ProfileService)->totalPoints($user));
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

    private function passExam(User $user, Track $track, bool $passed): void
    {
        ExamAttempt::create([
            'user_id' => $user->id,
            'track_id' => $track->id,
            'status' => 'completed',
            'question_ids' => ['f01'],
            'current_index' => 1,
            'answers' => ['f01' => ['submitted' => 0, 'correct' => $passed]],
            'score_correct' => $passed ? 1 : 0,
            'score_total' => 1,
            'passed' => $passed,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
        ]);
    }
}
