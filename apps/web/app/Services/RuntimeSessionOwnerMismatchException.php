<?php

namespace App\Services;

use RuntimeException;

/**
 * Der Runtime-Provider hat eine `sandbox_id` zurueckgegeben, die bereits
 * einer bestehenden `SandboxSession` mit einem ANDEREN Eigentuemer
 * (User/Provider/LabAttempt) gehoert (CMS-8b, Betreiber-Review, drittes
 * Review). Das darf nicht still als Wiederverwendung durchgehen -- eine
 * falsche Zuordnung waere schlimmer als ein harter Fehler. In der Praxis
 * sollte das nie passieren (Pythons `runtime_key`-Idempotenz ist bereits
 * Owner-scoped), diese Pruefung ist die zweite, Laravel-seitige
 * Verteidigungslinie dagegen.
 */
final class RuntimeSessionOwnerMismatchException extends RuntimeException
{
    public function __construct(public readonly string $runtimeInstanceId)
    {
        parent::__construct(
            "Die Runtime-Instanz \"{$runtimeInstanceId}\" gehoert bereits zu einer anderen SandboxSession.",
        );
    }
}
