<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $description
 * @property string $image
 * @property string $category
 * @property string|null $rarity
 * @property string $scope personal | global (ADR 0077)
 * @property array<string, mixed>|null $unlock_when Deklaratives Auslösekriterium, siehe App\Achievements\AchievementUnlockEvaluator
 * @property int $points
 * @property bool $is_hidden
 * @property int $sort_order
 */
#[Fillable(['slug', 'name', 'description', 'image', 'category', 'rarity', 'scope', 'unlock_when', 'points', 'is_hidden', 'sort_order'])]
class AchievementDefinition extends Model
{
    protected function casts(): array
    {
        return [
            'unlock_when' => 'array',
            'points' => 'integer',
            'is_hidden' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<AchievementUnlock, $this>
     */
    public function unlocks(): HasMany
    {
        return $this->hasMany(AchievementUnlock::class);
    }
}
