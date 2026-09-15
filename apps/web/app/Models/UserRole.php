<?php

namespace App\Models;

/**
 * Die drei Rollen aus ADR 0071 (W3), plus `Administrator` seit ADR 0098
 * (CMS-3a). `Learner` ist der Default fuer jedes neue Konto -- Registrierung
 * bleibt unveraendert, niemand muss beim Anlegen eine Rolle waehlen.
 *
 * `Administrator` erweitert `Reviewer` additiv, ersetzt ihn nicht: jede
 * bisher Reviewer-gegatete Faehigkeit (Nutzerverwaltung, Sandbox-Vorlagen,
 * Freigaben) bleibt fuer Reviewer bestehen, Administrator kann zusaetzlich
 * alles, was der Studio-Auftrag der hoechsten Stufe zuweist (Abschnitt 18).
 * Grund: es existiert noch kein einziges Administrator-Konto in einer echten
 * Umgebung -- eine Verengung auf "nur Administrator darf X" haette bestehende
 * Reviewer sofort ausgesperrt (docs/adr/0098-administrator-rolle.md).
 */
enum UserRole: string
{
    case Learner = 'learner';
    case Author = 'author';
    case Reviewer = 'reviewer';
    case Administrator = 'administrator';
}
