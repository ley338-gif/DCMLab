# 0111 — Rich-Content-Domain: Schema, Renderer, Legacy-Konverter (CMS-7a)

## Status

Angenommen, 16.09.2026. **Teilweise korrigiert durch ADR 0112:** die
Aussage weiter unten, TipTap produziere "dieselbe Form" und CMS-7b brauche
"keine Uebersetzungsschicht", stimmt nicht -- ADR 0112 legt stattdessen
einen kleinen, expliziten `RichContentEditorAdapter` fest. Alle anderen
Entscheidungen dieser ADR bleiben unveraendert gueltig.

## Kontext

CMS-6d hat Lesson und Node vollstaendig DB-gefuehrt gemacht (ADR 0101/0107)
und ihnen einen echten Studio-Editor gegeben (ADR 0102/0104/0108/0109) --
`body` bleibt dabei aber weiterhin ein einzelnes Markdown-Textfeld, editiert
per `<textarea>`. Betreiberauftrag CMS-7: einen echten Rich-Content-Editor
(TipTap 3, MIT-lizenziert) fuer Lesson und Node einfuehren, ohne dass der
Editor das Backend-Design diktiert -- also zuerst das Content-**Modell**
festziehen (strukturiertes, versioniertes JSON-Dokument als Quelle der
Wahrheit, TipTap nur EIN Editor dafuer), dann erst den Editor selbst (CMS-7b)
und die DCMLab-eigenen Blocktypen (CMS-7c) darauf aufsetzen.

CMS-7a liefert bewusst nur das Fundament: Schema, Validator, Renderer,
Legacy-Konverter. Kein Editor, keine DB-Spalte, keine Aenderung an
Lesson/Node/Composer -- die bestehenden `body`-Felder bleiben bis CMS-7d
unangetastet Markdown.

Direkt vor CMS-7a stand ein kleiner CMS-6d-Haertungsfix (ADR 0110): eine per
Studio angelegte, noch nicht freigegebene Node war fuer jeden angemeldeten
Lernenden sofort spielbar, nicht nur fuer ihren Autor -- `NodeController`
zeigt Lernenden jetzt nur noch `status: published`, "Vorschau" ist separat
ueber `ActivityPolicy` autorisiert.

## Entscheidung

**Schema** (`App\Content\RichContent\RichContentValidator`, Version 1): ein
`{type: "doc", version: 1, content: [...]}`-Dokument aus verschachtelten
Knoten im TipTap/ProseMirror-Vokabular (`type`/`attrs`/`content`/`text`/
`marks`) -- absichtlich dieselbe Form, die TipTap ohnehin produziert, damit
CMS-7b spaeter keine Uebersetzungsschicht braucht, aber unabhaengig von
TipTap validierbar und interpretierbar.

- Bloecke: `paragraph`, `heading` (`attrs.level` 1-6), `bullet_list`/
  `ordered_list` (aus `list_item`), `blockquote`, `code_block`
  (`attrs.variant`: `code`/`console`/`terminal`/`diagram`/`mermaid`,
  optional `attrs.language`, Inhalt als reiner `text`-String statt
  Rich-Inline-Content -- ein Code-Block hat keine Fett-/Kursiv-Schrift),
  `table` (aus `table_row`/`table_cell`, `attrs.header`).
- Inline: `text` (mit `marks`: `bold`, `italic`, `code`, `link` mit
  `attrs.href`), `glossary_term` (`attrs.slug`, ersetzt `{{term:x}}`),
  `hard_break`.
- Bewusst kein generisches JSON-Schema-Paket: `RichContentValidator` ist
  eine kleine, deterministische PHP-Pruefung im selben Stil wie
  `ContentValidator` -- keine zusaetzliche Abhaengigkeit fuer eine
  ueberschaubare Knotenmenge.

**Renderer** (`RichContentRenderer`): das strukturelle Gegenstueck zu
`MarkdownRenderer` -- erzeugt bewusst **dasselbe** HTML (Ueberschriften-Anker
via `HeadingSlug`, Glossar-Tooltip-Markup, `.lesson-code`/`.lesson-console`/
`.lesson-terminal`/`.lesson-diagram`/`<pre class="mermaid">`), damit das
bestehende Frontend-CSS/JS unveraendert weiterfunktioniert, sobald ein
Dokument statt Markdown durch die Pipeline laeuft. Erwartet ein bereits
validiertes Dokument; ein fehlender/falscher Wert bekommt einen stillen
Fallback statt eines Fehlers -- Validierung ist Sache von
`RichContentValidator`, nicht doppelt Sache des Renderers.

