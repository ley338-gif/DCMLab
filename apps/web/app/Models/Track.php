<?php

namespace App\Models;

use Database\Factories\TrackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Index ueber content/tracks.yml (Abschnitt 7) -- kein Speicherort fuer Prosa.
 *
 * @property int $id
 * @property int|null $themenfeld_id
 * @property string $slug
 * @property int $order
 * @property string $title_key
 * @property string $level
 * @property int $hours
 * @property string $status
 * @property-read int $completed_lessons_count Nur gesetzt nach withCount('lessons as completed_lessons_count' => ...)
 * @property-read Themenfeld|null $themenfeld
 */
#[Fillable(['themenfeld_id', 'slug', 'order', 'title_key', 'level', 'hours', 'status'])]
class Track extends Model
{
    /** @use HasFactory<TrackFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Themenfeld, $this>
     */
    public function themenfeld(): BelongsTo
    {
        return $this->belongsTo(Themenfeld::class);
    }

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('order');
    }
}
