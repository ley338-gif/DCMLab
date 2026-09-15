# 0112 — Rich Content: TipTap-Adapter statt geteiltem Schema, Versionsfehler explizit, `self_check`-Block

## Status

Angenommen, 16.09.2026.

## Kontext

Betreiber-Review von ADR 0111 (CMS-7a), vor Beginn von CMS-7b: die dortige
Aussage "dieselbe Form, die TipTap ohnehin produziert, damit CMS-7b keine
Uebersetzungsschicht braucht" stimmt so nicht. DCMLabs Schema nutzt
`snake_case` (`bullet_list`, `code_block`, `hard_break`, `table_row`,
`table_cell`), TipTops Standardextensions liefern `camelCase`
(`bulletList`, `codeBlock`, `hardBreak`, `tableRow`, `tableCell`).
Auffaelliger noch: `code_block` traegt seinen Inhalt direkt als
`text`-String, waehrend echtes ProseMirror/TipTap-JSON Text immer als
eigenen Kind-Knoten unter `content` modelliert (`{type:"text", text:"..."}`
innerhalb von `content`, nicht als Attribut des Eltern-Knotens).

Zwei kleinere Punkte kamen in derselben Review dazu: Wie soll
`RichContentRenderer` reagieren, wenn ihm ein Dokument mit einer anderen
`version` als der bekannten gegeben wird? Und: der Legacy-Konverter
uebersprang `<details>/<summary>`-Selbstcheck-Bloecke bisher stillschweigend
(vier Stueck allein in Lektion 1.0) -- vor der echten Migration (CMS-7d)
muss das gelost sein, sonst waere sie nicht verlustfrei.

## Entscheidung

**Kein geteiltes Schema -- ein kleiner Adapter.** DCMLab besitzt das
Content-Modell, TipTap ist austauschbare UI dafuer (Betreibervorgabe seit
ADR 0111). Das Schema wird deshalb **nicht** verbogen, um 1:1 zu TipTaps
StarterKit-Ausgabe zu passen -- das wuerde TipTap durch die Hintertuer doch
wieder zur Domain machen. Stattdessen wird CMS-7b einen kleinen, expliziten
`RichContentEditorAdapter` (client-seitig, TypeScript -- TipTap laeuft im
Browser) einfuehren, der beim Laden DCMLab-JSON in TipTap-JSON uebersetzt
und beim Speichern TipTap-JSON zurueck in DCMLab-JSON, bevor es beim Server
ankommt (`RichContentValidator` sieht nie TipTap-Form). Diese ADR
korrigiert damit ADR 0111s "keine Uebersetzungsschicht noetig" ausdruecklich
-- die Uebersetzung existiert, sie ist nur bewusst klein und an einer
Stelle konzentriert, statt das Schema selbst zu verwaessern.
Betroffen: `bullet_list`/`ordered_list`/`list_item`/`code_block`/
`hard_break`/`table_row`/`table_cell` (Namensform) und `code_block` (Text
als `content`-Kind statt `text`-Attribut in TipTaps Form). Der Adapter
selbst ist nicht Teil dieser ADR -- er entsteht mit CMS-7b, sobald TipTap
tatsaechlich im Projekt liegt.

**Bestaetigt und unveraendert** (aus ADR 0111 uebernommen, bewusst nicht
Teil dieser Korrektur): `version: 1` am Dokument, `code_block.attrs.variant`
fuer `code`/`console`/`terminal`/`diagram`/`mermaid`, `glossary_term` als
eigener semantischer Inline-Knoten, Tabellen als strukturierte Daten,
Activities explizit ausserhalb des Rich-Content-Dokuments (bleiben eigene
Composer-Elemente, ADR 0105), serverseitige Validierung unabhaengig vom
Editor, Renderer unabhaengig vom Editor.

**Unbekannte Version: Exception statt stiller Fallback.**
`RichContentRenderer::render()` prueft jetzt `doc.version` selbst und wirft
`UnsupportedRichContentVersionException`, wenn sie nicht der bekannten
Version entspricht -- sobald echte Dokumente in der DB liegen und das
Schema irgendwann auf Version 2 wechselt, darf ein `version: 1`-Renderer ein
neueres Dokument nicht "irgendwie" darstellen. Eine echte Versions-Migration
(v1 -> v2) ist eine eigene, spaetere Entscheidung; `RichContentValidator`
meldet eine falsche Version weiterhin als gewoehnlichen Validierungsbefund
(String in der Ergebnisliste) -- die Exception ist gezielt eine
Rendering-Angelegenheit, weil dort ein stiller Fallback ein falsch
dargestelltes Dokument waere, nicht nur ein uebersprungener Speichervorgang.

**`self_check` als eigener Block statt `raw_html`.** Ein `<details>`-
Selbstcheck ist fachlich ein Lernbaustein ("Antwort anzeigen"), kein
beliebiges eingebettetes HTML. Neuer Blocktyp:

```json
{
  "type": "self_check",
  "attrs": { "summary": "Antwort anzeigen" },
  "content": [ /* Bloecke */ ]
}
```

`RichContentValidator` prueft `attrs.summary` (nicht-leerer String) und den
`content` wie bei jedem anderen Container-Block (`blockquote`,
`table_cell`). `RichContentRenderer` erzeugt `<details><summary>...
</summary>...</details>` -- dasselbe Element, das der reale Bestand ohnehin
schon per rohem HTML verwendet. `MarkdownToRichContentConverter` erkennt das
Muster jetzt aktiv: CommonMark fasst `<details>` und die direkt folgende
`<summary>...</summary>`-Zeile (keine Leerzeile dazwischen) zu einem
einzigen `HtmlBlock` zusammen, `</details>` wird -- empirisch am echten
Bestand geprueft -- zu einem eigenen, separaten `HtmlBlock`, selbst wenn
davor keine Leerzeile steht. Der Konverter liest deshalb Geschwister-Knoten
ab dem Start-Marker weiter (statt jeden Knoten isoliert zu betrachten), bis
der Schluss-Marker kommt. Alle vier Selbstcheck-Bloecke in Lektion 1.0
konvertieren damit korrekt zu `self_check`.

## Konsequenzen

- Lektion 1.0s Body konvertiert jetzt vollstaendig verlustfrei bezogen auf
  alle heute bekannten Konstrukte (Tabelle, kein-beispiel-Diagramm,
  annotierte Code-Bloecke, Glossarbegriffe, alle vier Selbstchecks) --
  nur Bilder und alleinstehende horizontale Trennlinien bleiben offene,
  im Bestand nicht vorkommende Faelle.
- CMS-7b muss den `RichContentEditorAdapter` explizit einplanen (Aufwand
  bewusst klein gehalten: eine feste Namens-Zuordnungstabelle plus die
  eine `code_block`-Sonderregel), nicht implizit davon ausgehen, TipTaps
  Ausgabe liesse sich unveraendert speichern.
- `docs/offene-fragen.md` unveraendert -- diese ADR schliesst eine
  innerhalb von ADR 0111 selbst benannte Luecke, bevor sie zu Altlast wird.

## Verifikation

- Alle 580 Tests (13 neu: Versions-Exception, `self_check`-Validierung/
  -Rendering, `self_check`-Erkennung im Konverter inklusive aller vier
  echten Faelle aus Lektion 1.0), PHPStan Level 7 und `pint --test` sind
  gruen.
