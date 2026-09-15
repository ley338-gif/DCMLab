<?php

namespace Tests\Unit\Achievements;

use App\Achievements\AchievementUnlockEvaluator;
use App\Activities\ActivityResult;
use App\Models\AchievementDefinition;
use App\Models\AchievementUnlock;
use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADR 0071/0077, W4-DoD: "Ein Achievement, das nur als Dateneintrag
 * entsteht, wird beim Abschluss der zugeordneten Aktivität vergeben, ohne
 * dass dafür eine Zeile Controller-Code entstanden ist." Diese Tests rufen
 * ausschliesslich den Evaluator direkt auf -- kein Controller beteiligt.
 */
class AchievementUnlockEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_nothing_when_the_result_is_not_completed(): void
    {
        $this->makeDefinition('never', ['type' => 'activity_completed', 'activity_type' => 'node']);
        $activity = Activity::factory()->create(['type' => 'node', 'key' => 'foo']);
        $user = User::factory()->create();

        $unlocked = $this->evaluator()->evaluate($activity, $user, $this->activityResult(completed: false));

        $this->assertSame([], $unlocked);
        $this->assertSame(0, AchievementUnlock::count());
    }

    public function test_activity_completed_matches_only_the_declared_key(): void
    {
        $this->makeDefinition('echo-heard', ['type' => 'activity_completed', 'activity_type' => 'node', 'key' => 'silent-ct']);
        $matching = Activity::factory()->create(['type' => 'node', 'key' => 'silent-ct']);
        $other = Activity::factory()->create(['type' => 'node', 'key' => 'wrong-door']);
        $user = User::factory()->create();

        $unlockedForMatch = $this->evaluator()->evaluate($matching, $user, $this->activityResult(completed: true));
        $unlockedForOther = $this->evaluator()->evaluate($other, User::factory()->create(), $this->activityResult(completed: true));

        $this->assertSame(['echo-heard'], array_column($unlockedForMatch, 'slug'));
        $this->assertSame([], $unlockedForOther);
    }

    public function test_activity_completed_without_a_key_matches_any_activity_of_that_type(): void
    {
        $this->makeDefinition('any-node', ['type' => 'activity_completed', 'activity_type' => 'node']);
        $activity = Activity::factory()->create(['type' => 'node', 'key' => 'whatever']);
        $user = User::factory()->create();

        $unlocked = $this->evaluator()->evaluate($activity, $user, $this->activityResult(completed: true));

        $this->assertSame(['any-node'], array_column($unlocked, 'slug'));
    }

    public function test_track_passed_requires_the_matching_track_key(): void
    {
        $this->makeDefinition('fundamente-durch', ['type' => 'track_passed', 'track' => 'fundamente']);
        $matching = Activity::factory()->create(['type' => 'exam', 'key' => 'fundamente']);
        $other = Activity::factory()->create(['type' => 'exam', 'key' => 'bild']);
        $user = User::factory()->create();

        $unlocked = $this->evaluator()->evaluate($matching, $user, $this->activityResult(completed: true));
        $unlockedForOther = $this->evaluator()->evaluate($other, User::factory()->create(), $this->activityResult(completed: true));

        $this->assertSame(['fundamente-durch'], array_column($unlocked, 'slug'));
        $this->assertSame([], $unlockedForOther);
    }

    public function test_first_solve_fires_only_on_the_users_first_ever_completion_of_that_type(): void
    {
        $this->makeDefinition('first-blood', ['type' => 'first_solve', 'activity_type' => 'node']);
        $user = User::factory()->create();
        $firstNode = Activity::factory()->create(['type' => 'node', 'key' => 'first']);
        $secondNode = Activity::factory()->create(['type' => 'node', 'key' => 'second']);

        ActivityProgress::create(['user_id' => $user->id, 'activity_id' => $firstNode->id, 'completed' => true]);
        $firstUnlock = $this->evaluator()->evaluate($firstNode, $user, $this->activityResult(completed: true));

        ActivityProgress::create(['user_id' => $user->id, 'activity_id' => $secondNode->id, 'completed' => true]);
        $secondUnlock = $this->evaluator()->evaluate($secondNode, $user, $this->activityResult(completed: true));

        $this->assertSame(['first-blood'], array_column($firstUnlock, 'slug'));
        $this->assertSame([], $secondUnlock);
    }

    public function test_global_scope_lets_each_activity_have_its_own_winner_but_only_one_each(): void
    {
        $this->makeDefinition('node-pioneer', ['type' => 'activity_completed', 'activity_type' => 'node'], scope: 'global');
        $nodeA = Activity::factory()->create(['type' => 'node', 'key' => 'node-a']);
        $nodeB = Activity::factory()->create(['type' => 'node', 'key' => 'node-b']);
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $aliceOnA = $this->evaluator()->evaluate($nodeA, $alice, $this->activityResult(completed: true));
        $bobOnA = $this->evaluator()->evaluate($nodeA, $bob, $this->activityResult(completed: true));
        $bobOnB = $this->evaluator()->evaluate($nodeB, $bob, $this->activityResult(completed: true));

        $this->assertSame(['node-pioneer'], array_column($aliceOnA, 'slug'));
        $this->assertSame([], $bobOnA, 'Bob darf node-a nicht mehr gewinnen, Alice war zuerst.');
        $this->assertSame(['node-pioneer'], array_column($bobOnB, 'slug'), 'node-b hat noch keinen Gewinner.');

        $this->assertSame(2, AchievementUnlock::where('achievement_definition_id', AchievementDefinition::where('slug', 'node-pioneer')->value('id'))->count());
    }

    public function test_an_unknown_unlock_when_type_matches_nothing(): void
    {
        $this->makeDefinition('mystery', ['type' => 'does_not_exist']);
        $activity = Activity::factory()->create(['type' => 'node', 'key' => 'foo']);

        $unlocked = $this->evaluator()->evaluate($activity, User::factory()->create(), $this->activityResult(completed: true));

        $this->assertSame([], $unlocked);
    }

    private function evaluator(): AchievementUnlockEvaluator
    {
        return app(AchievementUnlockEvaluator::class);
    }

    private function activityResult(bool $completed): ActivityResult
    {
        return new ActivityResult(completed: $completed);
    }

    /**
     * @param  array<string, mixed>  $unlockWhen
     */
    private function makeDefinition(string $slug, array $unlockWhen, string $scope = 'personal'): AchievementDefinition
    {
        return AchievementDefinition::create([
            'slug' => $slug,
            'name' => $slug,
            'description' => $slug,
            'image' => "{$slug}.png",
            'category' => 'test',
            'scope' => $scope,
            'unlock_when' => $unlockWhen,
            'points' => 0,
            'is_hidden' => false,
            'sort_order' => 0,
        ]);
    }
}
