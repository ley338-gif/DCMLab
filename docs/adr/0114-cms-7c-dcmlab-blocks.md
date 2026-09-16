# 0114 — DCMLab-Bloecke: callout, dicom_tag_table, self_check editorfaehig, Slash-Menue (CMS-7c)

## Status

Angenommen, 16.09.2026.

## Kontext

CMS-7b (ADR 0113) hat den gemeinsamen TipTap-3-Editor fuer den DCMLab-v1-
Basissatz geliefert (Paragraph, Heading, Listen, Blockquote, Code Block,
Marks) -- noch ohne DCMLab-eigene Bausteine. Betreiberauftrag CMS-7c: die
im urspruenglichen CMS-Auftrag genannten DCMLab-Bloecke (Info/Warning,
DICOM Tag Table, DICOM Dump, Terminal/Command, Glossary Reference,
Self-Check) editorfaehig machen -- ausdruecklich **nicht** als moeglichst
viele neue Node-Typen, sondern so sparsam wie das Schema es zulaesst.

## Entscheidung

**Kein neuer Blocktyp fuer Info/Warning, DICOM Dump, Terminal/Command.**
Stattdessen:

- `callout` (neu): ein gemeinsamer Block fuer Info- **und** Warnkasten,
  `attrs.kind` (`info`|`warning`) traegt die Bedeutung. Weitere Arten
  (`tip`, `note`, `success`, ...) brauchen kuenftig nur einen weiteren
  `kind`-Wert, kein neues Schema. Optionales `attrs.title`.
- `code_block.attrs.variant` bekommt einen sechsten Wert `dicom_dump` --
  teilt sich Copy-/Monospace-/Renderer-Mechanik bewusst mit `terminal`
  (Betreiber-Vorgabe), nur mit eigener CSS-Klasse fuer spaetere
  Sonderbehandlung (Dictionary-Lookup o. ae.).
- Terminal/Command brauchten keine Schema-Aenderung -- `console`/
  `terminal` existierten bereits seit ADR 0111. CMS-7c liefert dafuer nur
  Editor-UX (Slash-Kommandos "Terminal einfuegen"/"Befehl einfuegen").

**`dicom_tag_table` (neu, echter Mehrwert statt generischer Tabelle).**
Fachlich strukturierte Zeilen (`tag`/`keyword`/`vr`/`value`) statt eines
generischen `table`-Blocks -- eine Zeile ist bewusst ein reines
Datenobjekt **ohne eigenes `type`** (einziger Knoten im Schema ohne
`type`, siehe `RichContentValidator::validateDicomTagTable()`): sie ist
kein Rich-Content-Block, sondern ein geschlossener, homogener Datensatz.
Das schafft Raum fuer spaetere Dictionary-Validierung, Tooltip, VR-
Erklaerung, ohne das Schema nochmal aendern zu muessen. Generische
GFM-Tabellen (`table`, seit CMS-7a) bleiben ausserhalb des Editor-Scopes
-- kein Ziel dieser ADR.

**`self_check` und `glossary_term` werden editorfaehig.** Beide existierten
als DCMLab-Schema-Knoten bereits (ADR 0112/0111), waren in CMS-7b aber
bewusst noch nicht im Editor-Extension-Satz. CMS-7c ergaenzt echte TipTap-
Knoten dafuer (`selfCheck`, `glossaryTerm`) -- `self_check.attrs.summary`
bleibt ein reiner String-Wert (kein NodeView fuer Inline-Umbenennen, siehe
"bewusst nicht Teil"), `glossary_term` ist ein Atom (Begriff selbst nicht
frei editierbar, nur ersetzbar/loeschbar).

**`RichContentEditorAdapter` bleibt vollstaendig bidirektional.** Alle
vier neuen/erweiterten Typen (`callout`, `dicom_tag_table`, `self_check`,
`glossary_term`) uebersetzen in beide Richtungen -- bewiesen sowohl gegen
die eigenen Adapter-Funktionen als auch (wie schon in ADR 0113) gegen das
echte ProseMirror-Schema (`Node.fromJSON()`/`toJSON()`). Nur `table`
bleibt der verbleibende Fall, in dem `UnsupportedEditorNodeError`
tatsaechlich greift.

