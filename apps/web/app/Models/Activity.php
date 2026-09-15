<?php

namespace App\Models;

use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Verzeichniseintrag einer Aktivitaet (ADR 0072). `type` + `key` sind der
 * fachliche Schluessel (siehe Migration); die eigentlichen Nutzdaten bleiben
 * bei der jeweiligen Fachtabelle (Lesson, Node, ...) bzw. bei content/.
 * `App\Activities\ActivityRegistry` loest einen Datensatz in die passende
 * `App\Activities\ActivityContract`-Instanz auf.
 *
 * @property int $id
 * @property string $type
 * @property string $key
 * @property int|null $track_id
 * @property int $order
 * @property string $status
 * @property array<int, string> $legacy_authors Freitext aus content:sync (docs/offene-fragen.md) -- nie auf ein Nutzerkonto aufgeloest, fuer echte Rechteprüfung siehe authorUsers()
 * @property array<string, string>|null $title
 * @property array<string, string>|null $teaser
 * @property string|null $source_hash
 * @property-read Track|null $track
 */
#[Fillable([
    'type', 'key', 'track_id', 'order', 'status', 'legacy_authors', 'title', 'teaser', 'source_hash',
])]
class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'legacy_authors' => 'array',
            'title' => 'array',
            'teaser' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Track, $this>
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    /**
     * @return HasMany<ActivityProgress, $this>
     */
    public function progress(): HasMany
    {
        return $this->hasMany(ActivityProgress::class);
    }

    /**
     * Die echte Autoren-Beziehung (ADR 0071, W3), gegen die ActivityPolicy
     * prueft -- bewusst nicht "authors" genannt, das ist die
     * Freitext-Historienspalte `legacy_authors` oben.
     *
     * @return BelongsToMany<User, $this>
     */
    public function authorUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'activity_authors');
    }

    /**
     * @return HasMany<ContentVersion, $this>
     */
    public function contentVersions(): HasMany
    {
        return $this->hasMany(ContentVersion::class);
    }
}
