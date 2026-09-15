<?php

namespace Tests\Unit\Policies;

use App\Models\Activity;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADR 0071, W3-DoD: "Ein Autor kann fremden Content nicht bearbeiten."
 */
class ActivityPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reviewer_can_update_any_activity(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $activity = Activity::factory()->create();

        $this->assertTrue($reviewer->can('update', $activity));
    }

    public function test_an_author_can_update_an_activity_they_are_assigned_to(): void
    {
        $author = User::factory()->author()->create();
        $activity = Activity::factory()->create();
        $activity->authorUsers()->attach($author);

        $this->assertTrue($author->can('update', $activity));
    }

    public function test_an_author_cannot_update_someone_elses_activity(): void
    {
        $author = User::factory()->author()->create();
        $otherAuthor = User::factory()->author()->create();
        $activity = Activity::factory()->create();
        $activity->authorUsers()->attach($otherAuthor);

        $this->assertFalse($author->can('update', $activity));
    }

    public function test_a_learner_cannot_update_any_activity(): void
    {
        $learner = User::factory()->create();
        $activity = Activity::factory()->create();
        $activity->authorUsers()->attach($learner);

        $this->assertSame(UserRole::Learner, $learner->role);
        $this->assertFalse($learner->can('update', $activity));
    }

    public function test_only_a_reviewer_can_publish(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $author = User::factory()->author()->create();
        $activity = Activity::factory()->create();
        $activity->authorUsers()->attach($author);

        $this->assertTrue($reviewer->can('publish', $activity));
        $this->assertFalse($author->can('publish', $activity));
    }
}
