<?php

namespace App\Policies;

use App\Models\Lab;
use App\Models\User;
use App\Models\UserRole;

/**
 * Berechtigungen auf der Lab-Ressource selbst (CMS-8c, nach NodePolicy-
 * Vorbild): Anlegen, Archivieren/Wiederherstellen sind strukturelle
 * Eingriffe ohne eigenen `activity_authors`-Bezug und bleiben deshalb
 * Reviewer/Administrator vorbehalten. Das eigentliche Content-Bearbeiten
 * (Entwurf speichern, einreichen, freigeben) laeuft weiterhin ueber
 * `ActivityPolicy` gegen die `type=lab`-Activity -- dort darf auch ein
 * zugewiesener Autor ran, siehe `StudioLabController`. Jede
 * Nicht-Lernende-Rolle darf die Liste sehen.
 */
class LabPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== UserRole::Learner;
    }

    public function manage(User $user, ?Lab $lab = null): bool
    {
        return in_array($user->role, [UserRole::Reviewer, UserRole::Administrator], true);
    }
}
