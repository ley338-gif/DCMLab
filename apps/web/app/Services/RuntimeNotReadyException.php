<?php

namespace App\Services;

use RuntimeException;

/**
 * Die Runtime-Sitzung existiert noch, ist aber noch nicht aktiv -- sie
 * wartet in Pythons Warteschlange (CMS-8b, Betreiber-Review). Das ist
 * KEIN Gone/Reaped-Fall: eine wartende Sitzung kann spaeter noch unter
 * derselben ID starten, waehrend `RuntimeGoneException` eine tatsaechlich
 * weggeraeumte Sitzung meint. Bewusst keine Unterklasse von
 * `RuntimeGoneException`, damit `RuntimeSessionService::withReconciliation()`
 * sie NICHT als 'reaped' reconciled.
 */
final class RuntimeNotReadyException extends RuntimeException
{
    public function __construct(public readonly string $sandboxId)
    {
        parent::__construct("Sandbox-Sitzung \"{$sandboxId}\" wartet noch in der Warteschlange.");
    }
}
