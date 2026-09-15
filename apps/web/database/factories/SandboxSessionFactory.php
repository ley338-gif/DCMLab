<?php

namespace Database\Factories;

use App\Models\SandboxSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SandboxSession>
 */
class SandboxSessionFactory extends Factory
{
    protected $model = SandboxSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'runtime_provider' => 'docker',
            'runtime_instance_id' => fake()->uuid(),
            'status' => 'running',
            'started_at' => now(),
            'last_activity_at' => now(),
        ];
    }
}
