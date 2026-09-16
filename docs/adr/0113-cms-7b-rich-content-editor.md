# 0113 — Gemeinsamer Rich-Content-Editor: TipTap 3 + Adapter (CMS-7b)

## Status

Angenommen, 16.09.2026.

## Kontext

CMS-7a (ADR 0111/0112) hat das DCMLab-v1-Rich-Content-Schema, seinen
serverseitigen Validator, Renderer und Legacy-Konverter geliefert -- noch
ohne jeden Editor. Betreiberauftrag CMS-7b: einen echten, gemeinsamen
Editor fuer Lesson/Node auf Basis von TipTap 3 (MIT-lizenziert, nur
OSS-Editorpakete, keine Cloud-/Pro-Abhaengigkeiten), der ausschliesslich
den Inhalt eines `content`-Composer-Elements editiert (ADR 0105) --
Activities bleiben eigene Composer-Elemente daneben, nicht Teil des
Rich-Content-Dokuments.

ADR 0112 hatte bereits festgelegt: DCMLab besitzt das Schema, TipTap ist
austauschbare UI dafuer, ein kleiner `RichContentEditorAdapter` uebersetzt
zwischen beiden Formen (`snake_case` vs. TipTaps `camelCase`,
`code_block.text` vs. TipTaps verschachtelter Text-Node). Drei
Leitplanken vom Betreiber fuer diesen Slice: der Adapter muss verlustfrei
roundtrippen (insbesondere `code_block.attrs.variant`/`language`), ein
nicht unterstuetzter DCMLab-Knoten (`self_check`, `table`,
`glossary_term` -- folgen erst mit CMS-7c) darf beim Laden nie
stillschweigend verschwinden, und der Slice bleibt vollstaendig isoliert
(kein DB-Feld, kein Controller, kein Publisher, keine Migration
bestehender Bodies).

## Entscheidung

**Pakete** (alle MIT, per `npm view <pkg> license` vor der Installation
geprueft): `@tiptap/core`, `@tiptap/pm`, `@tiptap/vue-3`,
`@tiptap/starter-kit`, `@tiptap/extension-code-block` (explizit, weil
direkt importiert und erweitert -- nicht nur transitiv ueber StarterKit),
`@floating-ui/dom` (Peer-Dependency von `@tiptap/vue-3`).

**`RichContentEditorAdapter.ts`** (`resources/js/lib/richContent/`):
`toTipTap()`/`fromTipTap()`, reine Funktionen ohne Vue-/TipTap-
Seiteneffekte. Namens-Zuordnung ausschliesslich fuer die in CMS-7b
unterstuetzten Typen (`bullet_list`↔`bulletList`,
`ordered_list`↔`orderedList`, `list_item`↔`listItem`,
`code_block`↔`codeBlock`, `hard_break`↔`hardBreak`; `paragraph`,
`heading`, `blockquote`, `text`, `bold`, `italic`, `code`, `link` heissen
in beiden Welten gleich). `code_block`: DCMLabs `text`-Attribut wird beim
Hin- und Herweg explizit ein-/ausgepackt in TipTaps
`content:[{type:"text",text}]`-Form. Jeder DCMLab- oder TipTap-Knotentyp
ausserhalb dieser Liste (`self_check`, `table`, `table_row`, `table_cell`,
`glossary_term`) wirft `UnsupportedEditorNodeError` -- sowohl beim Laden
(`toTipTap`) als auch defensiv beim Zurueckwandeln (`fromTipTap`, sollte
durch das geschlossene Editor-Schema nie erreicht werden, ist aber kein
stiller Fallback).

