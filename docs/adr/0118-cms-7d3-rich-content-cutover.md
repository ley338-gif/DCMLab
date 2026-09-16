# 0118 — Rich Content als kanonisches Autorenformat: Publish/Restore/Editor/Read-Cutover (CMS-7d.3)

## Status

Angenommen, 02.10.2026.

## Kontext

CMS-7d.1/7d.2 (ADR 0115-0117) haben `lessons.rich_content`/
`nodes.rich_content` befuellt, aber nichts liest oder schreibt sie im
Normalbetrieb -- der Editor schreibt weiterhin `body`, der Lernpfad
rendert weiterhin `body`. CMS-7d.3 ist der eigentliche, produktive
Cutover: Editor, Publish, Restore und der Learner-Lesepfad wechseln
gemeinsam, in einem einzigen PR (Betreiber-Vorgabe: "Publisher und
Read-Pfad muessen zusammen live gehen").

Beim Mapping wurden vor Beginn der Implementierung zwei Dinge bewusst
entschieden:

1. **`ActivityContentApplier::apply()` und `ContentVersioningService::
   publish()` liefen bisher NICHT atomar** (`ContentVersionController::
   publish()`): Schritt 1 schrieb live in `Lesson`/`Node`, Schritt 2
   markierte die Version erst danach als veroeffentlicht -- schlug
   Schritt 2 fehl, waren Live-Daten bereits geaendert, ohne dass die
   Versionshistorie das widerspiegelte. Wird beim Rich-Content-Cutover
   nicht mitgenommen.
2. **Drei `ContentValidator`-Regeln scannten rohen Markdown-Text**
   (`checkExampleRule`, `checkTerms`, `checkToolInverse`). Sobald ein
   Lesson-/Node-Entwurf `rich_content` statt `body` traegt, gibt es
   diesen Text nicht mehr. Entschieden: auf RichContentDocument-Struktur
   umstellen (Baum-Traversal statt Regex), nicht auf `content/`
   zurueckbauen und nicht ueberspringen.

Zusaetzliche, beim Mapping bestaetigte Feinheit: `LessonEditorController::
currentFields()` blendete den Post-Quiz-Fusstext ("Als Naechstes: ...")
bisher komplett aus dem Editor aus (nur `QuizContent::
splitBody()['before']` wurde gezeigt) -- er war ueber KEINE UI editierbar.
Da `rich_content` seit der 7d.2-Korrektur `before`+`after` als EIN
Dokument speichert (identisch zu dem, was `LessonController::show()`
rendert), wird dieser Fusstext mit dem neuen `RichContentEditor` erstmals
sichtbar und editierbar -- eine bewusste, positive Vereinfachung (ein
Dokument, keine unsichtbare Sonderzone mehr), keine Funktionseinschraenkung.

## Entscheidung

Sieben Phasen, ein gemeinsamer Cutover-PR.

### Phase 1 -- Payload-Normalizer + RichContentDocument-Validierung

`App\Content\RichContent\LessonPayloadNormalizer`/`NodePayloadNormalizer`:
eine Methode, `normalize(array $payload): array` -- no-op, wenn
`payload['rich_content']` schon gesetzt ist; sonst `body` (Legacy-
Payload) per `MarkdownToRichContentConverter`/`NodeSections::parse()`
umwandeln. Der EINE Ort, an dem `body -> rich_content` passiert;
Draft-Bearbeitung, Publish, Restore und Preview rufen alle denselben
Normalizer, nicht vier eigene Implementierungen.

`ContentValidator`-Umbau: `checkRichContentExampleRule()`/
`checkRichContentTerms()`/`checkRichContentToolInverse()` laufen als
Baum-Traversal (`walkRichContentBlocks()`/`walkRichContentListItems()`/
`walkRichContentTableRows()`) gegen `RichContentDocument['content']`
statt gegen Markdown-Regex. Dispatch ueber `isset($lesson['rich_content'])`/
`isset($node['rich_content'])` -- ein datei-basierter `ContentRepository`-
Eintrag traegt diesen Schluessel nie, `content:validate` gegen
`content/**` bleibt deshalb unveraendert. `mergedNodeContent()` fasst
Briefing+Hints+Write-up einer Node zu einem Pseudo-Dokument zusammen,
damit "mindestens ein Codeblock" fuer die GANZE Node gilt, nicht pro
Abschnitt.

`NodeActivity::checkHintIdConsistency()`: `set(hints[*].id)` muss exakt
`keys(rich_content.hints)` entsprechen -- kein Hint ohne Text, kein
Rich-Content-Hint ohne Kosten-/ID-Metadaten.

### Phase 2 -- Atomarer `ContentPublishingService` + `restoreVersion()`

`App\Content\ContentPublishingService` ersetzt den bisherigen
Zwei-Schritt-Aufruf:

```
publish(version, reviewer): list<ContentIssue>
  normalize(payload)
  validate(normalized)          -- ausserhalb der Transaktion
  DB::transaction:
    ActivityContentApplier::apply()
    ContentVersioningService::publish()
```

`ActivityContentApplier::apply()` bleibt unveraendert (entscheidet
weiterhin Lesson/Quiz/Node/ContentWriter) -- nur der Aufrufzeitpunkt
wandert in die Transaktion, sodass ein Fehlschlag in Schritt 2 auch
Schritt 1 zurueckrollt.

`restoreVersion(source, performedBy): ContentVersion` ersetzt die Rolle,
die `ContentVersioningService::rollback()` fuer Lesson/Node nie ausgefuellt
hat (reine Buchfuehrung, wendet laut eigenem Klassendoc nichts an):
normalisiert die historische Fassung, validiert sie gegen die HEUTIGEN
Regeln, wendet sie an UND schreibt eine neue Version -- alles in einer
Transaktion. `created_by`/`reviewed_by` sind bewusst `performedBy`, NICHT
der historische Autor der wiederhergestellten Fassung -- wer eine
Wiederherstellung ausloest, zeichnet dafuer verantwortlich. Neue Spalte
`restored_from_version_id` (nullable FK auf `content_versions.id`) haelt
die Herkunft nach. Eine ungueltige historische Fassung wirft
`RuntimeException` statt still zu ueberspringen -- eine Wiederherstellung,
die Live-Daten in einen ungueltigen Zustand versetzen wuerde, ist kein
normaler Ausgang. Alte, unveraendert gebliebene Revisionen (auch mit
`payload.body`) werden dabei nie rueckwirkend umgeschrieben, nur die neu
entstehende Kopie wird normalisiert.

### Phase 3 -- Publisher auf `rich_content` umstellen

`LessonContentPublisher`/`NodeContentPublisher` schreiben `rich_content`
direkt aus dem (normalisierten) Payload -- `body` wird NICHT mehr aus dem
Editor regeneriert (Betreiber-Vorgabe: "keine zwei schreibenden Sources
of Truth"). Die Spalte bleibt unangetastet stehen (Legacy-Fallback fuer
eine noch nicht migrierte Lektion/Node).

### Phase 4 -- Learner-Read-Cutover

`LessonController::show()`/`NodeController::show()`+`useHint()`+
`viewWriteUp()`: `if ($model->rich_content !== null) { RichContentRenderer } else { legacy MarkdownRenderer-Pfad }`.
`body`/`QuizContent::splitBody()` bleiben fuer `quiz_raw`/Quiz-Fragen
bestehen (Quiz braucht weiterhin Markdown-gefuehrten Text, unveraendert).
`environment`/Flag-Konfiguration bleibt datei-gefuehrt, unangetastet.

### Phase 5 -- Editor-Anschluss

`LessonEditor.vue`/`Studio/Nodes/Edit.vue`: `<textarea v-model="fields.body">`
weicht `<RichContentEditor v-model="fields.rich_content" />`. Fuer Node
DREI Editoren (Briefing, je Hint, Write-up) statt einer Textarea --
`addHint()`/`removeHint()`/eine neue `renameHintId()` halten
`fields.rich_content.hints` synchron zu `fields.hints[].id`. Laravel-
Validierungsfalle dabei gefunden: eine `rich_content.*`-Unterregel neben
`rich_content => required|array` liess `$request->validate()` jeden
NICHT explizit genannten Unterschluessel (z. B. `content`) still aus dem
validierten Ergebnis herausfiltern -- gefixt durch ausschliesslich die
grobe Form auf Formularebene zu pruefen, die eigentliche Struktur- und
Regelpruefung laeuft ueber `activity->validate($draft)`.

**Nachtrag (Tabellenunterstuetzung).** Der erste Editor-Test gegen echten
Bestand zeigte, dass 37 von 42 Lektionen und 16 von 17 Nodes mindestens
eine Tabelle enthalten -- der Editor war fuer den Grossteil des echten
Bestands faktisch nicht nutzbar. ADR 0114 hatte `table` noch bewusst aus
dem Editor-Scope ausgeschlossen ("selten"), diese Annahme traf nicht zu.
Ergaenzt: `@tiptap/extension-table(-row/-header/-cell)`. DCMLabs EIN
`table_cell{attrs:{header:boolean}}`-Knotentyp wird auf TipTaps ZWEI
getrennte Knotentypen (`tableHeader`/`tableCell`) uebersetzt, dazu ein
`/table`-Slash-Command.

### Phase 6 -- Echte Draft-Preview

`App\Content\LearnerViewBuilder`: `lessonProps(Lesson $lesson, User $user,
bool $trackProgress): array` baut EXAKT die Inertia-Props, die
`LessonController::show()` (jetzt dorthin ausgelagert) aus Live-Daten
baut, aber wahlweise aus einer NIE gespeicherten In-Memory-Kopie mit den
Entwurfswerten (title/teaser/objectives/level/duration_minutes/tools/
requires/glossary_terms/sandbox/lab/rich_content) statt der Live-Lektion
-- `trackProgress: false` verhindert, dass das blosse Ansehen eines
Entwurfs echten Lernfortschritt fuer den Autor anlegt. Neue Route
`author.lessons.edit.preview`.

`nodePreviewProps(Node $node, array $payload): array`: kein echter
`NodeAttempt`/keine Engine-Session in der Vorschau (Betreiber-Vorgabe).
Briefing/Hinweise/Write-up werden stattdessen VOLLSTAENDIG aufgedeckt
gerendert (`hints[].used = true`, `write_up_html` immer gesetzt --
dieselben "bereits geloest"-Zweige, die `Nodes/Show.vue` fuer einen
echten Lauf zeigt, kein zweiter Renderer noetig). `Nodes/Show.vue`
ersetzt den interaktiven Sandbox-/Terminal-/Flag-Teil hinter einem neuen
`preview: true`-Prop durch einen Platzhalter. Neue Route
`studio.nodes.preview`; beide Editoren verlinken jetzt auf ihre eigene
Draft-Vorschau statt (Node) auf `route('nodes.show', ...)`, das fuer
einen unveroeffentlichten Entwurf ohnehin nicht sichtbar waere.

### Phase 7 -- E2E/Regression

Feature-Tests fuer die vollstaendige Abnahmekriterien-Liste: Publish-
Atomaritaet (Live-Schreiben rollt zurueck, wenn der Versionswechsel
danach fehlschlaegt), `restoreVersion()`s Autorenschaft (Ausfuehrender,
nicht historischer Autor) und `restored_from_version_id`, eine Legacy-
`body`-Revision, die per Restore zu einer neuen Rich-Content-Revision
wird und vom Lernenden korrekt gesehen wird, `restoreVersion()`s
Fehlschlag ohne jeden Schreibvorgang bei einer heute ungueltigen
historischen Fassung, Preview-Output identisch zum Live-Learner-Output
fuer dasselbe Dokument, Node Draft-\>Review-\>Publish-\>Learner.

Beim Schreiben dieser Tests zwei echte, vom Mapping nicht vorhergesehene
Luecken gefunden und behoben (nicht Gegenstand der urspruenglichen
Planung, aber direkte Konsequenz des Cutovers):

- `ContentValidator::checkNodeStructure()`s "### h1"-Abschnittspruefung
  lief bisher UNABHAENGIG von `rich_content` und lehnte jede rein per
  Studio angelegte Node (ADR 0109) mit Hints faelschlich ab, obwohl deren
  Hint-Text laengst in `rich_content.hints` statt in ueberschriebenen
  Markdown-Abschnitten steht. Jetzt uebersprungen, sobald `rich_content`
  gesetzt ist -- `NodeActivity::checkHintIdConsistency()` (Phase 1) ist
  die zeitgemaesse Entsprechung.

## Konsequenzen

- `body`/`content/**` bleiben nach jedem neuen Lesson-/Node-Publish
  exakt unveraendert -- keine zwei schreibenden Sources of Truth.
- Ein fehlgeschlagener Publish (Validierungsfund ODER ein Fehler
  waehrend der Transaktion) veraendert weder Live-Daten noch die
  Versionshistorie.
- Restore ist jetzt eine echte, auf die Live-Ressource wirkende
  Operation mit korrekter Autorenschaft, nicht mehr reine Buchfuehrung.
- Autoren sehen eine echte Learner-View ihres ungespeicherten Entwurfs,
  ohne dabei eigenen Lernfortschritt zu erzeugen.
- Der Rich-Content-Editor ist erstmals gegen den vollstaendigen echten
  Bestand nutzbar (Tabellen eingeschlossen).
- Eine rein per Studio angelegte Node (kein `content/nodes/**`) kann ab
  sofort tatsaechlich durch den vollen Draft-\>Review-\>Publish-\>Learner-
  Kreislauf laufen, ohne an einer veralteten datei-basierten Annahme zu
  scheitern.
- `docs/offene-fragen.md` unveraendert.

## Verifikation

- PHP: 656/656 Tests gruen (neue Unit-Tests fuer beide Normalizer und
  die RichContentDocument-Validator-Regeln, Feature-Tests fuer
  `ContentPublishingService`/Restore inkl. Transaktions-Rollback ohne
  Mock -- ueber einen Entwurf im falschen Status statt eines nicht
  mockbaren `final`-Service --, aktualisierte Tests fuer `LessonActivity`/
  `NodeActivity`/`LessonEditorController`/`StudioNodeController`/
  `LessonController`/`NodeController` gegen `rich_content` statt `body`),
  PHPStan Level 7 und `pint --test` gruen.
- Frontend: `vue-tsc --noEmit` sauber (bis auf einen vorbestehenden,
  unabhaengigen Fehler in `Exams/Result.vue`), Vitest 78/78, `vp check`
  sauber.
- Manuell gegen die Dev-DB (Docker, echter Bestand): Node-Editor
  (`oversized-image`, eine der 16/17 Nodes mit Tabelle) und Lesson-Editor
  (`1.4`) im Browser gepruft -- Tabellen rendern/editieren korrekt,
  Draft-Vorschau zeigt denselben Inhalt wie die Learner-Seite, ohne einen
  `lesson_progress`-Eintrag fuer den vorschauenden Autor anzulegen.
