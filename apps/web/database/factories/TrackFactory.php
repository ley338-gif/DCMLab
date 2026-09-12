<?php

namespace Database\Factories;

use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Track>
 */
class TrackFactory extends Factory
{
    protected $model = Track::class;

    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'order' => fake()->numberBetween(1, 10),
            'title_key' => 'track.'.fake()->word().'.title',
            'level' => fake()->randomElement(['einsteiger', 'aufbau', 'fortgeschritten']),
            'hours' => fake()->numberBetween(1, 10),
            'status' => 'published',
        ];
    }
}
