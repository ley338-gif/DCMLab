<?php

namespace Tests\Feature;

use App\Models\Lab;
use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Homebase-Umbau: "/" ist jetzt personalisiert -- Gast sieht die
 * oeffentliche Landingpage, ein eingeloggter Nutzer wird direkt aufs
 * Dashboard geleitet. Der volle Katalog (vorher unter "/") lebt unveraendert
 * unter /de/tracks weiter, siehe TrackControllerTest.
 */
class HomeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_guest_sees_the_public_landing_page(): void
    {
        $response = $this->get('/de');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Welcome'));
    }

    public function test_the_landing_page_shows_real_published_tracks_and_labs(): void
    {
        Track::factory()->create(['slug' => 'fundamente', 'status' => 'published', 'order' => 1]);
        Track::factory()->create(['slug' => 'entwurf', 'status' => 'draft', 'order' => 2]);
        Lab::factory()->create(['slug' => 'c-echo-live', 'status' => 'published']);
        Lab::factory()->create(['slug' => 'noch-nicht-fertig', 'status' => 'draft']);

        $this->get('/de')->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->has('highlight_tracks', 1)
            ->where('highlight_tracks.0.slug', 'fundamente')
            ->has('highlight_labs', 1)
            ->where('highlight_labs.0.slug', 'c-echo-live'),
        );
    }

    public function test_an_authenticated_user_is_redirected_to_the_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/de');

        $response->assertRedirect(route('dashboard'));
    }
}
