<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nutzerverwaltung im Autoren-Panel (ADR 0092): Rollen vergeben und
 * Autor:innen einzelnen Aktivitaeten zuordnen, statt nur direkt in der
 * Datenbank -- gated auf Reviewer, die einzige bestehende Stufe, die dem
 * naheliegt (siehe UserPolicy).
 */
class AuthorUserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_learner_cannot_open_user_management(): void
    {
        $learner = User::factory()->create();

        $this->actingAs($learner)->get('/de/author/users')->assertForbidden();
    }

    public function test_an_author_cannot_open_user_management(): void
    {
        $author = User::factory()->author()->create();

        $this->actingAs($author)->get('/de/author/users')->assertForbidden();
    }

    public function test_a_reviewer_sees_all_users_with_their_role_and_assigned_activities(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $author = User::factory()->author()->create(['name' => 'Ada Autor']);
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);
        $activity->authorUsers()->attach($author);

        $this->actingAs($reviewer)
            ->get('/de/author/users')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Author/Users')
                ->where('users', function ($users) use ($author) {
                    $row = collect($users)->firstWhere('id', $author->id);

                    return $row['role'] === 'author'
                        && $row['name'] === 'Ada Autor'
                        && collect($row['authored_activities'])->pluck('key')->all() === ['1.0'];
                })
            );
    }

    public function test_a_non_reviewer_cannot_change_a_role(): void
    {
        $author = User::factory()->author()->create();
        $target = User::factory()->create();

        $this->actingAs($author)
            ->patch("/de/author/users/{$target->id}", ['role' => 'author'])
            ->assertForbidden();

        $this->assertSame('learner', $target->fresh()->role->value);
    }

    public function test_a_reviewer_can_promote_a_learner_to_author(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $target = User::factory()->create();

        $this->actingAs($reviewer)
            ->patch("/de/author/users/{$target->id}", ['role' => 'author'])
            ->assertRedirect();

        $this->assertSame('author', $target->fresh()->role->value);
    }

    /**
     * ADR 0098 (CMS-3a): Administrator erweitert Reviewer additiv, kann
     * also ebenfalls Rollen vergeben -- auch die neue administrator-Rolle
     * selbst.
     */
    public function test_an_administrator_can_promote_a_learner_to_administrator(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->actingAs($administrator)
            ->patch("/de/author/users/{$target->id}", ['role' => 'administrator'])
            ->assertRedirect();

        $this->assertSame('administrator', $target->fresh()->role->value);
    }

    public function test_an_invalid_role_is_rejected(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $target = User::factory()->create();

        $this->actingAs($reviewer)
            ->patch("/de/author/users/{$target->id}", ['role' => 'admin'])
            ->assertSessionHasErrors('role');
    }

    public function test_a_reviewer_can_assign_and_remove_an_activity_for_an_author(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $author = User::factory()->author()->create();
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);

        $this->actingAs($reviewer)
            ->post("/de/author/users/{$author->id}/activities", ['activity_id' => $activity->id])
            ->assertRedirect();

        $this->assertTrue($author->authoredActivities()->where('activities.id', $activity->id)->exists());

        $this->actingAs($reviewer)
            ->delete("/de/author/users/{$author->id}/activities/{$activity->id}")
            ->assertRedirect();

        $this->assertFalse($author->authoredActivities()->where('activities.id', $activity->id)->exists());
    }

    public function test_assigning_the_same_activity_twice_does_not_duplicate_the_pivot_row(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $author = User::factory()->author()->create();
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);

        $this->actingAs($reviewer)->post("/de/author/users/{$author->id}/activities", ['activity_id' => $activity->id]);
        $this->actingAs($reviewer)->post("/de/author/users/{$author->id}/activities", ['activity_id' => $activity->id]);

        $this->assertSame(1, $author->authoredActivities()->where('activities.id', $activity->id)->count());
    }
}
