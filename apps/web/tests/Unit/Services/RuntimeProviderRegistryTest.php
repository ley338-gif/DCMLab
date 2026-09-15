<?php

namespace Tests\Unit\Services;

use App\Services\DockerRuntimeProvider;
use App\Services\RuntimeProviderRegistry;
use App\Services\SandboxClientContract;
use RuntimeException;
use Tests\TestCase;

/**
 * ADR 0096 (CMS-2): loest einen sandbox_templates.runtime_provider-String
 * in seine RuntimeProviderContract-Instanz auf, analog zu
 * EngineClientResolver/ActivityRegistry.
 */
class RuntimeProviderRegistryTest extends TestCase
{
    public function test_it_resolves_the_docker_provider(): void
    {
        $client = $this->createMock(SandboxClientContract::class);
        $docker = new DockerRuntimeProvider($client);
        $registry = new RuntimeProviderRegistry($docker);

        $this->assertSame($docker, $registry->for('docker'));
    }

    public function test_it_throws_for_an_unknown_provider(): void
    {
        $registry = new RuntimeProviderRegistry(
            new DockerRuntimeProvider($this->createMock(SandboxClientContract::class)),
        );

        $this->expectException(RuntimeException::class);

        $registry->for('podman');
    }

    public function test_docker_runtime_provider_delegates_every_call_to_the_sandbox_client(): void
    {
        $client = $this->createMock(SandboxClientContract::class);
        $client->expects($this->once())->method('create')->with('42', 'ct-head-01')->willReturn(['status' => 'running']);
        $client->expects($this->once())->method('state')->with('sb-1')->willReturn(['status' => 'running']);
        $client->expects($this->once())->method('exec')->with('sb-1', 'echo hi')->willReturn(['stdout' => 'hi']);
        $client->expects($this->once())->method('delete')->with('sb-1');

        $provider = new DockerRuntimeProvider($client);

        $this->assertSame(['status' => 'running'], $provider->create('42', 'ct-head-01'));
        $this->assertSame(['status' => 'running'], $provider->state('sb-1'));
        $this->assertSame(['stdout' => 'hi'], $provider->exec('sb-1', 'echo hi'));
        $provider->delete('sb-1');
    }
}
