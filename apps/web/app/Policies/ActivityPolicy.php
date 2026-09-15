<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;
use App\Models\UserRole;

/**
 * Berechtigungen auf einer Aktivitaet (ADR 0071, W3): Policies statt
 * Rollen-Ifs im Controller. Reviewer und Administrator (ADR 0098, CMS-3a)
 * duerfen jede Aktivitaet bearbeiten/freigeben, ein Autor nur die, bei denen
 * er ueber Activity::authorUsers() eingetragen ist. Lernende duerfen nichts
 * bearbeiten -- dafuer gibt es keine explizite Regel, `false` ist der
 * Policy-Default fuer jede nicht behandelte Rolle.
 */
class ActivityPolicy
{
    public function update(User $user, Activity $activity): bool
    {
        if (in_array($user->role, [UserRole::Reviewer, UserRole::Administrator], true)) {
            return true;
        }

        if ($user->role !== UserRole::Author) {
            return false;
        }

        return $activity->authorUsers()->where('users.id', $user->id)->exists();
    }

    public function publish(User $user, Activity $activity): bool
    {
        return in_array($user->role, [UserRole::Reviewer, UserRole::Administrator], true);
    }
}
