<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Rate Limits auf den Endpunkten, die Nutzercode ausfuehren lassen
 * (Abschnitt 10, P9) -- prueft, dass die throttle-Middleware tatsaechlich
 * an den Routen haengt, ohne 60+ echte Requests feuern zu muessen.
 */
class RouteRateLimitsTest extends TestCase
{
    public function test_node_routes_are_throttled(): void
    {
        $middleware = Route::getRoutes()->getByName('nodes.exec')?->gatherMiddleware() ?? [];

        $this->assertContains('throttle:60,1', $middleware);
    }

    public function test_sandbox_routes_are_throttled(): void
    {
        $middleware = Route::getRoutes()->getByName('sandbox.exec')?->gatherMiddleware() ?? [];

        $this->assertContains('throttle:30,1', $middleware);
    }

    public function test_creating_a_sandbox_from_a_lesson_is_throttled(): void
    {
        $middleware = Route::getRoutes()->getByName('lessons.sandbox')?->gatherMiddleware() ?? [];

        $this->assertContains('throttle:10,1', $middleware);
    }
}
