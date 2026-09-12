<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('public-profile.edit'))
            ->assertOk();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('public-profile.edit'))->assertRedirect('/de/login');
    }

    public function test_leaderboard_opt_in_can_be_toggled(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('public-profile.update'), ['leaderboard_opt_in' => true])
            ->assertRedirect(route('public-profile.edit'));

        $this->assertTrue($user->profile()->firstOrFail()->leaderboard_opt_in);

        $this->actingAs($user)
            ->patch(route('public-profile.update'), ['leaderboard_opt_in' => false])
            ->assertRedirect(route('public-profile.edit'));

        $this->assertFalse($user->profile()->firstOrFail()->fresh()->leaderboard_opt_in);
    }
}