**Slash-Menue statt wachsender Toolbar** (`SlashCommand`, `@tiptap/
suggestion`, MIT): "/" oeffnet eine filterbare Liste aller Bausteine
(Text, Ueberschriften, Listen, Zitat, Code/Terminal/Befehl/DICOM Dump,
DICOM Tag Table, Info-/Warnkasten, Selbstcheck). Bewusst ein selbstgebautes,
schlichtes DOM-Popup statt einer Vue-NodeView/ReactRenderer-Integration --
fuer eine Befehlsliste mit Tastaturnavigation reicht das, und
`SuggestionProps.mount()` (TipTap 3) uebernimmt die Positionierung
(inkl. Scroll-/Resize-Nachfuehrung) bereits vollstaendig.

**"/glossary" ist eine echte Suche, kein Platzhalter-Eintrag.** Tippt man
"/glossary" (optional gefolgt von einem Suchbegriff, z. B. "/glossary
dicom"), schaltet die Item-Liste auf eine Filterung ueber die per Prop
uebergebenen Glossarbegriffe um (Treffer nach Begriff **oder** Slug) --
erfuellt die Betreiber-Vorgabe "glossary_term kann gesucht/eingefuegt
werden" als echten Such-Flow. `RichContentEditor.vue` bekommt dafuer eine
neue, optionale `glossaryTerms`-Prop (leer, wenn der Aufrufer noch keine
Liste hat).

**Bewusst nicht Teil dieser ADR:** interaktive NodeViews fuer
Zellen-/Attribut-Bearbeitung (`dicom_tag_table`-Zeilen per Formular
editieren, `self_check.summary` inline umbenennen, Zeilen hinzufuegen/
entfernen per UI-Button) -- das ist UX-Politur, kein Teil der CMS-7c-
Acceptance-Criteria (Rundtrip, Renderer, Adapter, "nichts verschwindet
still"). Der Legacy-Konverter erkennt `callout`/`dicom_tag_table`/
`dicom_dump` weiterhin nicht aus bestehendem Markdown (kein
zuverlaessiges Signal im echten Bestand, anders als der
`kein-beispiel`-Marker) -- alle drei sind reine Autoren-Konstrukte fuer
den Editor. Weiterhin keine `lessons.rich_content`/`nodes.rich_content`-
Spalte, keine Controller-/Publisher-Aenderung, keine Migration
bestehender Bodies (CMS-7d).

## Konsequenzen

- Das Rich-Content-Schema (Server) waechst additiv: `callout`,
  `dicom_tag_table`, `code_block.attrs.variant = dicom_dump`. Kein
  bestehendes CMS-7a/7b-Dokument wird dadurch ungueltig (per Test
  bewiesen).
- Der Editor-Extension-Satz waechst um vier TipTap-Knoten (`Callout`,
  `SelfCheck`, `DicomTagRow`, `DicomTagTable`) und einen Inline-Knoten
  (`GlossaryTerm`) sowie die `SlashCommand`-Extension -- weiterhin nicht
  von einer bestehenden Seite importiert (per `npm run build` bestaetigt).
- `docs/offene-fragen.md` unveraendert.
- Naechster Schritt ist CMS-7d: echte Persistenz
  (`lessons.rich_content`/`nodes.rich_content`), Legacy-Migration
  (inklusive der in ADR 0111/0112 offen gelassenen Faelle wie Bilder,
  eingebettetes rohes HTML ausser `kein-beispiel`) und der tatsaechliche
  Cutover -- an diesem Punkt wird der Editor erstmals produktiv.

## Verifikation

- PHP: alle 592 Tests (14 neu in `RichContentValidatorTest`/
  `RichContentRendererTest`, inklusive eines Regressionstests, dass
  bestehende CMS-7b-Dokumente weiterhin valide bleiben), PHPStan Level 7
  und `pint --test` sind gruen.
- Frontend: `npm run test` 71/71 gruen (26 neu: Adapter-Rundtrips fuer
  `callout`/`dicom_tag_table`/`dicom_dump`/`self_check`/`glossary_term`,
  Schema-Rundtrips gegen das echte ProseMirror-Schema, Slash-Menue-
  Filterlogik inklusive Glossar-Suche, Befehlsausfuehrung gegen einen
  echten Editor, Komponententest fuer die `glossaryTerms`-Weiterreichung).
  `npx vue-tsc --noEmit` und `npm run check` ohne neue Befunde. `npm run
  build` erfolgreich, keine der neuen Dateien im produktiven Bundle.
