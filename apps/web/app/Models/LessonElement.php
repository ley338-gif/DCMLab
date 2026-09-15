<?php

namespace App\Models;

use Database\Factories\LessonElementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Eintrag in der geordneten Elementsequenz einer Lektion (ADR 0105,
 * CMS-6b) -- siehe Migrationskommentar fuer das Domainmodell
 * (`type=content|activity`, welche Art Activity sagt `activity->type`
 * selbst).
 *
 * @property int $id
 * @property int $lesson_id
 * @property string $type
 * @property int $position
 * @property int|null $activity_id
 * @property int|null $content_block_id
 */
class LessonElement extends Model
{
    /** @use HasFactory<LessonElementFactory> */
    use HasFactory;

    protected $fillable = ['lesson_id', 'type', 'position', 'activity_id', 'content_block_id'];

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
