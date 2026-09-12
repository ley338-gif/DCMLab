<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bestenliste (Abschnitt 7): Opt-in ist per Default aus.
 */
class LeaderboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_leaderboard_is_reachable_without_login_and_defaults_to_empty(): void
    {
        $service = new ProfileService;
        $user = User::factory()->create();
        $service->profileFor($user);

        $response = $this->get('/de/leaderboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Leaderboard')
            ->where('entries', []),
        );
    }

    public function test_leaderboard_lists_only_opted_in_profiles(): void
    {
        $service = new ProfileService;

        $optedIn = User::factory()->create(['name' => 'Opted In']);
        $service->profileFor($optedIn)->forceFill(['leaderboard_opt_in' => true, 'points' => 42])->save();

        $optedOut = User::factory()->create(['name' => 'Opted Out']);
        $service->profileFor($optedOut)->forceFill(['leaderboard_opt_in' => false, 'points' => 99])->save();

        $response = $this->get('/de/leaderboard');

        $response->assertInertia(fn ($page) => $page
            ->component('Leaderboard')
            ->has('entries', 1)
            ->where('entries.0.name', 'Opted In')
            ->where('entries.0.points', 42),
        );
    }
}
