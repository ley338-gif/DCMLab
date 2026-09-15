# 0105 — Lesson Elements: geordnete Sequenz + Learner-Renderer (CMS-6b)

## Status

Angenommen, 15.09.2026.

## Kontext

ADR 0094 legt Phase CMS-6 ("Lesson Composer") fest. Eine erste Planung
sah vor, `lesson_elements` als reine Datengrundlage einzufuehren, ohne
dass zunaechst irgendetwas sie liest -- der Betreiber hat das
zurueckgewiesen: eine Tabelle, die niemand konsumiert, ist tote
Infrastruktur. Ebenso zurueckgewiesen wurde ein Sprung zu Pages/
Navigation (CMS-9) -- das fuehrt vom Kernziel weg (Lessons aus frei
anordenbaren Content-/Activity-Modulen).

Stattdessen: CMS-6b als vollstaendiger vertikaler Slice --
Datenmodell, Migration bestehender Lektionen, UND ein Learner-Renderer,
der die Reihenfolge tatsaechlich verwendet. Noch ohne Rich-Text-Editor,
noch ohne Drag & Drop (beide folgen separat). Abnahmekriterium: die in
`lesson_elements` gespeicherte Reihenfolge aendert die tatsaechlich
gerenderte Reihenfolge auf der Lern-Seite.

## Entscheidung

**Domainmodell** (`lesson_elements`): bewusst kein vollkommen
beliebiges Polymorphie-Feld. Nur zwei `type`-Werte:

- `content` -- zeigt (vorerst) implizit auf `lessons.body`/`objectives`.
  `content_block_id` ist eine reservierte, aktuell ungenutzte Spalte
  (kein Fremdschluessel-Constraint, da keine `content_blocks`-Tabelle
  existiert) -- CMS-7 zerlegt ein Content-Element dann in einen
  strukturierten Dokumentbaum.
- `activity` -- `activity_id` verweist auf eine echte `activities`-Zeile
  (Sandbox/Quiz/Node/...). WELCHE Art Activity es ist, sagt die Activity
  selbst (`activities.type`) -- `lesson_elements` kennt keinen einzigen
  Modultyp namentlich. Passt zur bestehenden `ActivityRegistry`.

**Quiz bekommt eine echte Activity-Zeile** (`ActivityType::Quiz` jetzt
in `ActivityRegistry` registriert, `content:sync` legt sie an, analog
zu Sandbox seit ADR 0096) -- ohne das koennte `lesson_elements` nicht
einheitlich per `activity_id` auf sie verweisen. Die Lab-Referenz einer
Lektion nutzt die bereits bestehende `type=node`-Activity des
verlinkten Node -- kein neuer Activity-Typ dafuer.

**Migration bestehender Lektionen**: `ContentSync::syncLessonElements()`
laeuft nach `syncNodes()` (eine Lab-Referenz braucht die `type=node`-
Activity des Node, die erst dort entsteht) und legt nur **fehlende**
kanonische Slots an -- Content, Sandbox, Lab, Quiz, in dieser
Reihenfolge, nur wenn das jeweilige Element tatsaechlich existiert.
Bereits vorhandene Zeilen werden **nie** angeruehrt: eine spaeter in
Studio (CMS-6c) per Drag & Drop geaenderte Reihenfolge bleibt ueber
jeden weiteren Sync-Lauf hinweg erhalten (idempotent, keine
Rueckwaerts-Ueberschreibung).

**Learner-Renderer** (`LessonController::show()`,
`Lessons/Show.vue`): "Content" ist jetzt ein einzelnes,
zusammenhaengendes Element -- Prosa vor UND nach einem eingebetteten
`## Quiz`-Abschnitt wird zu EINEM HTML-Block zusammengefuehrt (Quiz ist
kein Einschub mehr, sondern ein eigenstaendiges Element). Die Vue-Seite
iteriert `elements` und rendert je Eintrag die **bestehende** Komponente
wieder (`PracticeTask` fuer Sandbox/Lab, `QuizSection` fuer Quiz, ein
`v-html`-Block fuer Content) -- kein Redesign, dieselbe Komponente wird
fuer Sandbox und Lab einfach mit unterschiedlichen Props zweimal
verwendet. `toolbar` verliert `needs_sandbox`/`dataset`/`lab_node` (jetzt
Teil der jeweiligen Elemente); `tools`/`requires`/`prerequisites_met`/
`lab_optional` bleiben unveraendert dort.

Eine Lektion ohne jede `lesson_elements`-Zeile (naechster Sync-Lauf
steht noch aus) faellt auf denselben kanonischen Ablauf zurueck, den der
Backfill erzeugen wuerde -- kein Bruch fuer noch nicht synchronisierten
Bestand.

**Studio-Sichtbarkeit** (`StudioLessonController`, `/studio/lessons/{lesson}`):
zeigt die aktuelle Reihenfolge nummeriert an -- bewusst nur lesend, kein
Drag & Drop (das ist CMS-6c). Verlinkt vom Lektions-Editor aus.

## Konsequenzen

- Neue Tabelle `lesson_elements`, neues Model, neue Factory.
- `ActivityRegistry::registeredTypes()` liefert jetzt zusaetzlich
  `quiz`.
- **Bewusst nicht Teil dieser ADR**: Drag & Drop (CMS-6c), Content als
  strukturierter Dokumentbaum (CMS-7), ein eigener Node-Editor (CMS-6d).

## Verifikation

- Alle 484 Tests, PHPStan Level 7 und `pint --test` sind gruen.
- **Abnahmekriterium direkt getestet**
  (`LessonControllerTest::test_reordering_lesson_elements_in_the_db_changes_the_rendered_order`):
  vier `lesson_elements` in der Reihenfolge Quiz/Sandbox/Lab/Content
  angelegt, Antwort zeigt genau diese Reihenfolge; danach in der DB auf
  Content/Sandbox/Lab/Quiz umgestellt, Antwort zeigt die neue
  Reihenfolge, ohne Codeaenderung.
- `vue-tsc --noEmit`, `npm run check`, `npm run build` erfolgreich.
