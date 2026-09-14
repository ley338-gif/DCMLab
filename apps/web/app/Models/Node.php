<?php

namespace App\Models;

use Database\Factories\NodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property int|null $themenfeld_id
 * @property string $interaction
 * @property array<int, string> $skills
 * @property array<int, string> $related_lessons
 * @property int $estimated_minutes
 * @property string $status
 * @property Carbon|null $content_updated_at
 * @property array<string, string> $title
 * @property array<string, string> $scenario_title
 * @property string $source_hash
 * @property-read Themenfeld|null $themenfeld
 */
#[Fillable([
    'slug', 'difficulty', 'points', 'category', 'themenfeld_id', 'interaction', 'skills',
    'related_lessons', 'estimated_minutes', 'status', 'content_updated_at', 'title',
    'scenario_title', 'source_hash',
])]
class Node extends Model
{
    /** @use HasFactory<NodeFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

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

    /**
     * @return BelongsTo<Themenfeld, $this>
     */
    public function themenfeld(): BelongsTo
    {
        return $this->belongsTo(Themenfeld::class);
    }

    /**
     * @return HasMany<NodeAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(NodeAttempt::class);
    }
}
