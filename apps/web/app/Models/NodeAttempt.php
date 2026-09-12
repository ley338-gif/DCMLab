<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $node_id
 * @property string $engine_session_id
 * @property string $status
 * @property array<int, string> $hints_used
 * @property int|null $points
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $flag_submitted_at
 */
#[Fillable([
    'user_id', 'node_id', 'engine_session_id', 'status', 'hints_used',
    'points', 'started_at', 'flag_submitted_at',
])]
class NodeAttempt extends Model
{
    protected function casts(): array
    {
        return [
            'hints_used' => 'array',
            'started_at' => 'datetime',
            'flag_submitted_at' => 'datetime',
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
     * @return BelongsTo<Node, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
