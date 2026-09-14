<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Duenner HTTP-Client fuer die Szenario-Engine (Abschnitt 13, PoC
 * "Datenschutz"). Spricht dasselbe Protokoll wie EngineClient -- beide
 * implementieren EngineClientContract, EngineClientResolver waehlt pro
 * Node zwischen ihnen. Kennt keine Szenario-Semantik, reicht nur
 * Anfragen durch, genau wie EngineClient.
 */
final class ScenarioEngineClient implements EngineClientContract
{
    /**
     * @return array<string, mixed>
     */
    public function createSession(string $nodeSlug): array
    {
        return $this->client()->post('/v1/sessions', ['node_slug' => $nodeSlug])
            ->throw()->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function state(string $sessionId): array
    {
        return $this->client()->get("/v1/sessions/{$sessionId}/state")->throw()->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function exec(string $sessionId, string $host, string $command): array
    {
        return $this->client()
            ->post("/v1/sessions/{$sessionId}/exec", ['host' => $host, 'command' => $command])
            ->throw()->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function setConfig(string $sessionId, string $host, string $field, string $value): array
    {
        return $this->client()
            ->post("/v1/sessions/{$sessionId}/config", [
                'host' => $host,
                'field' => $field,
                'value' => $value,
            ])
            ->throw()->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function triggerAction(string $sessionId, string $host, string $action): array
    {
        try {
            return $this->client()
                ->post("/v1/sessions/{$sessionId}/action", ['host' => $host, 'action' => $action])
                ->throw()->json();
        } catch (RequestException $e) {
            if ($e->response->status() === 400) {
                return ['error' => $e->response->json('detail')];
            }

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function useHint(string $sessionId, string $hintId): array
    {
        try {
            return $this->client()
                ->post("/v1/sessions/{$sessionId}/hint", ['hint_id' => $hintId])
                ->throw()->json();
        } catch (RequestException $e) {
            if ($e->response->status() === 400) {
                return ['error' => $e->response->json('detail')];
            }

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function viewWriteUp(string $sessionId): array
    {
        return $this->client()->post("/v1/sessions/{$sessionId}/write-up")->throw()->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function submitFlag(string $sessionId, string $value): array
    {
        return $this->client()
            ->post("/v1/sessions/{$sessionId}/flag", ['value' => $value])
            ->throw()->json();
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('services.scenario_engine.url'))
            ->withHeaders(['X-DCMLAB-KEY' => config('services.scenario_engine.key')])
            ->timeout(10);
    }
}
