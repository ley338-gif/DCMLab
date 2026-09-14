<?php

namespace App\Services;

/**
 * Schnittstelle des Node-Engine-Clients (Abschnitt 3.3, 5.5, 13). Trennt
 * Controller von der konkreten HTTP-Implementierung, damit ein spaeteres
 * zweites Engine-Modul (pro Themenfeld) eingehaengt werden kann, ohne
 * Controller anzufassen.
 */
interface EngineClientContract
{
    /**
     * @return array<string, mixed>
     */
    public function createSession(string $nodeSlug): array;

    /**
     * @return array<string, mixed>
     */
    public function state(string $sessionId): array;

    /**
     * @return array<string, mixed>
     */
    public function exec(string $sessionId, string $host, string $command): array;

    /**
     * @return array<string, mixed>
     */
    public function setConfig(string $sessionId, string $host, string $field, string $value): array;

    /**
     * @return array<string, mixed>
     */
    public function triggerAction(string $sessionId, string $host, string $action): array;

    /**
     * @return array<string, mixed>
     */
    public function useHint(string $sessionId, string $hintId): array;

    /**
     * @return array<string, mixed>
     */
    public function viewWriteUp(string $sessionId): array;

    /**
     * @return array<string, mixed>
     */
    public function submitFlag(string $sessionId, string $value): array;
}
