# 0119 — Lesson-Sichtbarkeit auf "published" gehaertet

## Status

Angenommen, 2026-09-19.

## Kontext

ADR 0110 (CMS-6d Haertung) hat `NodeController::show()` gehaertet: eine
nicht veroeffentlichte Node (draft/review) ist seither fuer normale
Lernende 404, ausser der angemeldete Nutzer darf die zugehoerige
`type=node`-Activity bearbeiten (`Gate::allows('update', $activity)`,
`ActivityPolicy`). Der Grund war, dass eine Node sofort spielbar ist
(eigener Engine-Session, echte Flag-/Punktevergabe) und ein per Studio
angelegter Entwurf sonst fuer *jeden* angemeldeten Lernenden ab dem
Anlegen erreichbar war.

Beim redaktionellen Audit einer Content-PR (Interop-Lernpfad 7.1-7.6,
#154 — sechs Lektionen bewusst auf `status: draft`) fiel auf, dass
`LessonController::show()` diese Haertung nie bekommen hat: Es ruft
`LearnerViewBuilder::lessonProps()` bedingungslos auf, unabhaengig vom
`Lesson.status`. Eine Draft-Lektion ist zwar aus Track-/Katalog-Listings
ausgeblendet (`TrackController` filtert `Track.status === 'published'`,
und eine Draft-Lektion gehoert ohnehin selten einer veroeffentlichten
Track an), aber ein Lernender mit dem direkten `/lessons/{lesson_id}`-Link
sieht den vollen Lektionsinhalt trotzdem — dieselbe Luecke, die ADR 0110
fuer Node bereits geschlossen hat, nur fuer Lesson nie nachgezogen.

Der Lesson-Editor selbst (`LessonEditorController::edit()`/
`validateDraft()`/`storeDraft()`/`preview()`) ruft schon immer explizit
`Gate::authorize('update', $activity)` auf — nur die eigentliche
Lernenden-Route (`LessonController::show()`) hatte keinen aequivalenten
Schutz.

## Entscheidung

**Nur `status: published` ist fuer Lernende sichtbar**, exakt wie bei
Node. `LessonController::show()` prueft jetzt vorab: eine nicht
veroeffentlichte Lektion (draft/review, sowie archiviert) ist 404,
**ausser** der angemeldete Nutzer darf die zugehoerige `type=lesson`-
Activity bearbeiten (`Gate::allows('update', $activity)`,
`ActivityPolicy` — zugewiesener Autor oder Reviewer/Administrator). Das
ist "Vorschau" — dieselbe Route, kein zweiter Renderer, nur zusaetzlich
autorisiert statt bedingungslos offen. Die uebrigen `LessonController`-
Methoden (`complete()`/`reopen()`) bleiben ungeaendert, analog dazu, dass
Node's Zustandsendpunkte (`state()`/`exec()`/`submitFlag()`/...) ebenfalls
keinen eigenen Status-Check tragen — der Schutz sitzt an der einen Stelle,
an der Inhalt tatsaechlich gerendert wird.

**Keine neue `LessonPolicy`.** Wie bei Node reicht die Wiederverwendung
von `ActivityPolicy` gegen die generische `Activity`-Zeile (`type=lesson,
key=<lesson_id>`) — die Berechtigungslogik (Reviewer/Administrator immer,
Autor nur bei Zuweisung) ist typunabhaengig und lebt bereits dort.

**`LessonFactory`-Default auf `published` gedreht**, exakt aus demselben
Grund wie bei `NodeFactory` (ADR 0110): Fast jeder bestehende Lesson-
bezogene Test erzeugt seine Fixture ueber `Lesson::factory()->create()`
und verlaesst sich implizit auf "diese Lektion ist normal sichtbar" — der
bisherige Default `draft` haette mit der neuen Haertung fast den gesamten
Bestand betroffen. `published` spiegelt zudem den echten Bestand (jede
Lektion ausserhalb der bewusst als Entwurf gefuehrten Interop-/FHIR-Pfade
steht auf `published`); ein Test, der gezielt eine noch nicht
freigegebene Lektion braucht, ueberschreibt das explizit (siehe
`LessonControllerTest`, vier neue Tests analog zu `NodeControllerTest`).

## Konsequenzen

- Die sechs Interop-Lektionen (7.1-7.6, PR #154) und die drei FHIR-
  Lektionen (8.1-8.3) sind ab sofort tatsaechlich unsichtbar fuer normale
  Lernende, nicht nur aus dem Katalog ausgeblendet — der Zustand, den PR
  #154 bereits stillschweigend vorausgesetzt hatte.
- Kein Effekt auf `content:sync`, `content:validate` oder die Studio-
  Editor-Routen — die waren bereits ueber `Gate::authorize()` geschuetzt.
- `docs/offene-fragen.md` unveraendert — die Haertung schliesst eine
  Luecke, die es seit Einfuehrung des Lesson-`status`-Felds gab, keine neue
  offene Frage.

## Verifikation

- Vier neue Tests in `LessonControllerTest` (gesperrte/erlaubte Vorschau,
  analog zu den vier ADR-0110-Tests in `NodeControllerTest`).
- Voller Backend-Test-Suite (`pest`), `pint --test`, `phpstan analyse`
  gruen.
