# 0078 — W5: Voraussetzungen weich durchsetzen

## Status

Angenommen, 15.09.2026. Erster Teil von Arbeitsphase W5 aus
`dcm-lab-lms-agent-prompt.md` (Fragenbank folgt als eigener Schritt).

## Kontext

W5 verlangt: "`requires` wird durchgesetzt. Eine Aktivität mit offenen
Voraussetzungen wird angezeigt, aber gesperrt, mit Hinweis worauf sie
wartet. Keine harte Verbergung."

`docs/content-schema.md` Abschnitt 2 sagt zu `requires` bereits: "beschreibt
fachliche Abhängigkeit, nicht Reihenfolge im Menü. Eine Lektion ohne
`requires` darf quer eingestiegen werden." `requires` war also nie als
Zugriffsschutz gedacht, sondern als Empfehlung. "Durchsetzen" heißt hier
folglich: sichtbar machen, nicht verhindern.

## Entscheidung

**`App\Services\LessonPrerequisiteService`** berechnet je Nutzer und
Lektion, welche `requires`-Einträge noch nicht abgeschlossen sind
(`LessonProgress.status = 'completed'`). Sie verändert nirgends den
Zugriff: `LessonController::show()` rendert die Lektion unverändert
vollständig, auch wenn Voraussetzungen offen sind. Die einzige Änderung ist
eine zusätzliche, deutlich sichtbare Markierung in der Lernendenansicht:

- **Lektionsseite** (`LessonHero.vue`): ein Hinweis-Banner mit
  Schloss-Symbol ersetzt die bisherige neutrale "Vorher: ..."-Zeile, sobald
  mindestens eine Voraussetzung offen ist, mit explizitem Text "du kannst
  trotzdem hier weiterlesen". Sind alle Voraussetzungen erfüllt (oder gibt
  es keine), bleibt die bisherige, neutrale Zeile bestehen.
- **Trackübersicht** (`Tracks/Show.vue`): jede Lektionskarte mit offenen
  Voraussetzungen bekommt eine Zeile mit Schloss-Symbol und den Titeln der
  fehlenden Lektionen -- die Karte selbst bleibt klickbar.

Kein Redirect, kein 403, keine ausgegraute/deaktivierte Karte -- exakt das,
was "keine harte Verbergung" fordert.

## Konsequenzen

- `LessonController::toolbarData()`s `requires`-Einträge tragen jetzt ein
  `completed`-Feld; ein neues Top-Level-Feld `prerequisites_met` fasst das
  zusammen.
- `TrackController::show()`s Lektionsliste trägt `unmet_requires` je
  Lektion (leere Liste = nichts offen).
- Kein bestehender Zugriffspfad (Route, Middleware, Redirect) wurde
  verändert -- reine Anzeige-Erweiterung.

## Verifikation

- `LessonPrerequisiteServiceTest`: keine Voraussetzungen -> nichts offen;
  offene Voraussetzung wird mit Titel gemeldet; erfüllte Voraussetzung
  verschwindet aus der Liste; `unmetForMany()` liefert nur Lektionen mit
  mindestens einer offenen Voraussetzung.
- Alle 292 Tests (inklusive der bestehenden `LessonControllerTest`/
  `TrackControllerTest`, unverändert grün), `phpstan analyse` (Level 7),
  `pint --test`, `npm run check` und `npm run build` sind grün.
- Visuelle Kontrolle im Browser gegen die echte Postgres-Dev-Umgebung
  wurde für diese kleine, rein additive CSS-/Vue-Änderung nicht
  durchgeführt (siehe README-Warnung zur Dev-Datenbank) -- die neuen
  Klassen folgen bewusst denselben Design-Tokens und derselben Struktur
  wie die direkt benachbarte, bereits bestehende "Vorher"-Zeile.
