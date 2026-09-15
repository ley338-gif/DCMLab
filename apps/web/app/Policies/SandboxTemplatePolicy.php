<?php

namespace App\Policies;

use App\Models\SandboxTemplate;
use App\Models\User;
use App\Models\UserRole;

/**
 * Berechtigungen auf Sandbox-Vorlagen (ADR 0096/0098, CMS-2/CMS-3a):
 * "Sandbox Templates werden ausschliesslich von Administratoren angelegt
 * oder freigegeben" (Studio-Auftrag Abschnitt 10) -- `Reviewer` bleibt
 * zusaetzlich berechtigt, wie ueberall sonst im Projekt additiv zu
 * Administrator (siehe UserRole-Klassendoc), statt bestehenden
 * Reviewer-Konten diese Faehigkeit sofort wieder zu entziehen.
 */
class SandboxTemplatePolicy
{
    /**
     * Jeder Autor/Reviewer/Administrator darf sehen, welche Vorlagen es
     * gibt (z. B. fuer einen kuenftigen Sandbox-Editor) -- Lernende nicht.
     */
    public function viewAny(User $user): bool
    {
        return $user->role !== UserRole::Learner;
    }

    public function manage(User $user, ?SandboxTemplate $template = null): bool
    {
        return in_array($user->role, [UserRole::Reviewer, UserRole::Administrator], true);
    }
}
