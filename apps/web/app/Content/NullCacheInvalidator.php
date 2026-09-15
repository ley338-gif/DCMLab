<?php

namespace App\Content;

/**
 * No-op-Implementierung von CacheInvalidatorContract (ADR 0074/0085) --
 * gebunden in der Testumgebung (AppServiceProvider), damit die Suite keine
 * echten HTTP-Aufrufe an drei in Tests nie erreichbare Dienste macht.
 * Ausserhalb von Tests ist die Bindung `HttpCacheInvalidator`, die die
 * `/internal/cache/clear`-Endpunkte in services/engine,
 * services/scenario-engine und services/sandbox aufruft.
 */
final class NullCacheInvalidator implements CacheInvalidatorContract
{
    public function invalidate(): void
    {
        // Absichtlich leer, siehe Klassendoc.
    }
}
