<?php

namespace App\Services;

/**
 * Schnittstelle des Spielwiese-Orchestrator-Clients (Abschnitt 3.3, 6, 13).
 * Analog zu EngineClientContract -- trennt Controller von der konkreten
 * HTTP-Implementierung.
 */
interface SandboxClientContract
{
    /**
     * @return array<string, mixed>
     */
    public function create(string $userId, string $datasetSlug): array;

    /**
     * @return array<string, mixed>
     */
    public function state(string $sandboxId): array;

    /**
     * @return array<string, mixed>
     */
    public function exec(string $sandboxId, string $command): array;

    public function delete(string $sandboxId): void;
}
