<?php

namespace App\Services;

/**
 * Schnittstelle des Spielwiese-Orchestrator-Clients (Abschnitt 3.3, 6, 13;
 * erweitert CMS-8b). Analog zu EngineClientContract -- trennt
 * `RuntimeSessionService` von der konkreten HTTP-Implementierung.
 */
interface SandboxClientContract
{
    /**
     * @return array<string, mixed>
     */
    public function create(RuntimeRequest $request): array;

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeGoneException
     */
    public function state(string $sandboxId): array;

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeGoneException
     * @throws RuntimeNotReadyException Sitzung wartet noch in der Warteschlange (409).
     */
    public function exec(string $sandboxId, string $command): array;

    /**
     * @return array<string, mixed>
     *
     * @throws RuntimeGoneException
     * @throws RuntimeNotReadyException Sitzung wartet noch in der Warteschlange (409).
     */
    public function events(string $sandboxId): array;

    public function delete(string $sandboxId): void;
}
