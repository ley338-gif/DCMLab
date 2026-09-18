<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * IA-Luecke aus dem Terminologie-Audit geschlossen: Herausforderungen
 * hatten mit /de/nodes bereits eine eigene Uebersicht, Labs nicht --
 * `/de/labs` ist wie `/de/nodes` oeffentlich sichtbar (siehe
 * NodeControllerTest), ein Klick auf ein Lab fuehrt Gaeste ueber die
 * bestehende auth-Middleware auf labs/{lab} zum Login.
 */
class LabsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_view_the_labs_index(): void
    {
        Lab::factory()->create(['slug' => 'c-echo-connectivity', 'title' => ['de' => 'C-ECHO Connectivity Lab']]);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);

        $this->get('/de/labs')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Labs/Index')
                ->where('labs', fn (Collection $labs) => $labs->firstWhere('slug', 'c-echo-connectivity')['status'] === 'not_started'),
            );
    }

    public function test_only_published_labs_are_shown(): void
    {
        Lab::factory()->create(['slug' => 'published-lab', 'status' => 'published']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'published-lab']);
        Lab::factory()->create(['slug' => 'draft-lab', 'status' => 'draft']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'draft-lab']);

        $this->get('/de/labs')->assertInertia(fn ($page) => $page
            ->component('Labs/Index')
            ->where('labs', fn (Collection $labs) => $labs->pluck('slug')->all() === ['published-lab']),
        );
    }

    public function test_index_reflects_the_authenticated_users_attempt_status(): void
    {
        Lab::factory()->create(['slug' => 'started-lab']);
        $startedActivity = Activity::factory()->create(['type' => 'lab', 'key' => 'started-lab']);
        Lab::factory()->create(['slug' => 'solved-lab']);
        $solvedActivity = Activity::factory()->create(['type' => 'lab', 'key' => 'solved-lab']);
        Lab::factory()->create(['slug' => 'untouched-lab']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'untouched-lab']);

        $user = User::factory()->create();
        LabAttempt::create(['user_id' => $user->id, 'activity_id' => $startedActivity->id, 'status' => 'started', 'started_at' => now()]);
        LabAttempt::create(['user_id' => $user->id, 'activity_id' => $solvedActivity->id, 'status' => 'solved', 'started_at' => now(), 'completed_at' => now()]);

        $this->actingAs($user)->get('/de/labs')->assertInertia(fn ($page) => $page
            ->component('Labs/Index')
            ->where('labs', fn (Collection $labs) => $labs->firstWhere('slug', 'started-lab')['status'] === 'in_progress'
                && $labs->firstWhere('slug', 'solved-lab')['status'] === 'solved'
                && $labs->firstWhere('slug', 'untouched-lab')['status'] === 'not_started'),
        );
    }

    public function test_empty_state_when_no_labs_exist(): void
    {
        $this->get('/de/labs')->assertInertia(fn ($page) => $page
            ->component('Labs/Index')
            ->where('labs', []),
        );
    }

    public function test_the_labs_index_route_is_named_and_resolvable_from_the_dashboard(): void
    {
        $this->assertSame('/de/labs', route('labs.index', absolute: false));
    }
}
