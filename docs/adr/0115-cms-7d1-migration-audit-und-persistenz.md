# 0115 — Migration Readiness: Audit-Werkzeug und Persistenz-Grundlage (CMS-7d.1)

## Status

Angenommen, 30.09.2026. **Ergaenzt durch ADR 0116:** die dort unten unter
"Bewusst nicht Teil dieser ADR" genannte Aussage, ein neuer
`thematic_break`-Blocktyp sei eine spaetere CMS-7d.2-Entscheidung, wurde
nach Betreiber-Review sofort entschieden -- `horizontal_rule` ist bereits
ein echter v1-Block, das Audit meldet 0 statt 9 blockierende Funde.

## Kontext

CMS-7a/7b/7c (ADR 0111-0114) haben Schema, Renderer, Legacy-Konverter und
Editor gebaut -- vollstaendig isoliert, ohne eine einzige echte Lektion
oder Node zu beruehren. Betreiberauftrag CMS-7d ist der eigentliche
Umzug: produktive Daten, historische Revisionen, ein echter
Formatwechsel. Ausdruecklich **kein einzelner PR** dafuer, sondern
mehrere, jederzeit rueckrollbare Stufen. Diese ADR ist die erste davon
(CMS-7d.1): Audit + Persistenz-Grundlage, bevor irgendein Bestandsdatum
angefasst wird.

Zwei Fragen mussten vor jeder Migration beantwortet sein:

1. **Kann jede bestehende Lektion/Node ueberhaupt verlustfrei konvertiert
   werden?** ADR 0111/0112 vermuteten "Bilder, horizontale Trennlinien,
   sonstiges rohes HTML kommen im echten Bestand nicht vor" -- eine
   Annahme, keine Pruefung.
2. **Welche DB-Struktur bekommt `nodes.rich_content`?** Anders als bei
   Lesson (ein Fliesstext) ist Nodes `body` gleichzeitig Speicherformat
   fuer drei fachlich getrennte Bereiche (Briefing/Hints/Write-up,
   `NodeSections` nutzt `## Briefing`/`### h1`.../`## Write-up` als
   strukturelle Schluessel). Diese Struktur soll NICHT unsichtbar ueber
   H2/H3-Ueberschriften im WYSIWYG weiterleben.

## Entscheidung

**`rich-content:audit` (neues Artisan-Command, liest nur, schreibt
nichts, Gegenstueck zu `content:validate`).** Prueft fuer jede reale
Lektion und Node, ob eine Rich-Content-Konvertierung moeglich ist:

- Lektionen: nur `QuizContent::splitBody($body)['before']` (der
  Prosa-Teil) -- der Quiz-Block bleibt strukturierte Markdown-Syntax,
  kein Rich-Content-Ziel.
- Nodes: `NodeSections::parse($body)` zuerst, dann Briefing/jeder
  Hint/Write-up EINZELN gepruefen -- passend zum Envelope-Format unten.
