<?php

namespace App\Activities;

/**
 * Merkmalsdeklaration eines Aktivitaetstyps, nach dem Vorbild von Moodles
 * `[modname]_supports()` (ADR 0072): der Kern fragt ein Modul nach seinen
 * Faehigkeiten, statt ueber den Typ selbst zu verzweigen.
 */
final readonly class ActivitySupports
{
    public function __construct(
        /** Meldet ein Abschluss dieser Aktivitaet eine Punktzahl? */
        public bool $isGraded,
        /** Gibt es fuer diesen Typ ueberhaupt einen "abgeschlossen"-Zustand? */
        public bool $tracksCompletion,
        /** Braucht die Lernendenansicht einen laufenden Container/eine Engine-Sitzung? */
        public bool $needsContainer,
        /** Kann dieser Typ ueber den Autoren-Editor (ADR 0071) angelegt werden? */
        public bool $authorable,
        /** Ist eine Instanz frei platzierbar, oder an eine andere Aktivitaet gebunden? */
        public bool $freelyPlaceable,
    ) {}
}
