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
 * Index ueber content/nodes/<slug>/ (Abschnitt 7). Seit ADR 0107 (CMS-6d)
 * traegt `body` denselben Fliesstext, den `NodeSections::parse()` in
 * Briefing/Hints/Write-up zerlegt (`hints` nur die id/cost-Metadaten dazu)
 * -- Runtime-/Sicherheitsparameter (Engine-Konfiguration, Flag-Validierung,
 * Sandbox-Template) bleiben bewusst ausserhalb dieses Modells.
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
 * @property string|null $body Seit ADR 0107 (CMS-6d) von content:sync aus de.md befuellt
 * @property list<array{id: string, cost: int}>|null $hints Seit ADR 0107 (CMS-6d) von content:sync aus node.yml befuellt
 * @property string $source_hash
 * @property-read Themenfeld|null $themenfeld
 */
#[Fillable([
    'slug', 'difficulty', 'points', 'category', 'themenfeld_id', 'interaction', 'skills',
    'related_lessons', 'estimated_minutes', 'status', 'content_updated_at', 'title',
    'scenario_title', 'body', 'hints', 'source_hash',
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
            'hints' => 'array',
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
