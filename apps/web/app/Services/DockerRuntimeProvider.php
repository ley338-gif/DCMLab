<?php

namespace App\Services;

/**
 * Einzige Phase-1-Implementierung von RuntimeProviderContract (ADR 0096,
 * CMS-2): reine Weiterleitung an SandboxClientContract, das den
 * eigentlichen HTTP-Kontakt zu services/sandbox haelt. Der Docker-Kontakt
 * selbst bleibt vollstaendig in services/sandbox/app/docker_ops.py -- diese
 * Klasse ist nur die benannte Seam, hinter der spaeter ein zweiter Provider
 * (Podman, Kubernetes, ...) registriert werden koennte, ohne
 * SandboxController anzufassen.
 */
final readonly class DockerRuntimeProvider implements RuntimeProviderContract
{
    public function __construct(
        private SandboxClientContract $client,
    ) {}

    public function create(string $userId, string $datasetSlug): array
    {
        return $this->client->create($userId, $datasetSlug);
    }

    public function state(string $sandboxId): array
    {
        return $this->client->state($sandboxId);
    }

    public function exec(string $sandboxId, string $command): array
    {
        return $this->client->exec($sandboxId, $command);
    }

    public function delete(string $sandboxId): void
    {
        $this->client->delete($sandboxId);
    }
}
