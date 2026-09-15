<?php

namespace App\Policies;

use App\Models\Node;
use App\Models\User;
use App\Models\UserRole;

/**
 * Berechtigungen auf der Node-Ressource selbst (ADR 0109, CMS-6d Teil 3):
 * Anlegen, Duplizieren, Archivieren/Wiederherstellen und die Themenfeld-
 * Zuordnung sind -- wie bei Track (ADR 0100) -- strukturelle Eingriffe ohne
 * eigenen `activity_authors`-Bezug und bleiben deshalb Reviewer/
 * Administrator vorbehalten. Das eigentliche Content-Bearbeiten (Entwurf
 * speichern, einreichen, freigeben) laeuft weiterhin ueber `ActivityPolicy`
 * gegen die `type=node`-Activity -- dort darf auch ein zugewiesener Autor
 * ran, siehe `StudioNodeController`. Jede Nicht-Lernende-Rolle darf die
 * Liste sehen.
 */
class NodePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== UserRole::Learner;
    }

    public function manage(User $user, ?Node $node = null): bool
    {
        return in_array($user->role, [UserRole::Reviewer, UserRole::Administrator], true);
    }
}
