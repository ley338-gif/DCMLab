# 0109 — Studio-Node-Editor (CMS-6d, Teil 3)

## Status

Angenommen, 16.09.2026.

## Kontext

ADR 0107/0108 haben die Grundlage gelegt: Node-Fliesstext ist DB-gefuehrt,
`NodeActivity::serialize($draft)`/`validate($draft)` werten einen Entwurf
tatsaechlich aus, `NodeContentPublisher` wendet ihn ohne `content/`-
Schreibpfad an. Was fehlte, war der Editor selbst -- `/studio/nodes`
existierte nicht, eine Node liess sich nur ueber `content:sync` aus einer
Datei anlegen.

Betreiberauftrag fuer diesen Teil: eine vollstaendige Resource-Uebersicht
mit Suche/Filter, ein Editor fuer alle autorenrelevanten Felder
(einschliesslich Hints als strukturierte Liste, nicht als JSON-Textfeld),
Anlegen mit atomarer Node+Activity-Erzeugung, Duplizieren ohne
Uebernahme von `node_attempts`/Fortschritt, Archivieren statt Loeschen,
eine Vorschau, die denselben Lerner-Renderer nutzt statt eines zweiten
Studio-Renderers, und explizit kein WYSIWYG (folgt separat in CMS-7).
Slug ist beim Anlegen fest, danach kein normales Formularfeld mehr.

## Entscheidung

**`StudioNodeController`** (`/studio/nodes`): `index()`/`store()`/
`duplicate()`/`archive()`/`restore()`/`updateThemenfeld()` sind
strukturelle Eingriffe auf der Node-Ressource selbst und ueber die neue
`NodePolicy::manage()` gegated (Reviewer/Administrator, wie
`TrackPolicy::manage()`) -- Node hat wie Track keinen eigenen
`activity_authors`-Bezug fuer diese Operationen. `edit()`/`update()`/
`validateDraft()` bearbeiten dagegen den eigentlichen Content-Entwurf und
pruefen ueber `Gate::authorize('update', $activity)` gegen die
`type=node`-Activity (`ActivityPolicy`) -- ein zugewiesener Autor darf den
Entwurf bearbeiten, auch wenn er die Node selbst nicht archivieren oder
duplizieren darf. Einreichen/Freigeben laufen ueber den bestehenden
generischen `author.quiz-versions`-Kreislauf (`ContentVersionController`)
weiter -- kein Node-eigener Freigabeweg.

**Themenfeld bleibt ausserhalb des Entwurfs.** ADR 0108 hat `themenfeld`
bewusst aus dem Draft-Schema ausgeschlossen (strukturelle Zuordnung, kein
Autoreninhalt). `updateThemenfeld()` ist deshalb ein eigener, sofort
wirksamer Endpunkt (`manage`-gated) statt eines Entwurfsfelds -- dieselbe
Rolle wie `track_id` bei Lesson.

**Anlegen** (`store()`): validiert Slug (`alpha_dash`, `unique:nodes`),
Titel, Themenfeld, Schwierigkeit, Kategorie, Interaktionstyp und erzeugt
`Node` + `Activity(type=node)` in einer DB-Transaktion mit denselben
Default-Werten wie ein frisch synchronisierter Datensatz (`status:draft`,
leere `body`/`hints`/`skills`/`related_lessons`). Redirect direkt in den
Editor.

**Duplizieren** (`duplicate()`): kopiert nur die aktuellen Feldwerte (Titel
+ " (Kopie)", Body, Hints, Skills, ...) in eine NEUE Node mit neuem Slug
(`<slug>-kopie`, bei Kollision `-2`/`-3`/...) und neuer Activity --
`node_attempts` haengen an der alten `node_id` und werden nie kopiert,
`content_versions` startet leer wie bei jeder neu angelegten Node.

**Hints als strukturierte Liste.** Der Editor zeigt `hints` (id/cost) als
sortierbare Liste (natives HTML5-Drag&Drop, dasselbe Muster wie der Lesson
Composer, ADR 0106) statt eines JSON-Textfelds -- der Hint-**Text** selbst
steht weiterhin im `body`-Feld (`### h<id>`-Abschnitt unter `## Hints`,
ADR 0107).

