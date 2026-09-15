<?php

namespace App\Policies;

use App\Models\Track;
use App\Models\User;
use App\Models\UserRole;

/**
 * Berechtigungen auf Tracks (ADR 0100, CMS-4b). Track ist -- anders als eine
 * Activity -- nicht an einzelne Autor:innen zuweisbar (kein
 * `activity_authors`-Aequivalent); Anlegen/Bearbeiten/Archivieren bleibt
 * daher Reviewer/Administrator vorbehalten, wie schon bei
 * SandboxTemplatePolicy (additiv, siehe UserRole-Klassendoc). Jede
 * Nicht-Lernende-Rolle darf die Liste sehen.
 */
class TrackPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== UserRole::Learner;
    }

    public function manage(User $user, ?Track $track = null): bool
    {
        return in_array($user->role, [UserRole::Reviewer, UserRole::Administrator], true);
    }
}
