# 0107 — Node-Fliesstext wird DB-gefuehrt (CMS-6d, Teil 1)

## Status

Angenommen, 15.09.2026.

## Kontext

CMS-6d soll Node zum ersten Mal einen echten Browser-Editor geben --
aber nicht als "UI ueber den Altbestand", sondern als denselben
strukturellen Umbau, den Lesson bereits durchlaufen hat (ADR 0101/0102):
DB wird Quelle der Wahrheit fuer autorenrelevanten Inhalt, `content/`
wird nicht mehr beim Freigeben beschrieben, Learner-Ansicht konsumiert
die DB.

`Node`s eigener Klassenkommentar sagte bisher woertlich: "Index ueber
content/nodes/<slug>/... Umgebung, Flag-Hash und Hint-Texte bleiben im
Dateisystem." `NodeActivity::deserialize()` holte den Body weiterhin
live aus `ContentRepository`; `serialize()` ignoriert `$draft`
vollstaendig (schreibt immer den unveraenderten Ist-Zustand zurueck) --
Node ist der einzige Aktivitaetstyp, bei dem selbst die Grundlage fuer
einen Autoren-Entwurf noch fehlt.

Diese ADR deckt bewusst nur den ersten, dem Lesungspfad entsprechenden
Schritt ab (analog zu ADR 0101 fuer Lesson) -- der Studio-Editor selbst,
der DB-direkte Freigabe-Pfad und die Runtime-/Content-Trennung sind
separate, groessere Folge-ADRs.

## Entscheidung

**Neue Spalten** `nodes.body` (text, nullable) und `nodes.hints` (json,
nullable). `body` traegt denselben vollstaendigen Markdown-Text, den
`NodeSections::parse()` bereits in Briefing/Hints/Write-up zerlegt --
reine Text-Funktion, kein Datei-I/O, funktioniert unveraendert gegen den
DB-Wert. `hints` sind nur die Metadaten (`id`/`cost`) aus `node.yml`s
`hints:`-Block, analog zu `lessons.quiz` (ADR 0104) -- der Hint-**Text**
steckt bereits in `body`.

**Bewusst nicht Teil dieser Spalten**: `environment` (Engine-
Konfiguration: Hosts, Templates, Platzhalter), `flag` (Hash/Validierung).
Diese bleiben Runtime-/Sicherheitsparameter und damit ausserhalb des
Autorenmodells -- siehe Zielbild in `docs/studio-architecture-plan.md`
(Node Content vs. Node Runtime), das dieser ADR folgt.

`content:sync` befuellt beide neuen Spalten aus derselben Datei, die
ohnehin schon fuer `title`/`scenario_title` gelesen wird.
`NodeActivity::deserialize()`, `NodeController::show()`/`useHint()`/
`viewWriteUp()` bevorzugen jetzt die DB -- `ContentRepository` bleibt
Fallback fuer eine Node, deren naechster Sync-Lauf noch aussteht
(derselbe Mechanismus wie bei Lesson). `environment`/`templates`/
`placeholders` bleiben unveraendert datei-gefuehrt.

## Konsequenzen

- Nach dem naechsten `content:sync`-Lauf lesen alle real existierenden
  Nodes ihren Briefing-/Hint-/Write-up-Text aus der DB, nicht mehr live
  von der Platte.
- **Bewusst nicht Teil dieser ADR** (CMS-6d, weitere Teile):
  - `NodeActivity::serialize()`/`validate()` werten `$draft` weiterhin
    nicht aus -- ein DB-direkter `NodeContentPublisher`
    (Gegenstueck zu `LessonContentPublisher`/`QuizContentPublisher`)
    folgt separat, bevor ein echter Autoren-Entwurf moeglich ist.
  - `/studio/nodes` (Uebersicht, Anlegen, Bearbeiten, Draft/Review/
    Publish, Preview, Archive/Restore, Duplicate) existiert noch nicht.
  - Hints als eigenstaendig editierbarer didaktischer Inhalt (Text +
    Punktabzug) im Studio-Editor -- der Text steckt bereits in `body`,
    eine editierbare Oberflaeche dafuer fehlt noch.
  - Sichtbarkeit einer Studio-erzeugten Node im Lesson-Composer-Katalog.

## Verifikation

- Alle 490 Tests (3 neu: DB-Vorrang in `NodeControllerTest`, Body/Hints-
  Backfill in `ContentSyncTest` gegen echten Bestand), PHPStan Level 7
  und `pint --test` sind gruen.
