# 0110 — Node-Sichtbarkeit auf "published" gehaertet (CMS-6d Haertung)

## Status

Angenommen, 16.09.2026.

## Kontext

ADR 0109 (CMS-6d Teil 3) hat den Studio-Node-Editor gebaut und dabei
`NodeController::index()`/`orderedNodes()` bewusst nur `archiviert`
ausblenden lassen -- Draft blieb wie bei Track (ADR 0100) per Direktlink
erreichbar, damit "Vorschau" denselben Lerner-Renderer nutzen kann, ohne
einen zweiten Renderer zu bauen.

Betreiberfund direkt nach dem Merge: anders als bei Track ist eine Node
sofort spielbar (eigener Engine-Session, echte Flag-Pruefung, echte
Achievement-/Punktevergabe) -- ein per Studio angelegter Entwurf war damit
fuer *jeden angemeldeten Lernenden* ab dem Anlegen erreichbar, nicht nur
fuer den Autor selbst. `NodeController::index()` listete ihn zudem im
oeffentlichen Katalog. Der Lesson-Composer-Katalog
(`LessonEditorController`) bot eine solche Node ebenfalls ungefiltert als
Lab-Auswahl an.

## Entscheidung

**Nur `status: published` ist fuer Lernende sichtbar.**
`NodeController::orderedNodes()` (Basis fuer `index()` und die Prev/Next-
Navigation in `show()`) filtert jetzt `where('status', 'published')`
statt `!= 'archived'`. `show()` selbst prueft zusaetzlich explizit: eine
nicht veroeffentlichte Node (draft/review) ist 404, **ausser** der
angemeldete Nutzer darf die zugehoerige `type=node`-Activity bearbeiten
(`Gate::allows('update', $activity)`, `ActivityPolicy` -- zugewiesener
Autor oder Reviewer/Administrator). Das ist "Vorschau" (ADR 0109) --
weiterhin dieselbe Route, kein zweiter Renderer, nur zusaetzlich
autorisiert statt bedingungslos offen.

**Lesson-Composer-Katalog nur `published`.**
`LessonEditorController::edit()`s `catalog.nodes` liest jetzt
ausschliesslich `Node::where('status', 'published')` statt jede DB-Node --
ein Lab-Verweis auf einen noch nicht freigegebenen Entwurf waere fuer
Lernende ein gesperrter Link gewesen.

**`NodeFactory`-Default auf `published` gedreht.** Fast jeder bestehende
Node-bezogene Test erzeugt seine Fixture ueber `Node::factory()->create()`
und verlaesst sich implizit auf "diese Node ist normal spielbar" -- der
bisherige Default `draft` haette mit der neuen Haertung fast den gesamten
Bestand betroffen. `published` spiegelt zudem den echten Bestand (alle 17
realen Nodes stehen auf `published`); ein Test, der gezielt eine noch
nicht freigegebene Node braucht, ueberschreibt das explizit.

## Konsequenzen

- Eine frisch in Studio angelegte Node (ADR 0109) ist ab jetzt bis zu
  ihrer ersten echten Freigabe (`NodeContentPublisher::publish()`, das seit
  demselben Merge auch `Node.status` auf `published` setzt) fuer normale
  Lernende unsichtbar und nicht aufrufbar -- genau der Zustand, den CMS-6d
  von Anfang an wollte, aber ADR 0109 noch nicht vollstaendig umgesetzt
  hatte.
- `docs/offene-fragen.md` unveraendert -- keine neue offene Frage, die
  Haertung schliesst eine Luecke, die erst durch ADR 0109 selbst entstand.

## Verifikation

- Alle 525 Tests (6 neu: gesperrte/erlaubte Vorschau in
  `NodeControllerTest`, Katalog-Filter in `NodeIndexControllerTest`/
  `LessonEditorControllerTest`), PHPStan Level 7 und `pint --test` sind
  gruen.
