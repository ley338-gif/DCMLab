<?php

namespace App\Models;

/**
 * Die drei Rollen aus ADR 0071 (W3). `Learner` ist der Default fuer jedes
 * neue Konto -- Registrierung bleibt unveraendert, niemand muss beim
 * Anlegen eine Rolle waehlen.
 */
enum UserRole: string
{
    case Learner = 'learner';
    case Author = 'author';
    case Reviewer = 'reviewer';
}
