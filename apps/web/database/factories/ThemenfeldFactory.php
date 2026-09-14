<?php

namespace Database\Factories;

use App\Models\Themenfeld;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Themenfeld>
 */
class ThemenfeldFactory extends Factory
{
    protected $model = Themenfeld::class;

    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'order' => fake()->numberBetween(1, 10),
            'title_key' => 'themenfeld.'.fake()->word().'.title',
            'status' => 'published',
        ];
    }
}
