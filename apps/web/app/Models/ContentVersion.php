<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein unveraenderlicher Versions-Snapshot einer Aktivitaet (ADR 0071, W3).
 * Zustandsuebergaenge (draft -> review -> published, Rollback) laufen
 * ausschliesslich ueber App\Content\ContentVersioningService, nie direkt
 * ueber dieses Model, damit die Invarianten (genau eine `is_current`
 * Version je Aktivitaet) an einer Stelle geprueft werden.
 *
 * @property int $id
 * @property int $activity_id
 * @property string $status
 * @property array<string, mixed> $payload
 * @property bool $is_current
 * @property int $created_by
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $published_at
 */
#[Fillable(['activity_id', 'status', 'payload', 'is_current', 'created_by', 'reviewed_by', 'published_at'])]
class ContentVersion extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_current' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
