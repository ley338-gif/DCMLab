<?php

namespace App\Achievements;

use App\Models\AchievementDefinition;
use App\Models\AchievementUnlock;

/**
 * Rueckgabewert von AchievementService::unlock() (Auftrag Abschnitt 5):
 * genau die drei Zustaende already_unlocked | newly_unlocked | not_found,
 * damit Aufrufer ohne weitere DB-Abfrage entscheiden koennen, ob z. B. ein
 * Unlock-Toast gezeigt werden soll.
 */
final class AchievementUnlockResult
{
    private function __construct(
        public readonly AchievementUnlockStatus $status,
        public readonly ?AchievementDefinition $definition = null,
        public readonly ?AchievementUnlock $unlock = null,
    ) {}

    public static function notFound(): self
    {
        return new self(AchievementUnlockStatus::NotFound);
    }

    public static function alreadyUnlocked(AchievementDefinition $definition): self
    {
        return new self(AchievementUnlockStatus::AlreadyUnlocked, $definition);
    }

    public static function newlyUnlocked(AchievementDefinition $definition, AchievementUnlock $unlock): self
    {
        return new self(AchievementUnlockStatus::NewlyUnlocked, $definition, $unlock);
    }

    public function isNewlyUnlocked(): bool
    {
        return $this->status === AchievementUnlockStatus::NewlyUnlocked;
    }
}
