<?php

namespace App\Models;

use Database\Factories\TrackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Index ueber content/tracks.yml (Abschnitt 7) -- kein Speicherort fuer Prosa.
 *
 * @property int $id
 * @property string $slug
 * @property int $order
 * @property string $title_key
 * @property string $level
 * @property int $hours
 * @property string $status
 */
#[Fillable(['slug', 'order', 'title_key', 'level', 'hours', 'status'])]
class Track extends Model
{
    /** @use HasFactory<TrackFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('order');
    }
}
