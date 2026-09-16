<?php

namespace App\Services;

/**
 * Laufzeitumgebung fuer eine Spielwiesen-/Lab-Sitzung (ADR 0096, CMS-2;
 * erweitert CMS-8b): trennt `RuntimeSessionService` von der konkreten
 * Backend-Implementierung, analog zu EngineClientContract/
 * SandboxClientContract. Ein SandboxTemplate nennt nur seinen
 * `runtime_provider`-String ("docker"), nie eine konkrete Klasse --
 * RuntimeProviderRegistry loest ihn auf.
 *
 * `state()`/`exec()`/`events()` koennen `RuntimeGoneException` werfen, wenn
 * die Sitzung auf der Runtime-Seite nicht mehr existiert (z. B. durch den
 * Idle-Cleanup-Loop weggeraeumt, CMS-8b) -- `RuntimeSessionService` ist der
 * einzige vorgesehene Ort, der das abfaengt und reconciled.
 */
interface RuntimeProviderContract
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
     */
    public function exec(string $sandboxId, string $command): array;

    /**
     * Beobachtungsschicht (CMS-8b): Exec-Facts (tatsaechlich ausgefuehrte
     * Befehle + Exit-Codes) und Orthanc-Facts (neue Instanzen samt
     * SOP-Klasse/Transfer-Syntax) fuer diese Sitzung.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeGoneException
     */
    public function events(string $sandboxId): array;

    public function delete(string $sandboxId): void;
}
