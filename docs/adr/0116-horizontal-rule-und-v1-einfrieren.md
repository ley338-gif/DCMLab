# 0116 — `horizontal_rule` als echter v1-Block, Schema v1 eingefroren, Audit als Migrations-Gate

## Status

Angenommen, 30.09.2026.

## Kontext

`rich-content:audit` (ADR 0115, CMS-7d.1) hat gegen den echten
`content/`-Bestand neun blockierende Funde gemeldet: Lektion 1.0
verwendet `---` neun Mal als visuellen Abschnittstrenner vor
`##`-Ueberschriften. `docs/offene-fragen.md` hielt das ausdruecklich als
Betreiberentscheidung fest, nicht als Code-Agenten-Entscheidung: ein
neuer `horizontal_rule`-Blocktyp im Schema, oder manuelle Bereinigung
der Lektion vor dem Backfill.

Der Blick in die Lektion zeigt: die neun Trenner sind kein
zufaelliges Markdown-Artefakt, sondern werden konsistent als
visueller/semantischer Abschnittstrenner zwischen groesseren
Themenbloecken eingesetzt.

## Entscheidung

**`horizontal_rule` wird ein echter DCMLab-v1-Blocktyp**, nicht
manuell aus Lektion 1.0 entfernt. Ein horizontaler Trenner ist ein
legitimer, extrem simpler Rich-Content-Baustein -- kein Grund, ihn aus
dem Bestand zu tilgen, nur damit das Schema einen Typ weniger hat.

```json
{ "type": "horizontal_rule" }
```

Der einfachste Blocktyp im gesamten Schema: kein `attrs`, kein
`content` -- ein reines Marker-Objekt. Durchgezogen durch den ganzen
Stack:

- **`RichContentValidator`**: `horizontal_rule` zu `BLOCK_TYPES`
  ergaenzt, `validateBlock()`-Match-Arm gibt `[]` zurueck (nichts zu
  pruefen).
- **`RichContentRenderer`**: `'horizontal_rule' => '<hr>'`.
- **`MarkdownToRichContentConverter`**: `ThematicBreak`-Knoten werden
  jetzt zu `{ type: 'horizontal_rule' }` konvertiert, statt (wie in
  ADR 0115 zwischenzeitlich) als Skip protokolliert zu werden --
  `skippedNodes()` deckt weiterhin unbekanntes rohes HTML, Bilder und
  jeden sonst unbehandelten Knotentyp ab, aber nicht mehr Trennlinien.
- **`RichContentEditorAdapter`** (TS): `horizontal_rule` ↔
  `horizontalRule` in beide Richtungen; TipTaps eingebaute
  `HorizontalRule`-Extension aus `StarterKit` (bisher `false`
  konfiguriert) reicht dafuer unveraendert aus -- kein eigener
  Custom-Node noetig, anders als bei `callout`/`self_check`/
  `dicom_tag_table` (ADR 0114).
- **Slash-Menue**: `/trenner`/`/divider` (Keywords: `divider`,
  `trenner`, `trennlinie`, `hr`, `horizontal`) -- keine eigene
  Toolbar-Schaltflaeche noetig.

**Schema v1 wird ab jetzt eingefroren.** Der Zeitpunkt ist bewusst
gewaehlt: `lessons.rich_content`/`nodes.rich_content` sind gerade erst
nullable/additiv angelegt (ADR 0115), noch keine Lesson/Node nutzt
Rich Content als Source of Truth. Das ist der letzte guenstige Moment,
`v1` vollstaendig zu machen, ohne bereits gespeicherte Dokumente
migrieren zu muessen. Nach dem CMS-7d.2-Backfill gilt: `v1` ist
eingefroren, eine strukturell inkompatible Aenderung braucht `v2` plus
eine echte Dokument-Migration (nicht mehr "einfach ein Feld ergaenzen"
wie bisher bei ADR 0114/0116).

**`rich-content:audit` wird zum Migrations-Gate.** Ab sofort gilt: das
geplante Backfill-Command (`rich-content:migrate`, CMS-7d.2) darf nur
laufen, wenn `rich-content:audit` 0 blockierende Funde meldet. Die
Sicherheitskette:

```
Real Legacy Content
        ↓
rich-content:audit
        ↓
0 blockierende Funde erforderlich
        ↓
rich-content:migrate
        ↓
RichContentValidator
        ↓
DB-Transaktion
```

Kommt spaeter neues, nicht modelliertes rohes HTML oder ein anderes
unbekanntes Konstrukt in den Legacy-Bestand, faellt die Migration
kontrolliert um (Audit rot), statt Inhalt stillschweigend zu
verschlucken. Die genaue technische Kopplung (Artisan-Command prueft
den Audit-Exitcode vor dem eigentlichen Schreiben) ist Teil von
CMS-7d.2, nicht dieser ADR.

## Konsequenzen

- `docs/offene-fragen.md`s Eintrag zu den neun `---`-Funden ist
  aufgeloest (siehe dortige Aktualisierung) -- keine offene
  Betreiberfrage mehr.
- `rich-content:audit` meldet gegen den echten Bestand jetzt 0
  blockierende Funde (42 Lektionen, 17 Nodes) -- CMS-7d.2 kann ohne
  eine vorgelagerte manuelle Content-Bereinigung starten.
- Jedes bestehende CMS-7a/7b/7c-Dokument bleibt gueltig (additive
  Schema-Erweiterung, mit Regressionstest abgesichert).
- CMS-7d.2 ist jetzt konkret auf vier Punkte eingegrenzt: (1) diese
  ADR als geschlossen betrachten, (2) einen idempotenten
  `rich-content:migrate --dry-run`/`--apply`-Befehl bauen (mit
  Audit-Gate davor), (3) Lessons/Nodes tatsaechlich backfuellen
  (Envelope-Format aus ADR 0115), (4) den Lesepfad auf `rich_content`
  umstellen, `body` vorerst als Fallback. Editor-/Publish-Cutover
  bleibt CMS-7d.3.

## Verifikation

- `php artisan rich-content:audit` gegen echten Bestand: 0
  blockierende Funde (vorher 9, alle in Lektion 1.0).
- PHP: 610/610 Tests gruen (2 neu: Validator- und Renderer-Test fuer
  `horizontal_rule`; der bisherige Skip-Test fuer Trennlinien wurde zu
  einem Konvertierungstest, der Feature-Test fuer den vormals
  blockierenden Fund zu einem "blockiert nicht mehr"-Test), PHPStan
  Level 7 und `pint --test` gruen.
- Frontend: `npm run test` 75/75 gruen (4 neu: Adapter-Rundtrip,
  Schema-Rundtrip durch das echte ProseMirror-Schema, Slash-Menue-
  Keyword-Filter, Befehlsausfuehrung gegen einen echten Editor).
  `npm run check` (Format + Lint) ohne Befunde, `npm run build`
  erfolgreich -- der Rich-Content-Editor ist weiterhin an keiner Stelle
  importiert (Bundle unveraendert isoliert, wie seit CMS-7b/7c).
