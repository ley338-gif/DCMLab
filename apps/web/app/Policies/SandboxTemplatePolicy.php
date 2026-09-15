<?php

namespace App\Policies;

use App\Models\SandboxTemplate;
use App\Models\User;
use App\Models\UserRole;

/**
 * Berechtigungen auf Sandbox-Vorlagen (ADR 0096, CMS-2): "Sandbox Templates
 * werden ausschliesslich von Administratoren angelegt oder freigegeben"
 * (Studio-Auftrag Abschnitt 10) -- solange es die `administrator`-Rolle noch
 * nicht gibt (geplant fuer CMS-3, ADR 0094), uebernimmt `Reviewer` diese
 * Aufgabe, wie ueberall sonst im Projekt der Platzhalter fuer die hoechste
 * Berechtigungsstufe (siehe UserPolicy-Klassendoc).
 */
class SandboxTemplatePolicy
{
    /**
     * Jeder Autor/Reviewer darf sehen, welche Vorlagen es gibt (z. B. fuer
     * einen kuenftigen Sandbox-Editor) -- Lernende nicht.
     */
    public function viewAny(User $user): bool
    {
        return $user->role !== UserRole::Learner;
    }

    public function manage(User $user, ?SandboxTemplate $template = null): bool
    {
        return $user->role === UserRole::Reviewer;
    }
}