**Legacy-Konverter** (`MarkdownToRichContentConverter`): wandelt
bestehenden Markdown-Fliesstext in ein Rich-Content-Dokument -- Grundlage
fuer die eigentliche Migration (CMS-7d). Arbeitet auf dem echten
CommonMark-AST (`League\CommonMark\Parser\MarkdownParser::parse()`,
dieselbe Erweiterungs-Umgebung wie `MarkdownRenderer`, jetzt in
`MarkdownEnvironmentFactory` an einer Stelle geteilt, damit beide fuer
denselben Text garantiert dieselbe Struktur sehen) statt auf gerendertem
HTML oder eigenem Regex-Parsing zu arbeiten -- ein Baum, den eine
ausgereifte Bibliothek schon korrekt aufgebaut hat, ist verlaesslicher als
ein zweiter, selbstgebauter Parser. Erkennt dieselben Code-Block-Varianten
wie `MarkdownRenderer` (kein-beispiel-Marker -> `diagram`, `mermaid`-Sprache
-> `mermaid`, andere Sprache -> `code`, sonst Prompt-Heuristik ->
`console`/`terminal`) und loest `{{term:x}}` in `glossary_term`-Knoten auf.

**Bewusst (noch) nicht abgedeckt**, weil im echten Bestand nicht vorkommend
oder nicht angefragt: Bilder, horizontale Trennlinien als eigenstaendiger
Block, eingebettetes rohes HTML ausser dem `kein-beispiel`-Marker (z. B. die
`<details>`-Selbstcheck-Bloecke mancher Lektionen) -- ein solcher Knoten
wird beim Konvertieren stillschweigend uebersprungen, nicht als Fehler
gemeldet. Kein Verlust fuer CMS-7a selbst (nichts wird heute umgestellt),
aber eine offene Aufgabe fuer den echten Migrationsschritt in CMS-7d.

**Kein Composer-Element pro Rich-Content-Baustein.** Wie vom Betreiber
festgelegt: ein Rich-Content-Dokument bleibt der Inhalt EINES
`lesson_elements`-Eintrags vom Typ `content` (ADR 0105) -- Sandbox/Node/
Quiz-Activities bleiben eigene Composer-Elemente daneben, nicht Knoten
innerhalb des Dokuments. TipTap editiert ausschliesslich den Inhalt eines
`content`-Elements.

## Konsequenzen

- `MarkdownRenderer`s CommonMark-Umgebung ist jetzt in
  `MarkdownEnvironmentFactory` ausgelagert (rein mechanisch, kein
  Verhaltensunterschied, per Test bewiesen) -- Voraussetzung dafuer, dass
  Renderer und Konverter garantiert dieselbe Markdown-Interpretation sehen.
- `docs/offene-fragen.md` unveraendert -- CMS-7a aendert keinen
  bestehenden Anwendungsfall, es fuegt nur neue, ungenutzte Klassen hinzu.
- Naechste Schritte bleiben CMS-7b (gemeinsame `RichContentEditor.vue` mit
  TipTap 3 fuer Paragraph/Heading/Bold/Italic/Lists/Link/Code Block/Quote),
  CMS-7c (DCMLab-Blocktypen: Info/Warning, DICOM Tag Table, DICOM Dump,
  Terminal/Command, Glossary Reference), CMS-7d (Migration bestehender
  Bodies, Preview, Paste-Verhalten, Autosave).

## Verifikation

- Alle 566 Tests (48 neu: `RichContentValidatorTest`,
  `RichContentRendererTest`, `MarkdownToRichContentConverterTest`
  einschliesslich eines Konvertierungstests gegen den echten Body von
  Lektion 1.0), PHPStan Level 7 und `pint --test` sind gruen.
- Der Konverter-Test gegen echten Bestand beweist: kein Absturz, ein
  valides Dokument, und die zentralen Konstrukte (Tabelle,
  kein-beispiel-Diagramm, annotierter Code-Block, Glossarbegriffe `scu`/
  `scp`) kommen tatsaechlich an.
