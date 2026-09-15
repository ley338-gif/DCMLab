<?php

namespace Database\Factories;

use App\Models\Node;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Node>
 */
class NodeFactory extends Factory
{
    protected $model = Node::class;

    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'difficulty' => 'easy',
            'points' => 10,
            'category' => 'netzwerk',
            'skills' => ['netzwerk'],
            'related_lessons' => [],
            'estimated_minutes' => 15,
            // Wie jede real existierende Node (ADR 0110, CMS-6d Haertung:
            // NodeController zeigt Lernenden nur "published") -- ein Test,
            // der explizit eine noch nicht freigegebene Node braucht,
            // ueberschreibt das gezielt.
            'status' => 'published',
            'content_updated_at' => now(),
            'title' => ['de' => fake()->words(2, true)],
            'scenario_title' => ['de' => fake()->sentence()],
            'source_hash' => fake()->sha256(),
        ];
    }
}
