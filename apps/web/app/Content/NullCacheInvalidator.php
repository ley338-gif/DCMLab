<?php

namespace App\Content;

/**
 * Default-Implementierung von CacheInvalidatorContract (ADR 0074): bewusst
 * ein No-op. Echte Invalidierung braucht eigene Endpunkte in
 * services/engine, services/scenario-engine und services/sandbox, die es
 * heute nicht gibt -- das ist eine eigene, dienstuebergreifende Aenderung
 * und nicht Teil von W2 (siehe docs/offene-fragen.md). Bis dahin bleibt der
 * bestehende Mechanismus wirksam: alle drei Dienste cachen `content/` nur
 * pro Prozess (`lru_cache`, siehe services/engine/app/content.py und
 * services/sandbox/app/datasets_yaml.py) und lesen bei jedem Neustart neu.
 */
final class NullCacheInvalidator implements CacheInvalidatorContract
{
    public function invalidate(): void
    {
        // Absichtlich leer, siehe Klassendoc.
    }
}
