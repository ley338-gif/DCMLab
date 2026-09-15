<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $achievement_definition_id
 * @property int|null $activity_id Nur bei scope="global" gesetzt (ADR 0077) -- der einmalige globale Gewinner je Aktivität
 * @property CarbonImmutable $unlocked_at
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['user_id', 'achievement_definition_id', 'activity_id', 'unlocked_at', 'metadata'])]
class AchievementUnlock extends Model
{
    protected function casts(): array
    {
        return [
            'unlocked_at' => 'datetime',
            'metadata' => 'array',
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
     * @return BelongsTo<AchievementDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(AchievementDefinition::class, 'achievement_definition_id');
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
