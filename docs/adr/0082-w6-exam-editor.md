# 0082 — W6.3: Prüfungs-Editor (Einstellungen)

## Status

Angenommen, 15.09.2026. Dritter Editor aus Arbeitsphase W6
(`dcm-lab-lms-agent-prompt.md` Abschnitt 5), im Anschluss an ADR 0081.

## Kontext

Der Agent-Prompt beschreibt den Prüfungs-Editor so: "Pool aus der
Fragenbank, Typmischung und Abdeckung als Fortschrittsanzeige statt als
Fehlermeldung hinterher, `review`-Anker als Dropdown aus den Überschriften
der Ziellektion." Der Fragenpool einer Prüfung ist aber keine Liste
unabhängiger Einträge, sondern eine Menge mit *globalen* statistischen
Anforderungen (`ContentValidator::checkExamStructure()`, ADR 0079):
mindestens 4 Poolfragen je Lektion, mindestens 4 `cross`-Fragen, ein
Typ-Anteil von 35–40 % `single` / 20–25 % `multi` / 20–25 % `truefalse` /
10–15 % `input`, mindestens 25 % `difficulty: 3`. Eine einzelne Frage
hinzuzufügen oder zu löschen verschiebt fast immer mindestens eine dieser
Quoten für den *gesamten* Pool -- das ist ein Bulk-Kurationsproblem, kein
Einzelformular wie bei Quiz- oder Lektionsfragen.

## Entscheidung

**W6.3 liefert zwei getrennte, unabhängig nützliche Teile, nicht den
vollen Pool-Editor auf einmal:**

1. **Einstellungen editierbar** (Titel, Einleitung, Bestehensgrenze,
   Fragenzahl je Versuch, Dauer, Mischen, `min_per_lesson`) über
   `/de/author/exams/{track}/edit`, mit demselben Kreislauf wie Quiz-
   und Lektions-Editor (ADR 0080/0081). **`ExamMetaGenerator`** wendet
   dasselbe Zeilenersatz-Muster wie `LessonMetaGenerator` auf `exam.yml`
   und die Frontmatter von `de.md` an -- der `questions:`-Block und alle
   `### fNN — ...`-Abschnitte bleiben dabei Byte für Byte unangetastet.
   `ExamActivity::serialize($draft)`/`validate($draft)` folgen demselben
   Muster wie `LessonActivity` (synthetischer `exams`-Eintrag für
   `ContentValidator`).
2. **Eine live berechnete Pool-Übersicht** (`ExamEditorController::
   coverage()`) statt der vollen Fragenverwaltung: Abdeckung je Lektion
   gegen `min_per_lesson`, `cross`-Anzahl gegen die feste Mindestzahl 4,
   Typmischung gegen dieselben Bandbreiten wie der Validator, und der
   `difficulty: 3`-Anteil gegen 25 % -- als Fortschrittsanzeige direkt im
   Editor, nicht erst als Fehlermeldung nach dem Speichern. Diese Übersicht
   liest den *bestehenden* Pool, nicht den Entwurf, weil der Entwurf ihn
   in v1 nicht verändert.

**Bewusst nicht Teil von v1: Fragen einzeln hinzufügen, bearbeiten oder
entfernen** (inklusive `ref`-Erstellung und einem Anker-Dropdown aus den
Überschriften der Ziellektion). Das ist der eigentliche "Größter Aufwand"
-Teil des Editors und verdient eine eigene, sorgfältig getestete
Erweiterung mit einem UI, das die Pool-Statistik *waehrend* der
Bearbeitung nachführt (dieselbe `coverage()`-Berechnung, aber gegen den
Entwurf statt gegen den Ist-Zustand) -- siehe `docs/offene-fragen.md`. Bis
dahin bleibt das Kuratieren des Pools selbst Kommandozeilen-Sache, mit
klar sichtbarem Live-Feedback, wo der Pool heute steht.

## Konsequenzen

- `resources/js/pages/Author/ExamEditor.vue`: Einstellungsformular plus
  Pool-Übersicht (Lektionsabdeckung, Cross-Anzahl, Typmischung,
  Schwierigkeitsanteil), mit demselben Prüfen/Entwurf/Einreichen/
  Freigeben-Aktionsblock.
- Route-Gruppe `de/author/exams/{track}/edit` (auth+verified). Submit/
  Publish laufen über die bestehende, generische `quiz-versions`-Gruppe
  (`ContentVersionController`, ADR 0081).
- **DoD-Einschränkung:** Der Prüfungstyp ist mit diesem Editor noch nicht
  *vollständig* ohne Kommandozeile anlegbar (Abnahmekriterium aus
  Abschnitt 5) -- nur seine Einstellungen. Ein neuer Prüfungspool von
  Grund auf bleibt Kommandozeilen-Sache, bis die Pool-Verwaltung folgt.
  Diese Lücke ist bewusst und in `docs/offene-fragen.md` festgehalten,
  keine übersehene Anforderung.

## Verifikation

- `ExamMetaGeneratorTest`: einzelne Einstellungsfelder werden ersetzt,
  Kommentare und der `questions:`-Block bleiben erhalten, Rundtrip gegen
  echten Bestand (`content/exams/fundamente/exam.yml`) bleibt gültig und
  geparst identisch.
- `ExamActivityTest`: `serialize($draft)` regeneriert nur die
  Einstellungsfelder und lässt den Fragenpool unangetastet;
  `validate($draft)` meldet einen ungültigen Entwurf (z. B.
  `pass_percent` außerhalb 50–100) über denselben `ContentValidator`-Weg
  wie der Ist-Zustand.
- `ExamEditorControllerTest`: eine Lernperson darf den Editor nicht öffnen
  (403); eine zugewiesene Autorenperson sieht Ist-Zustand und
  Pool-Übersicht aus `content/`; der volle Kreislauf (prüfen → Entwurf →
  einreichen → freigeben) schreibt tatsächlich nach `content/`, und der
  Fragenpool bleibt danach byte-für-byte wie zuvor (Fixture: ein
  statistisch gültiger 8-Fragen-Pool, damit die Freigabe nicht an
  `checkExamStructure()`-Quoten scheitert, die dieser Editor gar nicht
  verändert).
- Alle 340 Tests, `phpstan analyse` (Level 7), `pint --test`,
  `npm run check` und `npm run build` sind grün.
