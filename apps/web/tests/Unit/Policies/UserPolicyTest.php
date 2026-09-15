<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADR 0092/0098: Nutzerverwaltung ist Reviewer und Administrator
 * gleichermassen erlaubt (additiv, siehe UserRole-Klassendoc).
 */
class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reviewer_can_view_and_update_users(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $target = User::factory()->create();

        $this->assertTrue($reviewer->can('viewAny', User::class));
        $this->assertTrue($reviewer->can('update', $target));
    }

    public function test_an_administrator_can_view_and_update_users(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->assertTrue($administrator->can('viewAny', User::class));
        $this->assertTrue($administrator->can('update', $target));
    }

    public function test_an_author_cannot_view_or_update_users(): void
    {
        $author = User::factory()->author()->create();
        $target = User::factory()->create();

        $this->assertFalse($author->can('viewAny', User::class));
        $this->assertFalse($author->can('update', $target));
    }

    public function test_a_learner_cannot_view_or_update_users(): void
    {
        $learner = User::factory()->create();
        $target = User::factory()->create();

        $this->assertFalse($learner->can('viewAny', User::class));
        $this->assertFalse($learner->can('update', $target));
    }
}
