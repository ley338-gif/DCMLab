<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserRole;

/**
 * Nutzerverwaltung im Autoren-Panel (ADR 0092): Rollen vergeben und
 * Autor:innen einzelnen Aktivitaeten zuordnen (`activity_authors`).
 * Reviewer und Administrator (ADR 0098, CMS-3a) duerfen das gleichermassen
 * -- Administrator ersetzt Reviewer hier bewusst nicht, siehe UserRole
 * Klassendoc.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Reviewer, UserRole::Administrator], true);
    }

    public function update(User $user, User $target): bool
    {
        return in_array($user->role, [UserRole::Reviewer, UserRole::Administrator], true);
    }
}
