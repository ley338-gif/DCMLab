<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Duenner HTTP-Client fuer den Spielwiese-Orchestrator (Abschnitt 3.3, 6).
 * Der Orchestrator ist der einzige Dienst mit Docker-Socket-Zugriff --
 * Laravel spricht ihn nur ueber HTTP an.
 */
final class SandboxClient
{
    /**
     * @return array<string, mixed>
     */
    public function create(string $userId, string $datasetSlug): array
    {
        try {
            return $this->client()
                ->post('/v1/sandboxes', ['user_id' => $userId, 'dataset_slug' => $datasetSlug])
                ->throw()->json();
        } catch (RequestException $e) {
            if ($e->response->status() === 429) {
                return ['error' => 'quota_exceeded'];
            }

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function state(string $sandboxId): array
    {
        return $this->client()->get("/v1/sandboxes/{$sandboxId}")->throw()->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function exec(string $sandboxId, string $command): array
    {
        return $this->client()
            ->post("/v1/sandboxes/{$sandboxId}/exec", ['command' => $command])
            ->throw()->json();
    }

    public function delete(string $sandboxId): void
    {
        $this->client()->delete("/v1/sandboxes/{$sandboxId}")->throw();
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('services.sandbox.url'))
            ->withHeaders(['X-DCMLAB-KEY' => config('services.sandbox.key')])
            ->timeout(15);
    }
}
