<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dauerhaftes Ergebnis einer abgeschlossenen Aktivitaet (ADR 0072) -- die
 * einzige Quelle, gegen die Punkte, Rang, Skill-Radar und
 * Achievement-Ausloeser rechnen. Der laufende Versuchszustand (z. B.
 * NodeAttempt::hints_used, ExamAttempt::answers) bleibt typspezifisch und
 * wird hier nicht dupliziert.
 *
 * @property int $id
 * @property int $user_id
 * @property int $activity_id
 * @property bool $completed
 * @property int|null $score
 * @property int|null $max_score
 * @property array<int, string> $skills
 * @property CarbonImmutable|null $completed_at
 */
#[Fillable(['user_id', 'activity_id', 'completed', 'score', 'max_score', 'skills', 'completed_at'])]
class ActivityProgress extends Model
{
    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'skills' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
