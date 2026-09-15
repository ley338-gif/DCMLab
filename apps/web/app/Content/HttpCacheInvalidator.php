<?php

namespace App\Content;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Loest die offene Frage "Cache-Invalidierung bei Engine/Sandbox nach einer
 * Veroeffentlichung" (docs/offene-fragen.md, ADR 0071/0074): ruft
 * `POST /internal/cache/clear` auf allen drei internen Diensten auf,
 * abgesichert mit demselben `X-DCMLAB-KEY`-Header wie die bestehende
 * Engine-Anbindung (`App\Services\EngineClient`). Parallel statt
 * nacheinander (`Http::pool()`), damit ein langsamer/nicht erreichbarer
 * Dienst die anderen beiden nicht verzoegert. Ein fehlgeschlagener Aufruf
 * wird geloggt, nie geworfen -- eine kurzzeitig veraltete Engine ist kein
 * Grund, eine Veroeffentlichung abzubrechen (`ContentWriter::write()`
 * ruft `invalidate()` erst NACH dem erfolgreichen Schreiben auf).
 */
final class HttpCacheInvalidator implements CacheInvalidatorContract
{
    public function invalidate(): void
    {
        $services = [
            'engine' => config('services.engine'),
            'scenario_engine' => config('services.scenario_engine'),
            'sandbox' => config('services.sandbox'),
        ];

        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn (string $name, array $service) => $pool->as($name)
                ->withHeaders(['X-DCMLAB-KEY' => $service['key']])
                ->timeout(3)
                ->connectTimeout(2)
                ->post("{$service['url']}/internal/cache/clear"),
            array_keys($services),
            $services,
        ));

        foreach ($services as $name => $service) {
            $response = $responses[$name] ?? null;

            if (! $response instanceof Response || ! $response->successful()) {
                Log::warning("Cache-Invalidierung fehlgeschlagen: {$name}", [
                    'url' => $service['url'],
                    'exception' => $response instanceof \Throwable ? $response->getMessage() : null,
                ]);
            }
        }
    }
}
