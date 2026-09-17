<?php

namespace Tests\Feature;

use App\Models\Lab;
use App\Models\Lesson;
use App\Models\SandboxTemplate;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Einstiegspunkt fuer DCMLab Studio (ADR 0099, CMS-3b) -- additiv neben dem
 * Autoren-Panel, dieselbe Zugangsregel (Gate::authorize('studio.access')).
 */
class StudioControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/de/studio')->assertRedirect('/de/login');
    }

    public function test_a_learner_cannot_open_studio(): void
    {
        $learner = User::factory()->create();

        $this->actingAs($learner)->get('/de/studio')->assertForbidden();
    }

    public function test_an_author_sees_the_dashboard_without_manage_rights(): void
    {
        $author = User::factory()->author()->create();

        $this->actingAs($author)
            ->get('/de/studio')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Studio/Dashboard')
                ->where('role', 'author')
                ->where('can_manage_sandbox_templates', false)
            );
    }

    public function test_a_reviewer_can_manage_sandbox_templates(): void
    {
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->get('/de/studio')
            ->assertInertia(fn ($page) => $page->where('can_manage_sandbox_templates', true));
    }

    public function test_an_administrator_can_manage_sandbox_templates(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->get('/de/studio')
            ->assertInertia(fn ($page) => $page->where('can_manage_sandbox_templates', true));
    }

    public function test_it_reports_the_sandbox_template_count(): void
    {
        SandboxTemplate::factory()->count(3)->create();
        $author = User::factory()->author()->create();

        $this->actingAs($author)
            ->get('/de/studio')
            ->assertInertia(fn ($page) => $page->where('sandbox_template_count', 3));
    }

    public function test_it_reports_the_track_count_and_manage_rights(): void
    {
        Track::factory()->count(2)->create();
        $author = User::factory()->author()->create();
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($author)
            ->get('/de/studio')
            ->assertInertia(fn ($page) => $page
                ->where('track_count', 2)
                ->where('can_manage_tracks', false));

        $this->actingAs($reviewer)
            ->get('/de/studio')
            ->assertInertia(fn ($page) => $page->where('can_manage_tracks', true));
    }

    public function test_it_reports_the_lab_count_and_manage_rights(): void
    {
        Lab::factory()->count(2)->create();
        $author = User::factory()->author()->create();
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($author)
            ->get('/de/studio')
            ->assertInertia(fn ($page) => $page
                ->where('lab_count', 2)
                ->where('can_manage_labs', false));

        $this->actingAs($reviewer)
            ->get('/de/studio')
            ->assertInertia(fn ($page) => $page->where('can_manage_labs', true));
    }

    /**
     * Lesson hat -- anders als Track/Node/Lab -- keine strukturelle Policy
     * (kein store()/archive() in Studio): `can_manage_lessons` ist deshalb
     * fuer jede Nicht-Lernende-Rolle gleich `true`, nicht rollenabhaengig.
     */
    public function test_it_reports_the_lesson_count_and_manage_rights(): void
    {
        Lesson::factory()->count(2)->create();
        $author = User::factory()->author()->create();

        $this->actingAs($author)
            ->get('/de/studio')
            ->assertInertia(fn ($page) => $page
                ->where('lesson_count', 2)
                ->where('can_manage_lessons', true));
    }
}
