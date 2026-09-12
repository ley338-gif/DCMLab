<?php

namespace Tests\Feature;

use App\Models\Node;
use App\Models\NodeAttempt;
use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Oeffentliches Profil unter dem Slug (Abschnitt 7, P8-DoD): ohne Login
 * erreichbar und als PDF exportierbar.
 */
class PublicProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_profile_is_reachable_without_login(): void
    {
        [$user, $node] = $this->solvedUser();
        $profile = (new ProfileService)->profileFor($user);
        (new ProfileService)->recomputeAfterSolve($user, $node);
        $profile->refresh();

        $response = $this->get("/de/profiles/{$profile->public_slug}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Profiles/Show')
            ->where('profile.name', $user->name)
            ->where('profile.points', 9)
            ->where('profile.skill_vector.netzwerk', 9),
        );
    }

    public function test_unknown_slug_is_a_404(): void
    {
        $this->get('/de/profiles/does-not-exist')->assertNotFound();
    }

    public function test_pdf_export_is_downloadable_without_login(): void
    {
        [$user, $node] = $this->solvedUser();
        $profile = (new ProfileService)->profileFor($user);
        (new ProfileService)->recomputeAfterSolve($user, $node);
        $profile->refresh();

        $response = $this->get("/de/profiles/{$profile->public_slug}/export");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    /**
     * @return array{0: User, 1: Node}
     */
    private function solvedUser(): array
    {
        $user = User::factory()->create();
        $node = Node::factory()->create(['skills' => ['netzwerk'], 'points' => 9]);

        NodeAttempt::create([
            'user_id' => $user->id,
            'node_id' => $node->id,
            'engine_session_id' => 'sess-1',
            'status' => 'solved',
            'points' => 9,
            'started_at' => now(),
            'flag_submitted_at' => now(),
        ]);

        return [$user, $node];
    }
}
