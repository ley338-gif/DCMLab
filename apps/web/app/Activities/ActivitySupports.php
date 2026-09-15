<?php

namespace App\Activities;

/**
 * Merkmalsdeklaration eines Aktivitaetstyps, nach dem Vorbild von Moodles
 * `[modname]_supports()` (ADR 0072): der Kern fragt ein Modul nach seinen
 * Faehigkeiten, statt ueber den Typ selbst zu verzweigen.
 *
 * Die fuenf zusaetzlichen Felder ab `runtimeType` (ADR 0094/CMS-1) ergaenzen
 * die urspruengliche Deklaration um die im Studio-Auftrag genannten
 * Capabilities. Additive Erweiterung, keine Neukonzeption -- die ersten
 * fuenf Felder und ihre Bedeutung bleiben unveraendert.
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
        /** Welche Laufzeitumgebung wird benoetigt (z. B. "container"), oder null wenn keine noetig ist. */
        public ?string $runtimeType = null,
        /** Kann ein Entwurf vor der Freigabe als Vorschau angezeigt werden? Noch keine Instanz unterstuetzt das (CMS-10). */
        public bool $previewable = false,
        /** Kann eine Instanz dieses Typs dupliziert werden? Noch nicht gebaut (CMS-4). */
        public bool $duplicatable = false,
        /** Nimmt eine eigene Instanz tatsaechlich am content_versions-Entwurfszyklus teil, d. h. wertet ihr eigenes serialize($draft) den Entwurf aus? */
        public bool $versionable = false,
        /** Kann dieselbe Instanz von mehreren anderen Aktivitaeten/Kontexten referenziert werden? */
        public bool $reusable = false,
    ) {}
}
