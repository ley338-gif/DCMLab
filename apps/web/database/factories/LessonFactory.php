<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    public function definition(): array
    {
        return [
            'lesson_id' => (string) fake()->unique()->randomFloat(1, 1, 9),
            'track_id' => Track::factory(),
            'order' => fake()->numberBetween(0, 10),
            'level' => fake()->randomElement(['einsteiger', 'aufbau', 'fortgeschritten']),
            'duration_minutes' => fake()->numberBetween(5, 15),
            'objectives_count' => 3,
            'requires' => [],
            'tools' => [],
            'sandbox' => null,
            'related_node' => null,
            'glossary_terms' => [],
            'tools_checked' => now(),
            // Wie jede real veroeffentlichte Lektion (ADR 0119, analog zu
            // NodeFactory/ADR 0110: LessonController zeigt Lernenden nur
            // "published") -- ein Test, der explizit eine noch nicht
            // freigegebene Lektion braucht, ueberschreibt das gezielt.
            'status' => 'published',
            'legacy_authors' => [],
            'content_updated_at' => now(),
            'title' => ['de' => fake()->sentence()],
            'teaser' => ['de' => fake()->sentence()],
            'source_hash' => fake()->sha256(),
        ];
    }
}
