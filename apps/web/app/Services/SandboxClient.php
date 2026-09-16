<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Duenner HTTP-Client fuer den Spielwiese-Orchestrator (Abschnitt 3.3, 6;
 * erweitert CMS-8b). Der Orchestrator ist der einzige Dienst mit
 * Docker-Socket-Zugriff -- Laravel spricht ihn nur ueber HTTP an.
 */
final class SandboxClient implements SandboxClientContract
{
    /**
     * @return array<string, mixed>
     */
    public function create(RuntimeRequest $request): array
    {
        try {
            return $this->client()
                ->post('/v1/sandboxes', [
                    'user_id' => $request->userId,
                    'dataset_slug' => $request->datasetSlug,
                    'template_slug' => $request->templateSlug,
                    'runtime_key' => $request->runtimeKey,
                ])
                ->throw()->json();
        } catch (RequestException $e) {
            // Quota (429, ADR 0096) und Runtime-Konflikt (409, CMS-8b,
            // Betreiber-Review: ein anderer runtime_key haelt bereits die
            // einzige erlaubte Sitzung dieses Nutzers) sind beides normale,
            // erwartete Ausgaenge -- keine Exception, derselbe weiche
            // ['error' => ...]-Rueckgabepfad fuer beide.
            if ($e->response->status() === 429) {
                return ['error' => 'quota_exceeded'];
            }

            if ($e->response->status() === 409) {
                return ['error' => 'active_runtime_exists'];
            }

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function state(string $sandboxId): array
    {
        return $this->getOrGone("/v1/sandboxes/{$sandboxId}", $sandboxId);
    }

    /**
     * @return array<string, mixed>
     */
    public function exec(string $sandboxId, string $command): array
    {
        try {
            return $this->client()
                ->post("/v1/sandboxes/{$sandboxId}/exec", ['command' => $command])
                ->throw()->json();
        } catch (RequestException $e) {
            throw $this->goneOr404($e, $sandboxId);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function events(string $sandboxId): array
    {
        return $this->getOrGone("/v1/sandboxes/{$sandboxId}/events", $sandboxId);
    }

    public function delete(string $sandboxId): void
    {
        // Bewusst KEINE RuntimeGoneException hier -- Pythons DELETE ist
        // bereits idempotent (204 auch fuer eine unbekannte sandbox_id,
        // orchestrator.delete_sandbox()), ein "loeschen, was schon weg
        // ist" ist kein Fehlerfall.
        $this->client()->delete("/v1/sandboxes/{$sandboxId}")->throw();
    }

    /**
     * @return array<string, mixed>
     */
    private function getOrGone(string $path, string $sandboxId): array
    {
        try {
            return $this->client()->get($path)->throw()->json();
        } catch (RequestException $e) {
            throw $this->goneOr404($e, $sandboxId);
        }
    }

    private function goneOr404(RequestException $e, string $sandboxId): RequestException|RuntimeGoneException
    {
        return $e->response->status() === 404
            ? new RuntimeGoneException($sandboxId)
            : $e;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('services.sandbox.url'))
            ->withHeaders(['X-DCMLAB-KEY' => config('services.sandbox.key')])
            ->timeout(15);
    }
}
