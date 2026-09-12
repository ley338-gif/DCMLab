<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Index ueber content/nodes/<slug>/ (Abschnitt 7). Umgebung, Flag-Hash und
 * Hint-Texte bleiben im Dateisystem bzw. bei der Engine.
 *
 * @property int $id
 * @property string $slug
 * @property string $difficulty
 * @property int $points
 * @property string $category
 * @property array<int, string> $skills
 * @property array<int, string> $related_lessons
 * @property int $estimated_minutes
 * @property string $status
 * @property Carbon|null $content_updated_at
 * @property array<string, string> $title
 * @property array<string, string> $scenario_title
 * @property string $source_hash
 */
#[Fillable([
    'slug', 'difficulty', 'points', 'category', 'skills', 'related_lessons',
    'estimated_minutes', 'status', 'content_updated_at', 'title', 'scenario_title', 'source_hash',
])]
class Node extends Model
{
    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'related_lessons' => 'array',
            'title' => 'array',
            'scenario_title' => 'array',
            'content_updated_at' => 'date',
        ];
    }
}
