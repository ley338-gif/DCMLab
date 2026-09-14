<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $lesson_id
 * @property string $question_id
 * @property int $repetitions
 * @property float $ease_factor
 * @property int $interval_days
 * @property CarbonImmutable|null $due_at
 * @property CarbonImmutable|null $last_reviewed_at
 * @property string|null $last_result
 */
#[Fillable([
    'user_id', 'lesson_id', 'question_id', 'repetitions', 'ease_factor',
    'interval_days', 'due_at', 'last_reviewed_at', 'last_result',
])]
class QuizReview extends Model
{
    protected function casts(): array
    {
        return [
            'ease_factor' => 'float',
            'due_at' => 'datetime',
            'last_reviewed_at' => 'datetime',
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
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
