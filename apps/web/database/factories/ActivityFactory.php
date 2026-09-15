<?php

namespace Database\Factories;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        return [
            'type' => 'lesson',
            'key' => fake()->unique()->slug(2),
            'order' => 0,
            'status' => 'draft',
            'legacy_authors' => [],
            'title' => ['de' => fake()->words(2, true)],
            'teaser' => ['de' => fake()->sentence()],
            'source_hash' => fake()->sha256(),
        ];
    }
}
