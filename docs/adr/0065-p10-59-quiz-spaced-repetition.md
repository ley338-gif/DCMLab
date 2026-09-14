# 0065 — P10.59: Quiz-Feature mit Spaced Repetition

Status: akzeptiert
Datum: 2026-09-14

## Kontext

`content/lessons/1.0`–`1.8/meta.yml` tragen seit P10.36 (ADR 0046) echte
`quiz:`-Daten (`id`/`type`/`answer`), mit einem passenden `## Quiz`-Abschnitt
in jeder der neun `de.md`-Dateien. Der Schema-Kommentar nennt sie
ausdrücklich „Wissenskarten für Spaced Repetition" — aber weder
`ContentSync`, `ContentValidate` noch `LessonController` lasen `quiz:`
bisher, und es gab keine UI dafür. Der Nutzer entschied sich nach
Abschluss von Track 5 explizit für den Ausbau der bestehenden Plattform
(statt einer neuen Themenfeld-Idee) und wählte für dieses Feature bewusst
den vollen Umfang (echter Wiederholungsalgorithmus plus eigene
Review-Seite) statt einer reinen In-Lektion-Quizkarte.

## Architektur-Leitplanke

Wie bei Node-Hints (`NodeSections`) gilt: **Content bleibt Datei-Wahrheit,
nichts wird in die DB synchronisiert.** Fragen-Text, Optionen und die
richtige Antwort werden bei jedem Request live aus `meta.yml`/`de.md`
gelesen — die DB (`quiz_reviews`) speichert ausschließlich den
Wiederholungs-Zustand pro Nutzer und Frage. `ContentSync.php` wurde
entsprechend nicht angefasst.

## Entscheidung

