<?php

namespace App\Content;

/**
 * Schnittstelle fuer die Cache-Invalidierung bei services/engine,
 * services/scenario-engine und services/sandbox nach einer Veroeffentlichung
 * (ADR 0071, W2). Trennt ContentWriter von der konkreten Umsetzung, nach
 * demselben Muster wie EngineClientContract/SandboxClientContract.
 */
interface CacheInvalidatorContract
{
    public function invalidate(): void;
}
