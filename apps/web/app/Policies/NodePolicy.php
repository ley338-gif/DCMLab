<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\Node;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\Gate;

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

    /**
     * Zentraler Autorisierungsvertrag fuer JEDE Node-Session-Operation
     * (`NodeController`: show/state/exec/config/action/hint/write-up/flag,
     * ADR 0110/CMS-6d-Haertung). Eine veroeffentlichte Node ist fuer jeden
     * angemeldeten Nutzer offen; eine nicht veroeffentlichte (draft/review/
     * archiviert) nur fuer wer die zugehoerige `type=node`-Activity
     * bearbeiten darf (zugewiesener Autor oder Reviewer/Administrator,
     * `ActivityPolicy::update()`) -- dieselbe Regel wie die Studio-
     * Content-Vorschau (`StudioNodeController::preview()`), keine zweite,
     * eigens erfundene Berechtigung. Da diese Methode die EINZIGE Stelle
     * ist, die je einer Node-Session ueberhaupt gruen licht gibt, folgt
     * daraus die Invariante, die `NodeController::attemptFor()` ausnutzt:
     * jede Session auf einer nicht veroeffentlichten Node ist zwangslaeufig
     * eine autorisierte Vorschau, nie ein echter Lernfortschritt.
     */
    public function view(User $user, Node $node): bool
    {
        if ($node->isPublished()) {
            return true;
        }

        $activity = $this->activityFor($node);

        // Published Content Boundary Hardening: `Gate::forUser($user)`,
        // nicht die ambiente `Gate::allows()`-Variante -- siehe
        // LessonPolicy::view() fuer die ausfuehrliche Begruendung.
        return $activity !== null && Gate::forUser($user)->allows('update', $activity);
    }

    private function activityFor(Node $node): ?Activity
    {
        return Activity::query()->where('type', 'node')->where('key', $node->slug)->first();
    }
}
