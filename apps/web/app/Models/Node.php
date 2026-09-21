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
 * @property array<string, mixed>|null $rich_content Seit ADR 0115 (CMS-7d.1) -- der node_content-Umschlag (Briefing/Hints je Id/Write-up einzeln, ADR 0115), bis zum Backfill (CMS-7d.2) durchgehend null; `body` bleibt bis zum Cutover (CMS-7d.3) die Rendering-Quelle
 * @property string $source_hash
 * @property-read Themenfeld|null $themenfeld
 */
#[Fillable([
    'slug', 'difficulty', 'points', 'category', 'themenfeld_id', 'interaction', 'skills',
    'related_lessons', 'estimated_minutes', 'status', 'content_updated_at', 'title',
    'scenario_title', 'body', 'hints', 'rich_content', 'source_hash',
])]
class Node extends Model
{
    /** @use HasFactory<NodeFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Einzige Stelle, die `status === 'published'` ausbuchstabiert (ADR
     * 0110/CMS-6d-Haertung): `NodePolicy::view()` und `NodeController`
     * lesen diese Methode statt den String-Vergleich zu wiederholen -- die
     * Invariante "nicht veroeffentlicht -> jede Session ist eine Vorschau"
     * (siehe `NodeController::attemptFor()`) haengt direkt daran.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'related_lessons' => 'array',
            'title' => 'array',
            'scenario_title' => 'array',
            'hints' => 'array',
            'rich_content' => 'array',
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
