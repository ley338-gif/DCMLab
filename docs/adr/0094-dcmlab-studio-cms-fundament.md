# 0094 — DCMLab Studio: CMS-Fundament und Phasenplan

## Status

Angenommen, 15.09.2026.

## Kontext

Der Betreiber hat entschieden, DCMLab von einem ueberwiegend
dateibasierten Content-System zu einem vollstaendigen browserbasierten
LMS/LCMS/CMS ("DCMLab Studio") weiterzuentwickeln -- eine explizite
Umkehr der zu Beginn der LMS-Phase ausgeschlossenen CMS-Option. Ein
vollstaendiger Architektur-Audit (`docs/studio-architecture-plan.md`,
Phase CMS-0) zeigt: `docs/adr/0071-content-speicherort-autorenschicht.md`
hatte "Die Datenbank wird die Autoren-Wahrheit" bereits fuer Metadaten
entschieden, aber `content/**` wird bis heute bei jedem Lern-/
Autoren-Request live fuer Fliesstext gelesen (`ContentRepository`,
27 Aufrufstellen) -- die "eine Richtung, nie zurueckgelesen"-Regel ist
nur teilweise umgesetzt. Der neue Auftrag geht zudem deutlich ueber
ADR 0071 hinaus: Modulsystem mit Capability-Deklaration, eigenstaendige
Sandbox-Runtime-Abstraktion, Lesson Composer, WYSIWYG-Block-Editor,
CMS-Funktionen (Pages/Navigation/Media), vierte Rolle
(`administrator`), gemeinsamer `/studio`-Adminbereich.

Der Audit fand zugleich, dass die bestehende Activity-Architektur
(`ActivityContract`/`ActivityRegistry`/`ActivitySupports`, ADR 0072)
bereits weitgehend dem im neuen Auftrag verlangten Modulsystem
entspricht -- keine Typ-Switch-Technical-Debt im Kernpfad -- und die
Sandbox-Sicherheitsmassnahmen (`services/sandbox`) bereits stark sind.
Die eigentliche Luecke liegt in der Kopplung (Sandbox an `Lesson`,
Quiz ohne eigene `activities`-Zeile) und im unvollstaendig
umgesetzten "DB ist Wahrheit"-Versprechen fuer Fliesstext.

## Entscheidung

DCMLab Studio wird in elf nummerierten Phasen (CMS-0 bis CMS-11)
umgesetzt, wie in `docs/studio-architecture-plan.md` Abschnitt 11
detailliert. Kernentscheidungen:

- **Datenbank wird alleinige Laufzeitquelle** fuer alle Inhaltstypen,
  inklusive Rich Content als strukturierter Block-Baum (nicht
  Markdown). `content/` wird auf Import/Export/Demo/Seed/Backup
  reduziert, nie mehr automatisch im Laufzeitpfad zurueckgelesen --
  keine dauerhafte bidirektionale Synchronisation.
- Die bestehende `ActivityContract`/`ActivityRegistry`/
  `ActivitySupports`-Architektur wird **nicht ersetzt**, nur additiv
  um weitere Capabilities erweitert (CMS-1).
- Sandbox wird auf ein Drei-Ebenen-Modell
  (`SandboxTemplate`/`SandboxActivity`/`SandboxSession`) hinter einer
  `RuntimeProviderContract`-Abstraktion umgestellt (CMS-2), ohne die
  bereits vorhandenen Container-Sicherheitsmassnahmen in
  `services/sandbox` neu zu bauen.
- Ein neuer `administrator`-Rollenwert (reine String-Erweiterung,
  keine Migration noetig) und ein `/studio`-Adminbereich mit
  gemeinsamer Layout-/Komponentenbibliothek werden eingefuehrt
  (CMS-3), `/author/*` bleibt waehrend der Migration funktionsfaehig.
- Rich-Content-/WYSIWYG-Editor kommt bewusst **zuletzt** (CMS-7), erst
  nachdem DB-Wahrheit, Modulsystem, Studio, Tracks, Lessons und Lesson
  Composer stehen -- wie vom Betreiber selbst vorgegeben.
- Kein Drittanbieter-Plugin-System, kein generisches
  Page-Builder-/Theme-System -- nur ein internes, klar definiertes
  Modulsystem (Phase 1).
- Jede Phase wird bei Umsetzung mit einer eigenen ADR dokumentiert, die
  auf dieses Dokument und `docs/studio-architecture-plan.md` verweist.

## Konsequenzen

- `docs/studio-architecture-plan.md` ist die lebende Planungsgrundlage
  (Zielarchitektur, Datenbankschema-Ausblick, Migrationsstrategie,
  Kompatibilitaetsrisiken, Sicherheitsbetrachtung) -- wird bei Bedarf
  pro Phase aktualisiert, nicht dupliziert.
- Der bereits offene Punkt "`content/` read-only gemountet, Publish
  schlaegt lokal fehl" (`docs/offene-fragen.md`) sollte moeglichst vor
  CMS-1 behoben werden, da er sonst jede neue Studio-Funktion gegen
  denselben kaputten Pfad testen wuerde.
- Zwei beim Audit gefundene, vom Studio-Umbau unabhaengige
  Sandbox-Haertungspunkte (Generator-Container laufen als `root`, kein
  `exec_command`-Timeout) werden separat als offene Fragen
  nachverfolgt, nicht Teil dieses ADRs.
- Noch keine Codeaenderung erfolgt durch dieses ADR -- CMS-1 beginnt
  erst nach Rueckmeldung des Betreibers zum Plan.

## Verifikation

- Vollstaendiger, dreigeteilter Architektur-Audit ueber
  Activity/Sandbox, Content-Pipeline, Rollen/Routen/Vue/Docker,
  Befunde in `docs/studio-architecture-plan.md` mit Datei:Zeile-Belegen.
- Keine Tests betroffen (reine Dokumentations-/Planungs-Aenderung).