**`tiptapExtensions.ts`**: `richContentExtensions()` liefert genau den
Betreiber-Scope (Paragraph, Heading 2-4, Bold, Italic, Inline Code, Link,
Bullet/Ordered List, Blockquote, Code Block, Undo/Redo) ueber
`StarterKit.configure({...})` (Strike/Underline/HorizontalRule
deaktiviert -- kein Teil des DCMLab-Schemas). `RichContentCodeBlock`
erweitert TipTaps eingebaute `CodeBlock`-Extension um ein echtes
`variant`-Attribut (Default `terminal`) -- ohne diese Erweiterung haette
ProseMirror das Attribut bei jedem `Node.fromJSON()`/`getJSON()`
stillschweigend verworfen, weil ein nicht im Schema deklariertes Attribut
nie ankommt.

**`RichContentEditor.vue`** (`resources/js/components/RichContent/`):
kennt nach aussen ausschliesslich `RichContentDocument` --
`modelValue`/`update:modelValue` sind nie TipTap-JSON. Ein Wechsel von
`modelValue` von aussen laedt den Editor-Inhalt neu, **ausser** wenn der
neue Wert inhaltlich mit dem aktuellen Editor-Zustand uebereinstimmt (per
`fromTipTap(editor.getJSON())`-Vergleich) -- sonst wuerde die eigene
`onUpdate`-Ruecklaufschleife bei jedem Tastendruck den Cursor
zuruecksetzen. Ein nicht unterstuetzter Knoten fuehrt zu einem
`unsupported-node`-Event und einer Fehlermeldung statt eines Editors --
kein Absturz, kein stiller Teilverlust. `defineExpose({ editor })` gibt
der umgebenden Seite (kuenftige Toolbar) und Tests Zugriff auf den
TipTap-Editor, ohne dass `modelValue`/`update:modelValue` dadurch ihren
Vertrag verlieren.

**Isolation** (Betreiber-Vorgabe): keine `lessons.rich_content`/
`nodes.rich_content`-Spalte, keine Controller- oder Publisher-Aenderung,
keine Migration bestehender Bodies. Der Slice besteht ausschliesslich aus
`RichContentEditor.vue`, `RichContentEditorAdapter.ts`,
`tiptapExtensions.ts`, `types/richContent.ts` und Vitest-Tests -- nichts
davon wird von einer bestehenden Seite importiert (per `npm run build`
bestaetigt: keine der neuen Dateien landet im produktiven Bundle).

## Konsequenzen

- Alle vom Betreiber verlangten Nachweise sind erbracht (siehe
  Verifikation): Roundtrip fuer jeden Block-/Mark-Typ, `code_block`-
  Variante+Sprache bleiben erhalten (sowohl in der reinen
  Adapter-Pruefung als auch durch das echte ProseMirror-Schema
  hindurch), `self_check`/`table`/`glossary_term` werfen statt still zu
  verschwinden, der Editor emittiert nachweislich nur DCMLab-v1-JSON
  (kein `"bulletList"`/`"codeBlock"` im emittierten Wert), Undo/Redo
  funktioniert, keine Persistenz beruehrt.
- `docs/offene-fragen.md` unveraendert -- dieser Slice fuehrt keine neue
  offene Frage ein.
- Naechste Schritte: CMS-7c (DCMLab-Bloecke -- Info/Warning, DICOM Tag
  Table, DICOM Dump, Terminal/Command, Glossary Reference, `self_check`
  editorfaehig machen), danach CMS-7d (Migration bestehender Bodies,
  Preview, Paste-Verhalten, Autosave, echter Cutover in
  `lessons.rich_content`/`nodes.rich_content`).

## Verifikation

- `npm run test` (Vitest): 45/45 gruen (10 neu in
  `RichContentEditorAdapter.spec.ts`, 8 neu in `tiptapExtensions.spec.ts`
  gegen das echte ProseMirror-Schema, 5 neu in
  `RichContentEditor.spec.ts`).
- `npx vue-tsc --noEmit`: keine neuen Fehler (ein bestehender,
  unveraenderter Fehler in `Exams/Result.vue` bleibt unberuehrt).
- `npm run check`: 0 Formatierungs-/Lint-Befunde.
- `npm run build`: erfolgreich: keine der neuen Dateien wird gebuendelt,
  solange kein Consumer sie importiert -- Beleg fuer die geforderte
  Isolation.
