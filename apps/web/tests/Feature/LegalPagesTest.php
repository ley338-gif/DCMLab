<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rechtliche Platzhalterseiten (Abschnitt 10, P9): oeffentlich ohne Login,
 * die Nutzungsbedingungen nennen ausdruecklich, dass die Spielwiese
 * Nutzercode ausfuehrt.
 */
class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_impressum_is_reachable_without_login(): void
    {
        $this->get('/de/impressum')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Legal/Impressum'));
    }

    public function test_datenschutz_is_reachable_without_login(): void
    {
        $this->get('/de/datenschutz')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Legal/Datenschutz'));
    }

    public function test_nutzungsbedingungen_is_reachable_without_login(): void
    {
        $this->get('/de/nutzungsbedingungen')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Legal/Nutzungsbedingungen'));
    }
}