**Datenmodell** (`create_quiz_reviews_table.php`, `QuizReview.php`): Ein
vereinfachter, binärer SM-2 — das Original bewertet mit einer 0–5-Skala,
unsere Fragen sind aber binär richtig/falsch (single/multi/input-Grading
kennt kein „halb richtig"), daher eine bewusst dokumentierte
Vereinfachung, kein 1:1-SM-2 (`QuizSchedulerService`). Zeilen entstehen
lazy, erst bei der ersten Beantwortung. Ein realer Bug wurde beim Testen
gefunden und behoben: ohne Deckel wächst `interval_days` bei vielen
richtigen Antworten hintereinander exponentiell (`interval * ease_factor`)
und überschreitet irgendwann den Bereich, den Carbons Datumsarithmetik
noch parsen kann (`InvalidFormatException: Could not parse
'19481-09-19...'`, real durch einen Test mit 20 aufeinanderfolgenden
richtigen Antworten aufgedeckt) — behoben mit einem
`MAX_INTERVAL_DAYS`-Deckel (3650 Tage).

**Content-Parsing** (`QuizContent.php`): trennt den `## Quiz`-Abschnitt
vom übrigen Lektions-Body (Grenze: die erste `---`-Zeile nach der
Überschrift — real gegen alle neun bestehenden Lektionen verifiziert,
überall identisch strukturiert) und parst `**qN — Fragetext**`-Marker
plus nachfolgende nummerierte Listen in strukturierte Fragen. Die
richtige Antwort verlässt den Server nie ungeprüft — `answerFor()` wird
ausschließlich serverseitig aufgerufen.

**Validierung** (`ContentValidate::checkQuizStructure()`): 1:1 nach dem
Vorbild von `checkNodeStructure()`s Hint-Check — jede `quiz[].id` aus
`meta.yml` braucht eine passende Überschrift in `de.md`, `single`/`multi`-
Antwortindizes müssen innerhalb der geparsten Optionsanzahl liegen,
`input`-Antworten dürfen nicht leer sein. Real gegen den bestehenden
Content geprüft: 0 neue Verstöße bei allen 41 Lektionen.

**Backend**: `QuizController::answer()` (neue Route
`POST lessons/{lesson}/quiz/{questionId}/answer`) und
`ReviewController::index()` (neue Route `GET review`) — beide nach dem
Muster von `NodeController`/`postJson` (Bewertung server-seitig, kein
Punktesystem-Anschluss, da `ProfileService`s eigener Docblock festhält,
dass Lektionen aktuell 0 Punkte geben; Quiz bleibt konsistent dazu
vorerst punktefrei).

**Frontend**: `QuizSection.vue` (in `Lessons/Show.vue` anstelle des
bisher nur statisch mitgerenderten `## Quiz`-Abschnitts eingehängt —
der Lektions-Body wird jetzt in „vor dem Quiz" / „nach dem Quiz"
gesplittet, damit Navigation und Fußnote nach dem Quiz weiterhin an
ihrer ursprünglichen Stelle im Dokument erscheinen), `Review/Index.vue`
(eigene Seite für fällige Karten über alle Lektionen hinweg), neue
`components/ui/radio-group/`-Komponente (existierte im Projekt noch
nicht, im exakten Baustil von `Checkbox.vue` mit `reka-ui` ergänzt),
neue Karte „Fällige Wiederholungen" auf dem Dashboard.

## Manuell verifiziert (gegen den echten Stack)

- Isolierter Compose-Stack (`docker compose -p p10-59`): Migration real
  ausgeführt, `quiz_reviews`-Tabelle real bestätigt.
- `php artisan content:validate` gegen den echten `content/`-Bestand:
  weiterhin 0 Verstöße (41 Lektionen, 52 Glossarbegriffe) — die neue
  Prüfung schlägt bei keiner der neun realen Quiz-Lektionen an.
- **Vollständiger Browser-Durchlauf gegen den echten Stack:** echte
  Anmeldung, Lektion 1.0 aufgerufen, alle drei realen Fragetypen
  (single/multi/input) live beantwortet — jedes Mal reales, korrektes
  Server-Feedback („Richtig!"), reale `quiz_reviews`-Zeile mit
  korrektem SM-2-Zustand bestätigt (`repetitions: 1`, `interval_days:
  1`, `due_at` = real +1 Tag).
- Dashboard zeigte real keine „Fällige Wiederholungen"-Karte, solange
  nichts fällig ist (korrekt) — nach realem Vorstellen von `due_at`
  per `tinker` (Zeitraffer-Simulation statt tagelangem Warten) erschien
  die Karte real, verlinkte real zur `/de/review`-Seite.
- `/de/review` zeigte real alle drei fälligen Karten mit korrektem
  Lektionstitel (live aus Content geladen), Beantworten einer Karte
  entfernte sie real aus der Liste; zweite Beantwortung derselben Frage
  zeigte real `repetitions: 2`, `interval_days: 6` (echte
  SM-2-Progression über zwei echte Antworten hinweg).
- Wayfinder-generierte Routen-Helfer (`routes/quiz/index.ts`,
  `routes/review/index.ts`) real geprüft — Aufrufsignatur
  (`{lesson, questionId}`-Objekt) stimmte mit der im Frontend
  verwendeten Form exakt überein.
- `php artisan test --filter=Quiz`: 17/17 real bestanden (SM-2-Unit-
  Tests, Controller-Feature-Tests, ContentValidate-Erweiterung).
- `vendor/bin/phpstan analyse`: zunächst 5 reale Fehler gefunden (PHPStan
  konnte die verschachtelte `$raw[$id]['options'][]`-Struktur in
  `QuizContent::parseQuestions()` nicht sauber typisieren; `collect()`
  auf einer `mixed`-typisierten Variable in `QuizController` löste
  Template-Type-Fehler aus) — behoben durch getrennte flache Arrays
  statt verschachtelter dynamischer Schlüssel bzw. eine einfache
  `foreach`-Suche statt `collect()->firstWhere()`. Danach 0 Fehler.
- `vendor/bin/pint --test`: ein Style-Verstoß (voll qualifizierter
  Klassenname statt Import in einem Test) gefunden und behoben. Danach
  „PASS, 121 files".
- Kein Sandbox-Container für diese Slice nötig; `dcmlab/toolbox:latest`/
  `dcmlab/orthanc:latest` unberührt.

## Nicht Teil dieser Slice

- Keine Punkte-Vergabe für Quiz-Antworten (siehe oben).
- Kein Anschluss an Achievements/Skill-Radar.
- Kein `quiz:` für Tracks 2–5 — nur Track 1 hat aktuell reale
  Quiz-Daten; das Feature funktioniert für jede Lektion, die künftig
  welche bekommt, ohne weitere Code-Änderung.
- Keine E-Mail-/Push-Erinnerung für fällige Wiederholungen — nur die
  Dashboard-Karte und die Review-Seite selbst.
