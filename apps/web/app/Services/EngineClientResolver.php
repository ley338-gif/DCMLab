<?php

namespace App\Services;

use App\Models\Node;

/**
 * Waehlt pro Node den zustaendigen Engine-Client (Abschnitt 13, PoC
 * "Datenschutz"): `terminal`-Nodes laufen gegen services/engine (DICOM),
 * `scenario`-Nodes gegen services/scenario-engine. Haelt NodeController
 * frei davon, das Engine-Modul selbst zu bestimmen.
 */
final class EngineClientResolver
{
    public function __construct(
        private readonly EngineClient $engine,
        private readonly ScenarioEngineClient $scenarioEngine,
    ) {}

    public function for(Node $node): EngineClientContract
    {
        return match ($node->interaction) {
            'scenario' => $this->scenarioEngine,
            default => $this->engine,
        };
    }
}
