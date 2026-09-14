<?php

namespace Tests\Feature;

use App\Models\Node;
use App\Models\NodeAttempt;
use App\Models\Track;
use App\Models\TrackBadge;
use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
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
     * P10.65: das oeffentliche Profil zeigt bestandene Tracks bereits seit
     * P10.60 an -- hier folgt der PDF-Export nach, der denselben
     * `profileData()`-Prop (`track_badges`) bisher nicht rendert hat.
     */
    public function test_profile_page_lists_a_passed_track(): void
    {
        $user = User::factory()->create();
        $track = Track::factory()->create(['slug' => 'fundamente', 'title_key' => 'track.fundamente.title']);
        $profile = (new ProfileService)->profileFor($user);
        TrackBadge::create(['user_id' => $user->id, 'track_id' => $track->id, 'awarded_at' => now()]);

        $response = $this->get("/de/profiles/{$profile->public_slug}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('profile.track_badges.0.track_title_key', 'track.fundamente.title'),
        );
    }

    /**
     * Prueft die PDF-Vorlage direkt (nicht das von DomPDF erzeugte, komprimierte
     * Binaerformat -- darin laesst sich Text nicht zuverlaessig suchen), analog
     * dazu, dass der bestehende Export-Test auch nur den Content-Type prueft.
     */
    public function test_pdf_template_renders_the_passed_track_title(): void
    {
        $html = View::make('profiles.pdf', [
            'profile' => [
                'name' => 'Test Nutzer',
                'rank' => 'novice',
                'points' => 50,
                'skill_vector' => ['netzwerk' => 0, 'datenmodell' => 0, 'bildgebung' => 0, 'integration' => 0, 'security' => 0],
                'member_since' => '01.01.2026',
                'first_bloods' => [],
                'track_badges' => [
                    ['track_title_key' => 'track.fundamente.title', 'awarded_at' => '2026-09-14'],
                ],
            ],
        ])->render();

        $this->assertStringContainsString('Fundamente', $html);
        $this->assertStringContainsString('2026-09-14', $html);
    }

    /**
     * Leerzustand: ohne bestandenen Track zeigt das PDF einen sinnvollen
     * Hinweis statt einer leeren Tabelle.
     */
    public function test_pdf_template_shows_an_empty_state_without_a_passed_track(): void
    {
        $html = View::make('profiles.pdf', [
            'profile' => [
                'name' => 'Test Nutzer',
                'rank' => 'novice',
                'points' => 0,
                'skill_vector' => ['netzwerk' => 0, 'datenmodell' => 0, 'bildgebung' => 0, 'integration' => 0, 'security' => 0],
                'member_since' => '01.01.2026',
                'first_bloods' => [],
                'track_badges' => [],
            ],
        ])->render();

        $this->assertStringContainsString('Noch keine Abschlussprüfung bestanden.', $html);
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
