<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;
use App\Models\UserRole;

/**
 * Berechtigungen auf einer Aktivitaet (ADR 0071, W3): Policies statt
 * Rollen-Ifs im Controller. Ein Reviewer darf jede Aktivitaet bearbeiten
 * (Freigabe setzt das voraus), ein Autor nur die, bei denen er ueber
 * Activity::authorUsers() eingetragen ist. Lernende duerfen nichts
 * bearbeiten -- dafuer gibt es keine explizite Regel, `false` ist der
 * Policy-Default fuer jede nicht behandelte Rolle.
 */
class ActivityPolicy
{
    public function update(User $user, Activity $activity): bool
    {
        if ($user->role === UserRole::Reviewer) {
            return true;
        }

        if ($user->role !== UserRole::Author) {
            return false;
        }

        return $activity->authorUsers()->where('users.id', $user->id)->exists();
    }

    public function publish(User $user, Activity $activity): bool
    {
        return $user->role === UserRole::Reviewer;
    }
}
