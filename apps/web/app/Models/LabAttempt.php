<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $activity_id
 * @property string $status
 * @property list<string> $assertions_passed
 * @property int|null $current_sandbox_session_id
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $completed_at
 */
#[Fillable([
    'user_id', 'activity_id', 'status', 'assertions_passed',
    'current_sandbox_session_id', 'started_at', 'completed_at',
])]
class LabAttempt extends Model
{
    protected function casts(): array
    {
        return [
            'assertions_passed' => 'array',
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
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return BelongsTo<SandboxSession, $this>
     */
    public function currentSandboxSession(): BelongsTo
    {
        return $this->belongsTo(SandboxSession::class, 'current_sandbox_session_id');
    }
}
