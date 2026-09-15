<?php

namespace Tests\Feature;

use App\Models\Node;
use App\Models\NodeAttempt;
use App\Models\Track;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\ProfileService;
use Database\Seeders\AchievementSeeder;
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
     * P10.65/ADR 0090b: bestandene Tracks sind seit der Migration des
     * "Pionier"-Systems ein deklaratives Achievement ("track-<slug>") wie
     * jedes andere, keine eigene `track_badges`-Prop mehr.
     */
    public function test_profile_page_lists_a_passed_track_as_an_unlocked_achievement(): void
    {
        $this->seed(AchievementSeeder::class);
        $user = User::factory()->create();
        Track::factory()->create(['slug' => 'fundamente']);
        $profile = (new ProfileService)->profileFor($user);
        (new AchievementService)->unlock($user, 'track-fundamente');

        $response = $this->get("/de/profiles/{$profile->public_slug}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('profile.achievements', function ($achievements) {
                $trackBadge = collect($achievements)->firstWhere('slug', 'track-fundamente');

                return $trackBadge !== null && $trackBadge['unlocked'] === true;
            }),
        );
    }

    /**
     * Prueft die PDF-Vorlage direkt (nicht das von DomPDF erzeugte, komprimierte
     * Binaerformat -- darin laesst sich Text nicht zuverlaessig suchen), analog
     * dazu, dass der bestehende Export-Test auch nur den Content-Type prueft.
     */
    public function test_pdf_template_renders_unlocked_achievements(): void
    {
        $html = View::make('profiles.pdf', [
            'profile' => [
                'name' => 'Test Nutzer',
                'rank' => 'novice',
                'points' => 50,
                'skill_vector' => ['netzwerk' => 0, 'datenmodell' => 0, 'bildgebung' => 0, 'integration' => 0, 'security' => 0],
                'member_since' => '01.01.2026',
                'achievements' => [
                    [
                        'slug' => 'track-fundamente', 'name' => 'Fundamente abgeschlossen',
                        'description' => 'Bestehe die Abschlussprüfung des Tracks „Fundamente".',
                        'unlocked' => true, 'unlocked_at' => '2026-09-14T00:00:00+00:00',
                    ],
                    ['slug' => 'echo-heard', 'name' => 'Echo Heard', 'description' => '...', 'unlocked' => false, 'unlocked_at' => null],
                ],
            ],
        ])->render();

        $this->assertStringContainsString('Fundamente abgeschlossen', $html);
        $this->assertStringContainsString('2026-09-14', $html);
        $this->assertStringNotContainsString('Echo Heard', $html);
    }

    /**
     * Leerzustand: ohne freigeschaltetes Achievement zeigt das PDF einen
     * sinnvollen Hinweis statt einer leeren Tabelle.
     */
    public function test_pdf_template_shows_an_empty_state_without_unlocked_achievements(): void
    {
        $html = View::make('profiles.pdf', [
            'profile' => [
                'name' => 'Test Nutzer',
                'rank' => 'novice',
                'points' => 0,
                'skill_vector' => ['netzwerk' => 0, 'datenmodell' => 0, 'bildgebung' => 0, 'integration' => 0, 'security' => 0],
                'member_since' => '01.01.2026',
                'achievements' => [
                    ['slug' => 'echo-heard', 'name' => 'Echo Heard', 'description' => '...', 'unlocked' => false, 'unlocked_at' => null],
                ],
            ],
        ])->render();

        $this->assertStringContainsString('Noch keine Achievements verfügbar.', $html);
    }

    /**
     * Achievement-System: das oeffentliche Profil zeigt die generische
     * Achievement-Liste (gesperrt und freigeschaltet), aber niemals das
     * interne `metadata`-Feld eines Unlocks -- AchievementService::toArray()
     * gibt es ohnehin nie aus, dieser Test haelt das fest.
     */
    public function test_profile_page_exposes_unlocked_achievements_without_leaking_internal_metadata(): void
    {
        $this->seed(AchievementSeeder::class);
        $user = User::factory()->create();
        $profile = (new ProfileService)->profileFor($user);
        (new AchievementService)->unlock($user, 'sandbox-starter', ['lesson' => 'geheime-lektion-id']);

        $response = $this->get("/de/profiles/{$profile->public_slug}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('profile.achievements', 13)
            ->where('profile.achievements', function ($achievements) {
                $sandboxStarter = collect($achievements)->firstWhere('slug', 'sandbox-starter');

                return $sandboxStarter['unlocked'] === true
                    && ! array_key_exists('metadata', $sandboxStarter)
                    && collect($achievements)->firstWhere('slug', 'echo-heard')['unlocked'] === false;
            }),
        );
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
