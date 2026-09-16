<?php

namespace App\Services;

use RuntimeException;

/**
 * Eine Runtime-Sitzung existiert auf der services/sandbox-Seite nicht mehr
 * (Python 404 "sandbox not found") -- meistens, weil der Idle-Cleanup-Loop
 * sie weggeraeumt hat, ohne dass Laravel je davon erfahren hat (CMS-8b,
 * Betreiber-Review). `SandboxClient` faengt den 404-Fall gezielt ab und
 * wirft diese Exception statt der generischen `RequestException`;
 * `RuntimeSessionService` ist der einzige Ort, der sie faengt, um
 * `sandbox_sessions`/`lab_attempts` zu reconcilen, bevor sie fuer die
 * Controller-404-Antwort weitergereicht wird.
 */
final class RuntimeGoneException extends RuntimeException
{
    public function __construct(public readonly string $sandboxId)
    {
        parent::__construct("Sandbox-Sitzung \"{$sandboxId}\" existiert nicht mehr.");
    }
}
