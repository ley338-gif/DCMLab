<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Einstiegspunkt fuer Author/Reviewer (ADR 0092): vorher gab es keine
 * einzige Seite, von der aus diese Rollen ihre Editoren finden konnten.
 */
class AuthorPanelControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/de/author')->assertRedirect('/de/login');
    }

    public function test_a_learner_cannot_open_the_panel(): void
    {
        $learner = User::factory()->create();

        $this->actingAs($learner)->get('/de/author')->assertForbidden();
    }

    public function test_an_author_sees_their_assigned_activities_but_no_review_queue_count(): void
    {
        $author = User::factory()->author()->create();
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => '1.0', 'title' => ['de' => 'Fundamente']]);
        $activity->authorUsers()->attach($author);
        // Nicht zugewiesen -- darf in der Antwort nicht auftauchen.
        Activity::factory()->create(['type' => 'lesson', 'key' => '1.1']);

        $this->actingAs($author)
            ->get('/de/author')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Author/Panel')
                ->where('role', 'author')
                ->where('review_queue_count', null)
                ->has('assigned_activities', 1)
                ->where('assigned_activities.0.key', '1.0')
                ->where('assigned_activities.0.title', 'Fundamente')
            );
    }

    public function test_a_reviewer_sees_the_review_queue_count(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);
        ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'review', 'payload' => [],
            'is_current' => false, 'created_by' => $reviewer->id,
        ]);

        $this->actingAs($reviewer)
            ->get('/de/author')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('role', 'reviewer')
                ->where('review_queue_count', 1)
            );
    }

    /**
     * ADR 0098 (CMS-3a): Administrator erweitert Reviewer additiv.
     */
    public function test_an_administrator_sees_the_review_queue_count(): void
    {
        $administrator = User::factory()->administrator()->create();
        $activity = Activity::factory()->create(['type' => 'lesson', 'key' => '1.0']);
        ContentVersion::create([
            'activity_id' => $activity->id, 'status' => 'review', 'payload' => [],
            'is_current' => false, 'created_by' => $administrator->id,
        ]);

        $this->actingAs($administrator)
            ->get('/de/author')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('role', 'administrator')
                ->where('review_queue_count', 1)
            );
    }
}
