<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $track_id
 * @property string $status
 * @property array<int, string> $question_ids
 * @property int $current_index
 * @property array<string, array{submitted: mixed, correct: bool}> $answers
 * @property int|null $score_correct
 * @property int|null $score_total
 * @property bool|null $passed
 * @property bool $badge_awarded
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $completed_at
 */
#[Fillable([
    'user_id', 'track_id', 'status', 'question_ids', 'current_index', 'answers',
    'score_correct', 'score_total', 'passed', 'badge_awarded', 'started_at', 'completed_at',
])]
class ExamAttempt extends Model
{
    protected function casts(): array
    {
        return [
            'question_ids' => 'array',
            'answers' => 'array',
            'passed' => 'boolean',
            'badge_awarded' => 'boolean',
            'started_at' => 'datetime',
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
     * @return BelongsTo<Track, $this>
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    public function currentQuestionId(): ?string
    {
        return $this->question_ids[$this->current_index] ?? null;
    }
}
