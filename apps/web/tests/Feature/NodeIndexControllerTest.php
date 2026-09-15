<?php

namespace Tests\Feature;

use App\Models\Node;
use App\Models\NodeAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Oeffentlicher Labs-Katalog (NodeController::index): wie Tracks/Index ohne
 * Login sichtbar, mit echtem "gelöst"-Status pro Nutzer statt eines
 * Platzhalters.
 */
class NodeIndexControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_it_is_publicly_visible_without_login(): void
    {
        Node::factory()->create(['slug' => 'silent-ct']);

        $this->get('/de/nodes')->assertOk();
    }

    public function test_it_lists_a_published_node(): void
    {
        Node::factory()->create(['slug' => 'silent-ct', 'status' => 'published']);

        $response = $this->get('/de/nodes');

        $response->assertInertia(fn ($page) => $page
            ->component('Nodes/Index')
            ->where('nodes.0.slug', 'silent-ct'),
        );
    }

    /**
     * Seit ADR 0110 (CMS-6d Haertung): eine per Studio angelegte, noch nicht
     * freigegebene Node (ADR 0109) darf im oeffentlichen Katalog nicht
     * auftauchen -- vorher haette jeder Entwurf sofort jeden Lernenden
     * erreicht.
     */
    public function test_it_excludes_a_node_that_is_not_yet_published(): void
    {
        Node::factory()->create(['slug' => 'draft-node', 'status' => 'draft']);

        $response = $this->get('/de/nodes');

        $response->assertInertia(fn ($page) => $page->has('nodes', 0));
    }

    public function test_it_marks_solved_nodes_for_the_authenticated_user(): void
    {
        // Gleiche category/difficulty (Factory-Default) => Sortierung faellt
        // auf slug zurueck: "silent-ct" vor "wrong-door".
        $solved = Node::factory()->create(['slug' => 'silent-ct']);
        Node::factory()->create(['slug' => 'wrong-door']);
        $user = User::factory()->create();

        NodeAttempt::create([
            'user_id' => $user->id,
            'node_id' => $solved->id,
            'engine_session_id' => 'session-1',
            'status' => 'solved',
            'started_at' => now(),
            'flag_submitted_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/de/nodes');

        $response->assertInertia(fn ($page) => $page
            ->where('nodes.0.slug', 'silent-ct')
            ->where('nodes.0.solved', true)
            ->where('nodes.1.slug', 'wrong-door')
            ->where('nodes.1.solved', false),
        );
    }

    public function test_guests_never_see_a_solved_node(): void
    {
        $node = Node::factory()->create(['slug' => 'silent-ct']);
        $user = User::factory()->create();

        NodeAttempt::create([
            'user_id' => $user->id,
            'node_id' => $node->id,
            'engine_session_id' => 'session-1',
            'status' => 'solved',
            'started_at' => now(),
            'flag_submitted_at' => now(),
        ]);

        $response = $this->get('/de/nodes');

        $response->assertInertia(fn ($page) => $page->where('nodes.0.solved', false));
    }
}
