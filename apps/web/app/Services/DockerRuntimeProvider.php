<?php

namespace App\Services;

/**
 * Einzige Phase-1-Implementierung von RuntimeProviderContract (ADR 0096,
 * CMS-2; erweitert CMS-8b): reine Weiterleitung an SandboxClientContract,
 * das den eigentlichen HTTP-Kontakt zu services/sandbox haelt. Der
 * Docker-Kontakt selbst bleibt vollstaendig in services/sandbox/app/
 * docker_ops.py -- diese Klasse ist nur die benannte Seam, hinter der
 * spaeter ein zweiter Provider (Podman, Kubernetes, ...) registriert
 * werden koennte, ohne RuntimeSessionService anzufassen.
 */
final readonly class DockerRuntimeProvider implements RuntimeProviderContract
{
    public function __construct(
        private SandboxClientContract $client,
    ) {}

    public function create(RuntimeRequest $request): array
    {
        return $this->client->create($request);
    }

    public function state(string $sandboxId): array
    {
        return $this->client->state($sandboxId);
    }

    public function exec(string $sandboxId, string $command): array
    {
        return $this->client->exec($sandboxId, $command);
    }

    public function events(string $sandboxId): array
    {
        return $this->client->events($sandboxId);
    }

    public function delete(string $sandboxId): void
    {
        $this->client->delete($sandboxId);
    }
}
