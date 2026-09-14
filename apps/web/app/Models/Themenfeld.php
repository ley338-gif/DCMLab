<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Index ueber content/themenfelder.yml (Abschnitt 13) -- kein Speicherort
 * fuer Prosa. Track-uebergreifende Ebene ueber Track; aktuell existiert
 * genau ein Themenfeld (dicom).
 *
 * @property int $id
 * @property string $slug
 * @property int $order
 * @property string $title_key
 * @property string $status
 */
#[Fillable(['slug', 'order', 'title_key', 'status'])]
class Themenfeld extends Model
{
    protected $table = 'themenfelder';

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<Track, $this>
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class)->orderBy('order');
    }
}
