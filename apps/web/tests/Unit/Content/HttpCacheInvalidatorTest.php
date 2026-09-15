<?php

namespace Tests\Unit\Content;

use App\Content\HttpCacheInvalidator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * ADR 0085 (docs/offene-fragen.md, "Cache-Invalidierung bei Engine/Sandbox
 * nach einer Veroeffentlichung"): ruft alle drei internen Dienste auf,
 * loggt statt zu werfen, wenn einer nicht erreichbar ist.
 */
class HttpCacheInvalidatorTest extends TestCase
{
    public function test_it_calls_the_cache_clear_endpoint_on_all_three_services_with_the_internal_key(): void
    {
        config([
            'services.engine.url' => 'http://engine.test',
            'services.engine.key' => 'secret',
            'services.scenario_engine.url' => 'http://scenario-engine.test',
            'services.scenario_engine.key' => 'secret',
            'services.sandbox.url' => 'http://sandbox.test',
            'services.sandbox.key' => 'secret',
        ]);
        Http::fake(['*' => Http::response(['status' => 'cleared'], 200)]);

        (new HttpCacheInvalidator)->invalidate();

        Http::assertSent(fn ($request) => $request->url() === 'http://engine.test/internal/cache/clear'
            && $request->hasHeader('X-DCMLAB-KEY', 'secret'));
        Http::assertSent(fn ($request) => $request->url() === 'http://scenario-engine.test/internal/cache/clear');
        Http::assertSent(fn ($request) => $request->url() === 'http://sandbox.test/internal/cache/clear');
    }

    public function test_it_logs_a_warning_instead_of_throwing_when_a_service_is_unreachable(): void
    {
        config([
            'services.engine.url' => 'http://engine.test',
            'services.engine.key' => 'secret',
            'services.scenario_engine.url' => 'http://scenario-engine.test',
            'services.scenario_engine.key' => 'secret',
            'services.sandbox.url' => 'http://sandbox.test',
            'services.sandbox.key' => 'secret',
        ]);
        Http::fake([
            'engine.test/*' => Http::response(['status' => 'cleared'], 200),
            'scenario-engine.test/*' => fn () => throw new ConnectionException('refused'),
            'sandbox.test/*' => Http::response('server error', 500),
        ]);
        Log::spy();

        (new HttpCacheInvalidator)->invalidate();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message) => str_contains($message, 'scenario_engine'))
            ->once();
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message) => str_contains($message, 'sandbox'))
            ->once();
        Log::shouldHaveReceived('warning')->twice();
    }
}
