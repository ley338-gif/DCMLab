# 0076 — activity_progress als echtes Schreibziel (Vorstufe zu W4)

## Status

Angenommen, 15.09.2026. Vorstufe zu Arbeitsphase W4 aus
`dcm-lab-lms-agent-prompt.md`, im Anschluss an ADR 0073 (W0), die
ContentValidator-Extraktion (W1), ADR 0074 (W2) und ADR 0075 (W3).

## Kontext

W4 verlangt ein deklaratives Auslösekriterium fuer Achievements,
"ausgewertet gegen `activity_progress` aus W0". Beim Vorbereiten von W4 fiel
auf: `activity_progress` existiert seit W0 als Tabelle, aber **nichts
schreibt hinein**. `NodeController::submitFlag()` schreibt weiterhin nur
`node_attempts`, `LessonController::complete()`/`reopen()` nur
`lesson_progress`, `ExamAttemptService::complete()` nur `exam_attempts`. Ein
Achievement deklarativ gegen `activity_progress` auszuwerten, waere gegen
eine leere Tabelle bedeutungslos. Diese Vorstufe schliesst die Luecke,
bevor W4s eigentliches Ziel (die Achievement-Vereinheitlichung nach ADR
0070) angegangen wird.

## Entscheidung

**`ActivityProgressRecorder` schreibt additiv neben den bestehenden
Tabellen, ausgeloest von genau den drei Stellen, die heute schon einen
Abschluss feststellen:** `NodeController::submitFlag()` (nach korrektem
Flag), `LessonController::complete()`/`reopen()`, `ExamAttemptService::
complete()`. Der Recorder ruft dafuer nicht seine eigene Punkte-/
Skill-Logik auf, sondern `ActivityContract::result()` der jeweiligen
Aktivitaet -- dieselbe, bereits in W0 getestete Methode. Damit gibt es
weiterhin nur eine Stelle, die "was bedeutet Abschluss bei diesem Typ"
beantwortet.

Fehlt der `activities`-Verzeichniseintrag (z. B. weil `content:sync` in
einem Test nie lief), schreibt der Recorder stillschweigend nichts -- kein
Fehler, keine geaenderte Antwort an den Client. Das haelt alle bestehenden
Controller-Tests unveraendert gruen, ohne dass sie etwas ueber
`activity_progress` wissen muessten.

**Ein neuer Befehl `activity:backfill-progress`** liest die drei
Bestandstabellen einmalig aus und fuellt `activity_progress` fuer Nutzer,
die schon vor dieser Aenderung gespielt haben -- reine Additivmigration
(`updateOrCreate`), idempotent, ohne die Quelltabellen anzufassen. Ein
Operator fuehrt ihn nach dem Deploy dieser Aenderung einmal aus.

**Bewusst nicht Teil dieser Vorstufe:** die eigentliche
Achievement-Vereinheitlichung (drei Mechaniken zu einer, deklaratives
`unlock_when` gegen `activity_progress`) -- das ist der naechste Schritt und
braucht diese Grundlage als Voraussetzung, nicht als Teil desselben
Commits.

## Konsequenzen

- `activity_progress` fuellt sich ab sofort bei jedem neuen Node-Solve,
  Lektionsabschluss (inkl. Reopen) und Pruefungsabschluss.
- Fuer Lektionen wird bewusst nur bei `complete()`/`reopen()` geschrieben,
  nicht bei jedem Seitenaufruf (`show()`) -- ein reiner Lesebesuch ist kein
  Ergebnis im Sinn von ADR 0072.
- Kein bestehender Controller, Service oder dessen Rueckgabewert hat sich
  veraendert; alle Aenderungen sind zusaetzliche Methodenaufrufe.

## Verifikation

- `ActivityProgressDualWriteTest` beweist alle drei Schreibwege end-to-end
  (echter HTTP-Request bzw. Service-Aufruf, keine Mocks der Recorder-Logik).
- `ActivityProgressRecorderTest` beweist die beiden Nicht-Faelle (kein
  `activities`-Eintrag, kein Ergebnis) und Idempotenz.
- `ActivityBackfillProgressTest` beweist Uebertragung aus allen drei
  Bestandstabellen und Idempotenz.
- Alle 281 Tests, `phpstan analyse` (Level 7), `pint --test` und
  `npm run check` sind gruen; keine bestehende Controller-Antwort hat sich
  veraendert.
