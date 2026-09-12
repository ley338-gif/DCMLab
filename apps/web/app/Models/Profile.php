<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $public_slug
 * @property string $rank
 * @property int $points
 * @property array<string, int> $skill_vector
 * @property bool $leaderboard_opt_in
 */
#[Fillable(['user_id', 'public_slug', 'rank', 'points', 'skill_vector', 'leaderboard_opt_in'])]
class Profile extends Model
{
    protected function casts(): array
    {
        return [
            'skill_vector' => 'array',
            'leaderboard_opt_in' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
