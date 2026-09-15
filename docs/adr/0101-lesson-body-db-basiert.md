# 0101 — Lesson-Fliesstext wird DB-gefuehrt (CMS-5a)

## Status

Angenommen, 15.09.2026.

## Kontext

ADR 0094 legt Phase CMS-5 ("Lesson Management") fest: "Lesson soll
vollstaendig DB-basiert werden. Aktuelle LessonEditor-
Dateisystemabhaengigkeit abloesen." Das ist der zentrale, im CMS-0-Audit
(`docs/studio-architecture-plan.md` Abschnitt 2.1) dokumentierte Befund:
ADR 0071 hatte "Die Datenbank wird die Autoren-Wahrheit" bereits fuer
Metadaten (title/teaser/level/duration_minutes/...) umgesetzt, aber
`LessonController::show()` liest den eigentlichen Lektionstext (`body`,
`objectives`) bis heute live aus `content/lessons/<id>/de.md` --
`ContentRepository` wird bei **jedem** Seitenaufruf einer Lektion
angefragt.

Die volle CMS-5-Liste (komplettes CRUD, Autoren-Editor auf DB
umgestellt, `LessonEditorController`s Dateisystemabhaengigkeit
tatsaechlich abgeloest) ist zu gross fuer eine Aenderung -- sie beruehrt
`LessonActivity::serialize()`/`ContentWriter`/`ContentVersioningService`
und den gesamten Freigabe-Kreislauf, der weiterhin fuer alle anderen
Autoren-Editoren (Quiz, Sandbox-Konfiguration innerhalb der Lektion)
gebraucht wird. Diese ADR deckt bewusst nur den risikoarmen,
**lesenden** Teil ab (**CMS-5a**): den Lern-Ansicht-Pfad von der Datei
loesen. Der Autoren-Editor-/Publish-Pfad (**CMS-5b**, deutlich groesser)
folgt separat.

## Entscheidung

`lessons` bekommt zwei neue, additive Spalten: `body` (text, nullable)
und `objectives` (json, nullable) -- `title`/`teaser` existieren als
DB-Spalten bereits seit ADR 0071, wurden aber trotzdem nie fuer die
Anzeige genutzt (siehe Fund unten). `ContentSync::syncLessons()` befuellt
beide neuen Spalten aus **derselben** Datei, die es ohnehin schon fuer
title/teaser liest (`de.md`) -- kein zusaetzlicher Lesevorgang, keine
neue Datenquelle.

`LessonController::show()` bevorzugt jetzt ueberall die DB:
`$lesson->body ?? $lessonContent['body']`,
`$lesson->objectives ?? $lessonContent['frontmatter']['objectives']`,
`$lesson->title['de'] ?? $lessonContent['frontmatter']['title']`,
`$lesson->teaser['de'] ?? $lessonContent['frontmatter']['teaser']`.
Der 404-Guard prueft jetzt den DB-Wert zuerst. `ContentRepository`
bleibt als Fallback (eine Lektion, deren naechster `content:sync`-Lauf
noch aussteht, bleibt so funktionsfaehig) und als weiterhin einzige
Quelle fuer: Werkzeug-/Datensatz-Kataloge, Glossar-Begriffsaufloesung
(`MarkdownRenderer`) und das Quiz-Meta (`meta.yml`s `quiz:`-Block --
bleibt Teil von CMS-6, siehe QuizActivity/SandboxActivity-Kopplung,
ADR 0097).

**Nebenbefund:** `title`/`teaser` waren bereits seit ADR 0071
DB-Spalten, wurden aber nie tatsaechlich fuer die Lektionsanzeige
gelesen -- `LessonController::show()` bezog sie bis zu dieser ADR immer
frisch aus `$lessonContent['frontmatter']`, obwohl `content:sync` exakt
denselben Wert bereits in die DB geschrieben hatte. Diese ADR behebt das
nebenbei, ohne dass es eine eigene Migration braucht.

## Konsequenzen

- Nach dem naechsten `content:sync`-Lauf (z. B. bei jedem
  Docker-Image-Rebuild, siehe `entrypoint.sh`) lesen alle real
  existierenden Lektionen ihren Text aus der DB, nicht mehr live von der
  Platte -- der zentrale Audit-Befund ist fuer den **Lesepfad**
  geschlossen.
- **Bewusst nicht Teil dieser ADR** (CMS-5b, separat, deutlich groesser):
  `LessonEditorController`/`ContentVersioningService`/`ContentWriter`
  schreiben weiterhin nach `content/**` beim Freigeben -- der
  Autoren-Schreibpfad ist unveraendert. Ob `content/` dabei langfristig
  git-verfolgter Audit-Trail unter einer vollstaendig DB-schreibenden
  Autorenschicht bleibt, oder vollstaendig durch `content:export`
  ersetzt wird, ist weiterhin die in
  `docs/studio-architecture-plan.md` Abschnitt 3 offen gelassene Frage.
- Quiz-Meta bleibt datei-gefuehrt (Kopplung an die Lektion, siehe ADR
  0097) -- erst der Lesson Composer (CMS-6) macht Quiz zu einem
  eigenstaendigen, DB-gefuehrten Element.

## Verifikation

- Alle 470 Tests (4 neu: `ContentSyncTest` fuer body/objectives-Sync,
  zwei neue `LessonControllerTest`-Faelle fuer DB-Vorrang und
  Datei-Fallback), PHPStan Level 7 und `pint --test` sind gruen.
- `vue-tsc --noEmit`: keine neuen Fehler (keine Frontend-Aenderung in
  dieser Phase -- die Antwortform von `LessonController::show()` bleibt
  gleich, nur die Quelle der Werte aendert sich).
