<?php

namespace App\Services;

/**
 * Laufzeitumgebung fuer eine Spielwiesen-Sitzung (ADR 0096, CMS-2): trennt
 * `SandboxController` von der konkreten Backend-Implementierung, analog zu
 * EngineClientContract/SandboxClientContract. Ein SandboxTemplate nennt nur
 * seinen `runtime_provider`-String ("docker"), nie eine konkrete Klasse --
 * RuntimeProviderRegistry loest ihn auf.
 */
interface RuntimeProviderContract
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
