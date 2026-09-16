<?php

namespace Database\Factories;

use App\Models\Lab;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lab>
 */
class LabFactory extends Factory
{
    protected $model = Lab::class;

    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'difficulty' => 'easy',
            'points' => 10,
            'estimated_minutes' => 15,
            'runtime_template' => null,
            'dataset' => null,
            'assertions' => [],
            // Wie bei Node (ADR 0110-Muster): ein Test, der explizit ein noch
            // nicht freigegebenes Lab braucht, ueberschreibt das gezielt.
            'status' => 'published',
            'title' => ['de' => fake()->words(2, true)],
            'scenario_title' => ['de' => fake()->sentence()],
            'rich_content' => null,
            'source_hash' => fake()->sha256(),
        ];
    }
}
