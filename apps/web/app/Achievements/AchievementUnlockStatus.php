<?php

namespace App\Achievements;

enum AchievementUnlockStatus: string
{
    case AlreadyUnlocked = 'already_unlocked';
    case NewlyUnlocked = 'newly_unlocked';
    case NotFound = 'not_found';
}
