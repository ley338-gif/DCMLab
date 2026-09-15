<?php

namespace App\Services;

use RuntimeException;

/**
 * Loest einen `sandbox_templates.runtime_provider`-String in seine
 * RuntimeProviderContract-Instanz auf (ADR 0096, CMS-2). Nach demselben
 * Registry-Muster wie ActivityRegistry, nur (noch) mit genau einem
 * eingetragenen Provider -- match() statt Closure-Map ist hier bewusst
 * einfacher gehalten, analog zu EngineClientResolver.
 */
final readonly class RuntimeProviderRegistry
{
    public function __construct(
        private DockerRuntimeProvider $docker,
    ) {}

    public function for(string $runtimeProvider): RuntimeProviderContract
    {
        return match ($runtimeProvider) {
            'docker' => $this->docker,
            default => throw new RuntimeException("Kein Runtime-Provider registriert fuer \"{$runtimeProvider}\"."),
        };
    }
}
