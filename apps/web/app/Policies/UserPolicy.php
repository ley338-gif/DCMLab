<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserRole;

/**
 * Nutzerverwaltung im Autoren-Panel (ADR 0092): Rollen vergeben und
 * Autor:innen einzelnen Aktivitaeten zuordnen (`activity_authors`). Es
 * gibt keine eigene Admin-Rolle (siehe UserRole) -- Reviewer ist die
 * hoechste bestehende Stufe und uebernimmt diese Zustaendigkeit, analog zu
 * ActivityPolicy::publish().
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Reviewer;
    }

    public function update(User $user, User $target): bool
    {
        return $user->role === UserRole::Reviewer;
    }
}