**Vorschau ist der echte Lerner-Renderer.** `preview_url` zeigt auf die
bestehende `nodes.show`-Route (`NodeController::show()`) -- kein zweiter,
Studio-spezifischer Renderer, der mit der Zeit vom echten abweichen
koennte. Damit eine frisch angelegte Node dort ueberhaupt sichtbar ist,
gilt (analog zu Track, ADR 0100) `archiviert` als endgueltig unsichtbar,
`draft` bleibt wie ein Track im Entwurf per Direktlink erreichbar:
`NodeController::index()`/`orderedNodes()` filtern jetzt `status !=
archived`, `show()` gibt fuer eine archivierte Node 404 zurueck.

**Runtime bleibt read-only.** `has_runtime_config` (im Editor angezeigt)
sagt nur, ob `content/nodes/<slug>/` ueberhaupt existiert -- eine rein in
Studio angelegte Node hat keine Sandbox-/Engine-Konfiguration und keinen
Flag-Validator; die Vorschau zeigt Titel/Text, aber keine spielbare
Aufgabe, bis ein Administrator die Runtime-Datei zusaetzlich anlegt
(ADR 0107). Das Editorformular selbst hat dafuer kein Feld.

**`serialize($draft)`/`validate($draft)` fuer eine Node ohne
Datei-Gegenstueck.** ADR 0108 hat diesen Pfad nur fuer bereits
dateibasierte Nodes gebaut (`serialize()` gab `[]` zurueck, sobald
`contentEntry()` `null` war -- fuer die 17 realen Nodes nie der Fall, fuer
eine neue Studio-Node aber immer). Fix: `serialize($draft)` startet bei
einer Node ohne Datei von einem leeren, aber validen Rumpf
(`node.yml` leer, `de.md` mit leerer Frontmatter `"---\n---\n"`) statt
sofort abzubrechen -- `validate($draft)` prueft den Entwurf einer neuen
Node damit genauso wie den einer bestehenden. Dabei aufgefallen und
mitkorrigiert: `NodeMetaGenerator::regenerateFrontMatter()` haengte ein
noch nicht existierendes Feld (`title`/`scenario_title`) bisher blind ans
Ende des gesamten Frontmatter-**Blocks** an -- hinter das schliessende
`---`, also ausserhalb der YAML-Frontmatter und beim naechsten Parsen
verloren. Betraf bisher keinen realen Node (alle 17 haben beide Felder
schon), waere aber fuer jede neue Studio-Node sofort aufgefallen. Jetzt
werden fehlende Felder innerhalb der Delimiter eingefuegt.

**Composer-Katalog sieht jetzt auch DB-only Nodes.**
`LessonEditorController::edit()`s `catalog.nodes` (Auswahl fuer
`lab.node`) las bisher nur `ContentRepository::nodes()` -- eine rein in
Studio angelegte Node (kein `content/nodes/**`) fehlte dort. Jetzt
`Node::pluck('slug')` zusaetzlich zum Datei-Bestand, dedupliziert und
sortiert.

**Review-Queue kennt jetzt auch Node.**
`ReviewQueueController::editUrl()`s `match` hatte keinen `'node'`-Zweig --
ein eingereichter Node-Entwurf waere in der Queue sichtbar, aber ohne
Klick-Link gewesen. Neuer Zweig verlinkt auf `/de/studio/nodes/{key}`.

## Konsequenzen

- Der komplette Zyklus aus dem Betreiberauftrag ist jetzt end-to-end
  moeglich: Studio → "+ Neue Node" → Metadaten/Body/Hint bearbeiten →
  Entwurf speichern → Einreichen → Freigeben → `content/nodes/**`
  unveraendert → Lerner sieht die Aenderung → Node im Lesson-Composer
  waehlbar.
- **Bewusst nicht Teil dieser ADR:** kein WYSIWYG/Rich-Text-Editor fuer
  `body` (folgt gemeinsam mit Lesson in CMS-7); keine Server-seitige
  Suche/Pagination in `index()` (bei der heutigen Node-Zahl reicht
  Client-seitiges Filtern); keine automatische Erzeugung einer
  Runtime-Konfigurationsdatei fuer eine neue Node -- das bleibt
  Administrator-Aufgabe.

## Verifikation

- Alle 518 Tests (19 neu: `StudioNodeControllerTest`, ein
  Review-Queue-Link-Test, ein Composer-Katalog-Test, ein
  `NodeActivityTest` fuer den datei-losen Serialisierungspfad), PHPStan
  Level 7 und `pint --test` sind gruen.
- `npm run build` und `vue-tsc --noEmit` (fuer die neuen Dateien) sind
  fehlerfrei.