- Blockierend: jeder Rich-Content-Validator-Verstoss, sowie alles, was
  `MarkdownToRichContentConverter::skippedNodes()` (neu, siehe unten)
  meldet -- unbekanntes rohes HTML, horizontale Trennlinien, Bilder,
  jeder sonst unbehandelte Knotentyp. Kein `raw_html`-Fallback, um das
  zu umgehen (Betreiber-Vorgabe: "blockieren... bis das Konstrukt
  semantisch modelliert oder manuell bereinigt wurde").
- Nicht blockierend: eine Abweichung im reinen Textinhalt zwischen dem
  bisherigen `MarkdownRenderer`- und dem neuen `RichContentRenderer`-
  Rendering desselben Abschnitts -- nur ein Hinweis, meist ohnehin Folge
  eines bereits blockierend gemeldeten Funds im selben Abschnitt (dann
  unterdrueckt, um nicht doppelt zu warnen).
- Fundstelle: bei Lektionen die echte `de.md`-Zeile (der
  `body_start_line`-Offset aus `FrontMatter::parse()` macht die
  konverter-interne, auf `prose` relative Zeile wieder zur Dateizeile).
  Bei Node-Abschnitten relativ zum jeweiligen Abschnittsinhalt (nach
  Entfernen der Ueberschrift durch `NodeSections`) -- zusammen mit
  Node-Slug und Abschnittsname trotzdem eindeutig, nur ohne direkten
  Dateibezug.

**`MarkdownToRichContentConverter::skippedNodes()` (neu).** Der
Konverter verwirft nicht mehr stillschweigend, was er nicht abbilden
kann -- er protokolliert es zusaetzlich (Typ, Zeile, Textausschnitt),
abrufbar nach `convert()`. `convert()`s Rueckgabe und Verhalten bleiben
unveraendert; die bisherigen Silent-Skip-Pfade (`HtmlBlock`,
`ThematicBreak`, inline `Image`, jeder sonst unbehandelte Knotentyp)
rufen jetzt zusaetzlich eine Protokoll-Hilfsfunktion auf. Der
"kein-beispiel"-Marker und ein erkannter `self_check` zaehlen
ausdruecklich NICHT als Skip -- die sind bereits sinnvoll verarbeitet.

**Real gefunden, nicht vermutet:** Der erste Lauf gegen `content/` zeigt
zwei Dinge, die die bisherigen Annahmen korrigieren:

1. Lektion 1.0 verwendet `---` als reinen visuellen Abschnittstrenner
   vor `##`-Ueberschriften -- **neun Mal**, kein Einzelfall. ADR
   0111/0112s Annahme "kommt im echten Bestand nicht vor" war falsch.
   Das bleibt bewusst ein blockierender Audit-Fund (kein Freifahrtschein
   fuer einen sofortigen `thematic_break`-Blocktyp) -- ob das ein
   echter Rich-Content-Blocktyp wird oder vor der Migration manuell
   entfernt wird, ist eine CMS-7d.2-Entscheidung, kein Nebenprodukt
   dieser ADR.
2. **Echter Bug in `MarkdownToRichContentConverter::convertText()`**:
   eine Tabellenzelle (oder jeder andere Textknoten), deren gesamter
   Inhalt aus GENAU einem `{{term:x}}` ohne umgebenden Text besteht
   (z. B. Lektion 1.5, Zeile 132: `| {{term:ae-title}} | ... |`), wurde
   NICHT aufgeloest. Ursache: ein Kurzschluss `count($parts) === 1` nach
   `preg_split()`, gedacht als "kein Treffer", trifft aber auch zu, wenn
   der gesamte String aus einem einzigen Treffer besteht. Behoben, indem
   jeder `preg_split()`-Teil einzeln gegen das Glossar-Muster geprueft
   wird, unabhaengig von der Teileanzahl -- mit Regressionstest gegen
   genau diesen Fall. Ohne das Audit-Werkzeug waere dieser Bug erst beim
   echten Cutover sichtbar geworden.

**`nodes.rich_content`-Envelope (festgezogen VOR jeder Migration, wie
vom Betreiber gefordert):**

```json
{
  "type": "node_content",
  "version": 1,
  "briefing": { "type": "doc", "version": 1, "content": [] },
  "hints": {
    "h1": { "type": "doc", "version": 1, "content": [] }
  },
  "write_up": { "type": "doc", "version": 1, "content": [] }
}
```

`briefing`/jeder Hint/`write_up` ist ein eigenstaendiges
`RichContentDocument` -- `RichContentEditor.vue` bleibt dadurch
unveraendert generisch (ein Editor pro Feld), die Node-Studio-Seite
zeigt spaeter (CMS-7d.3) einfach mehrere Editor-Instanzen nebeneinander,
statt Autoren weiterhin Kontroll-Ueberschriften editieren zu lassen.
`lessons.rich_content` bleibt dagegen ein einzelnes
`RichContentDocument` ohne Umschlag -- Lesson und Node bekommen bewusst
UNTERSCHIEDLICHE Persistenzstrukturen, weil ihre fachliche Struktur
unterschiedlich ist.

**Zwei rein additive Migrationen:** `lessons.rich_content` und
`nodes.rich_content`, beide `jsonb().nullable()`, `body`
unveraendert. Nichts schreibt diese Spalten bisher (kein
`content:sync`-Anschluss, kein Controller, kein Publisher) --
Backfill ist CMS-7d.2, Autoren-/Publish-Cutover CMS-7d.3. Beide
Model-Klassen (`Lesson`, `Node`) bekommen den `array`-Cast und werden
zu `#[Fillable]` ergaenzt, rein deklarativ.

**Bewusst nicht Teil dieser ADR:** ein neuer `thematic_break`- oder
Bild-Blocktyp im Schema (erst wenn CMS-7d.2 entscheidet, wie mit dem
jetzt bekannten Fund umgegangen wird); jede Erweiterung von
`lesson_elements.content_block_id` zu einem generischen
Multi-Block-System (das kommt erst, wenn eine Lesson mehrere getrennte
Rich-Content-Elemente authoren koennen soll -- ausserhalb des aktuellen
Auftrags); jeder Schreibzugriff auf `rich_content` (Audit ist reines
Lesen).

## Konsequenzen

- `content/` hat jetzt ein zweites Audit-Werkzeug neben
  `content:validate`: `rich-content:audit` fuer die Migrations-Frage
  "kann das verlustfrei nach Rich Content?", waehrend `content:validate`
  weiterhin "erfuellt das die Autoren-/Struktur-Regeln?" prueft. Beide
  laufen unabhaengig, gegen dieselbe `ContentRepository`.
- `MarkdownToRichContentConverter::skippedNodes()` ist ab jetzt Teil der
  oeffentlichen Schnittstelle des Konverters -- jede zukuenftige neue
  Silent-Skip-Stelle (falls je eine entsteht) muss ebenfalls darueber
  protokollieren, sonst wird sie vom Audit nicht erkannt.
- Der `{{term:x}}`-Bugfix wirkt sich auf ALLE Aufrufer von
  `MarkdownToRichContentConverter::convert()` aus (auch die bereits
  gemergten CMS-7a/7b/7c-Tests) -- keiner der bestehenden 75 (vorher 67)
  RichContent-Tests aendert dadurch sein erwartetes Ergebnis, weil kein
  bisheriger Test einen isolierten Glossarbegriff als gesamten
  Zelleninhalt abgedeckt hatte (jetzt durch zwei neue Regressionstests
  geschlossen).
- `lessons`/`nodes` haben ab sofort eine `rich_content`-Spalte, aber sie
  ist ueberall `null` -- kein Verhalten aendert sich fuer Lerner,
  Autoren oder API. `docs/offene-fragen.md` unveraendert.
- Naechster Schritt CMS-7d.2: tatsaechlicher Backfill
  (`lessons.rich_content` aus `QuizContent::splitBody()['before']`,
  `nodes.rich_content` im obigen Envelope aus `NodeSections::parse()`),
  plus eine bewusste Entscheidung, was mit den neun gefundenen
  `---`-Trennern in Lektion 1.0 geschieht (Schema-Erweiterung vs.
  manuelle Bereinigung) -- erst dann Read-Cutover.

## Verifikation

- `rich-content:audit` gegen den echten `content/`-Bestand: 9
  blockierende Funde (alle: Lektion 1.0, `horizontale_trennlinie`,
  Zeilen 39/60/124/212/235/264/272/282/316), 0 nach dem Beheben des
  `{{term:x}}`-Bugs verbleibende nicht-blockierende Hinweise.
- PHP: 608/608 Tests gruen (16 neu: 6 `skippedNodes()`-Tests, 2
  Regressionstests fuer den `{{term:x}}`-Bug, 8 Feature-Tests fuer
  `rich-content:audit` gegen minimale Fixtures), PHPStan Level 7 und
  `pint --test` gruen.
- Migrationen laufen sauber gegen die SQLite-Testdatenbank
  (`RefreshDatabase`, voller Testlauf gruen); `jsonb()` ist eine
  dokumentierte Laravel-Blueprint-Methode, erzeugt auf Postgres den
  nativen `jsonb`-Spaltentyp.
