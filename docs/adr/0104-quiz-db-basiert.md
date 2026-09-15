# 0104 — Quiz wird DB-basiert (CMS-6a)

## Status

Angenommen, 15.09.2026.

## Kontext

ADR 0094 legt Phase CMS-6 ("Lesson Composer") fest: "LessonElement /
Activity Placement implementieren... Quiz als eigenstaendiges Element."
ADR 0097 hatte bereits festgehalten: Quiz bleibt bis CMS-6 datei-
gefuehrt, weil Fragen-Metadaten keine eigene DB-Spalte haben.

Ein konkreter, durch ADR 0102 (CMS-5b) neu entstandener Risiko-Fall
motiviert, das jetzt zu schliessen: seit ADR 0102 schreibt eine
Lektionsfeld-Freigabe nur noch die DB, nie mehr `content/`. Eine
QUIZ-Freigabe schrieb bis zu dieser ADR aber weiterhin `content/`
(ueber `ContentWriter`/`LessonActivity::serialize()`), basierend auf dem
-- nach einer reinen Lektionsfeld-Aenderung nun potenziell veralteten --
Dateiinhalt. Ein anschliessender `content:sync`-Lauf haette die frisch
in der DB gespeicherte Lektionsprosa mit dem alten Dateistand wieder
ueberschrieben. CMS-6a schliesst dieses Fenster vollstaendig, indem
Quiz denselben DB-direkten Weg wie die Lektion selbst bekommt.

## Entscheidung

**Neue Spalte `lessons.quiz`** (json, nullable): trägt dieselben
Metadaten wie bisher `meta.yml`s `quiz:`-Block (`id`, `type`, `answer`).
Der eigentliche Fragetext/die Optionen brauchen **keinen** zweiten
Speicherort -- sie stecken bereits im `body`-Feld (seit ADR 0101, als
Teil des vollstaendigen Markdowns inklusive `## Quiz`-Abschnitt).
`content:sync` befuellt `quiz` aus derselben Datei, die ohnehin schon
fuer `body`/`objectives` gelesen wird.

**`QuizContentPublisher`** (Gegenstueck zu `LessonContentPublisher`):
regeneriert den `## Quiz`-Abschnitt in `lessons.body` mit
`LessonQuizGenerator::regenerateBody()` -- derselben, bereits
bestehenden, reinen Text-in-Text-out-Funktion, jetzt nur gegen den
DB-Spaltenwert statt gegen Dateitext angewendet -- und schreibt die
Metadaten-Tripel nach `lessons.quiz`. `ActivityContentApplier`
unterscheidet jetzt drei Faelle statt zwei: Lektionsfeld-Entwurf (kein
`quiz`-Schluessel) -> `LessonContentPublisher`; Quiz-Entwurf (`quiz`-
Schluessel) -> `QuizContentPublisher`; jeder andere Aktivitaetstyp ->
weiterhin `ContentWriter`.

`QuizActivity::quizMeta()`/`splitBody()` und
`QuizEditorController::currentQuestions()` (der Ist-Zustand, den das
Formular ohne ausstehenden Entwurf zeigt) bevorzugen jetzt die DB --
`content/` bleibt Fallback fuer eine noch nicht synchronisierte
Lektion. Anders als beim Lektions-Editor (ADR 0103, dort nachtraeglich
korrigiert) wurde dieser Fallback hier von Anfang an mitgezogen.

## Konsequenzen

- Quiz-Freigaben sind jetzt vom `:ro`-Docker-Mount-Problem nicht mehr
  betroffen (`docs/offene-fragen.md` entsprechend aktualisiert) --
  Pruefung/Achievement/Node bleiben betroffen, bis CMS-8.
- `LessonActivity::serialize()`s Quiz-Zweig (Dateitext-Regenerierung)
  bleibt unveraendert bestehen -- nicht mehr fuer echte Freigaben
  aufgerufen, aber weiterhin fuer `validate($draft)`s synthetische
  Ist-Zustands-Pruefung load-bearing (unveraendertes, akzeptiertes
  Verhalten, kein Datenverlustrisiko, da nichts mehr geschrieben wird).
- Kein Datenverlust: `LessonQuizGenerator::regenerateBody()` ist
  bereits vor dieser ADR reine Funktion ohne Datei-I/O -- ihre
  Wiederverwendung gegen DB-Text aendert an ihrem Verhalten nichts.

## Verifikation

- Alle 479 Tests (7 neu: `QuizContentPublisherTest`, ein neuer
  `ContentSyncTest`-Fall gegen echten Bestand, `QuizEditorControllerTest`s
  Freigabe-Test vollstaendig neu gefasst inkl. "erneutes Oeffnen zeigt
  frischen Stand"), PHPStan Level 7 und `pint --test` sind gruen.
