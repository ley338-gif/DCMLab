<?php

namespace App\Models;

use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Index ueber content/lessons/<id>/ (Abschnitt 7) -- kein Speicherort fuer
 * Prosa, die bleibt in de.md. lesson_id ist bewusst ein String ("1.5"), nicht
 * die numerische id-Spalte, sonst wird 1.10 zu 1.1 (Abschnitt 4.1).
 *
 * @property int $id
 * @property string $lesson_id
 * @property int $track_id
 * @property int $order
 * @property string $level
 * @property int $duration_minutes
 * @property int $objectives_count
 * @property array<int, string> $requires
 * @property array<int, string> $tools
 * @property array<string, mixed>|null $sandbox
 * @property array<string, mixed>|null $lab
 * @property array<int, string> $glossary_terms
 * @property Carbon|null $tools_checked
 * @property string $status
 * @property array<int, string> $authors
 * @property Carbon|null $content_updated_at
 * @property array<string, string> $title
 * @property array<string, string> $teaser
 * @property string $source_hash
 */
#[Fillable([
    'lesson_id', 'track_id', 'order', 'level', 'duration_minutes', 'objectives_count',
    'requires', 'tools', 'sandbox', 'lab', 'glossary_terms', 'tools_checked', 'status',
    'authors', 'content_updated_at', 'title', 'teaser', 'source_hash',
])]
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'lesson_id';
    }

    protected function casts(): array
    {
        return [
            'requires' => 'array',
            'tools' => 'array',
            'sandbox' => 'array',
            'lab' => 'array',
            'glossary_terms' => 'array',
            'authors' => 'array',
            'title' => 'array',
            'teaser' => 'array',
            'tools_checked' => 'date',
            'content_updated_at' => 'date',
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
     * @return HasMany<LessonProgress, $this>
     */
    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }
}
