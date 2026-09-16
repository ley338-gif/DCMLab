<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CMS-8a, Abschnitt H: eigenstaendige Learner-Route fuer ein Lab -- noch ohne
 * Terminal/Runtime (CMS-8b/8d), nur Anleitung + ein erster Attempt-Eintrag.
 *
 * Betreiber-Review vor #128: Ansehen (show) und Beginnen (start) sind
 * bewusst getrennte Aktionen -- anders als bei Node legt der reine
 * Seitenaufruf noch KEINEN Attempt an.
 */
class LabControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_published_lab_is_visible_without_an_attempt(): void
    {
        Lab::factory()->create([
            'slug' => 'c-echo-connectivity',
            'title' => ['de' => 'C-ECHO Connectivity Lab'],
            'scenario_title' => ['de' => 'Verbindung pruefen'],
            'rich_content' => ['type' => 'doc', 'version' => 1, 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Pruefe die Verbindung.']]],
            ]],
        ]);
        Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/de/labs/c-echo-connectivity')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Labs/Show')
                ->where('lab.title', 'C-ECHO Connectivity Lab')
                ->where('lab.scenario_title', 'Verbindung pruefen')
                ->where('attempt', null)
                ->where('briefing_html', fn (string $html) => str_contains($html, 'Pruefe die Verbindung.')),
            );

        $this->assertDatabaseCount('lab_attempts', 0);
    }

    public function test_starting_a_lab_creates_an_attempt(): void
    {
        Lab::factory()->create(['slug' => 'c-echo-connectivity']);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/de/labs/c-echo-connectivity/start')
            ->assertRedirect();

        $this->assertDatabaseHas('lab_attempts', [
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'status' => 'started',
        ]);

        $this->actingAs($user)
            ->get('/de/labs/c-echo-connectivity')
            ->assertInertia(fn ($page) => $page->where('attempt.status', 'started'));
    }

    public function test_starting_a_lab_twice_does_not_create_a_second_attempt(): void
    {
        Lab::factory()->create(['slug' => 'c-echo-connectivity']);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');
        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');

        $this->assertSame(1, LabAttempt::where('user_id', $user->id)->where('activity_id', $activity->id)->count());
    }

    /**
     * Ein zweiter "Lab starten"-Klick darf einen bereits geloesten Attempt
     * nicht zurueck auf "started" setzen -- firstOrCreate() legt nur an,
     * ruehrt einen bestehenden Datensatz nie an.
     */
    public function test_starting_a_lab_does_not_reset_an_already_solved_attempt(): void
    {
        Lab::factory()->create(['slug' => 'c-echo-connectivity']);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'c-echo-connectivity']);
        $user = User::factory()->create();

        LabAttempt::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'status' => 'solved',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->actingAs($user)->post('/de/labs/c-echo-connectivity/start');

        $this->assertSame('solved', LabAttempt::where('user_id', $user->id)->where('activity_id', $activity->id)->value('status'));
    }

    public function test_a_draft_lab_is_hidden_from_a_learner(): void
    {
        Lab::factory()->create(['slug' => 'draft-lab', 'status' => 'draft']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'draft-lab']);

        $user = User::factory()->create();

        $this->actingAs($user)->get('/de/labs/draft-lab')->assertNotFound();
    }

    public function test_starting_a_draft_lab_is_blocked_for_a_learner(): void
    {
        Lab::factory()->create(['slug' => 'draft-lab', 'status' => 'draft']);
        Activity::factory()->create(['type' => 'lab', 'key' => 'draft-lab']);

        $user = User::factory()->create();

        $this->actingAs($user)->post('/de/labs/draft-lab/start')->assertNotFound();
        $this->assertDatabaseCount('lab_attempts', 0);
    }

    public function test_a_draft_lab_is_visible_to_its_author(): void
    {
        $lab = Lab::factory()->create(['slug' => 'draft-lab', 'status' => 'draft']);
        $activity = Activity::factory()->create(['type' => 'lab', 'key' => 'draft-lab']);
        $author = User::factory()->author()->create();
        $activity->authorUsers()->attach($author);

        $this->actingAs($author)->get('/de/labs/draft-lab')->assertOk();
    }
}
