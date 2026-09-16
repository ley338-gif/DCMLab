<?php

namespace App\Services;

/**
 * Ersetzt die zwei positionalen Parameter von `RuntimeProviderContract::
 * create()` (CMS-8b). `userId`/`datasetSlug`/`templateSlug`/`runtimeKey`
 * ueberqueren die HTTP-Grenze zu services/sandbox, `activityId`/
 * `labAttemptId` bleiben reine Laravel-seitige Buchfuehrung fuer die
 * `sandbox_sessions`-Zeile (RuntimeSessionService).
 */
final readonly class RuntimeRequest
{
    public function __construct(
        public string $userId,
        public string $datasetSlug,
        public string $templateSlug,
        /**
         * Opaker, vom Aufrufer gewaehlter Eigentuemer-/Idempotenz-
         * Schluessel (nie Nutzereingabe) -- verhindert, dass
         * services/sandbox eine bestehende Sitzung eines anderen
         * Zwecks (z. B. eine offene Lesson-Spielwiese) blind einem neuen
         * Aufruf zuordnet (Betreiber-Review, CMS-8b). Sandbox setzt
         * `"sandbox:user:{$userId}"` (unveraendert ueber Lektionen/
         * Datensaetze hinweg, reproduziert das heutige Verhalten), Lab
         * setzt `"lab-attempt:{$attemptId}"` (CMS-8d).
         */
        public string $runtimeKey,
        public ?int $activityId = null,
        /**
         * Bewusst nicht generisch "attemptId" genannt -- nur Lab hat ein
         * Attempt-Konzept, Sandbox nicht. Bleibt `null` fuer jeden
         * Sandbox-Aufruf.
         */
        public ?int $labAttemptId = null,
    ) {}
}
