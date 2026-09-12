<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Eigene Fehlerseiten statt der Laravel-Standardseiten (Abschnitt 10, P9).
 */
class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_unknown_route_renders_the_custom_404_page(): void
    {
        $response = $this->get('/de/this-route-does-not-exist');

        $response->assertNotFound();
        $response->assertInertia(fn ($page) => $page
            ->component('Error')
            ->where('status', 404),
        );
    }

    public function test_debug_mode_still_shows_the_default_laravel_error_page(): void
    {
        config(['app.debug' => true]);

        $response = $this->get('/de/this-route-does-not-exist');

        $response->assertNotFound();
        $this->assertStringNotContainsString('"component":"Error"', $response->getContent() ?: '');
    }
}
