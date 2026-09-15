<?php

namespace Database\Factories;

use App\Models\SandboxTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SandboxTemplate>
 */
class SandboxTemplateFactory extends Factory
{
    protected $model = SandboxTemplate::class;

    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'runtime_provider' => 'docker',
            'status' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => 'published']);
    }
}
