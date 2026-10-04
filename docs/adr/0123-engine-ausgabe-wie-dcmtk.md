# 0123 — Simulierte Engine: Ausgabe wie DCMTK, C-FIND-Antworten nach PS3.4

Status: vorgeschlagen (PR „Engine: DCMTK-treue Ausgabe …“), Merge erst zusammen mit den Studio-Texten der betroffenen Nodes
Datum: 2026-10-04

## Kontext

Die Fachprüfung der veröffentlichten Nodes am 04.10.2026 hat vier
Abweichungen der simulierten Engine (`services/engine`) vom Standard bzw.
von echtem DCMTK gefunden. Sie sind in den Write-ups sichtbar und lehren
dort etwas Falsches:

- **E1:** C-FIND-Antworten enthielten alle Felder eines Records, egal was
  angefragt war. PS3.4 C.4.1.1.3.2: „The C-FIND response shall not contain
  Attributes that were not in the request or specified in this section.“
  (zwillinge, patient-merge-discovery, worklist-query-empty)
- **E2:** Auf STUDY-Ebene passte ein Record ohne `study_uid`. Ein Treffer
  auf STUDY-Ebene ist aber immer eine Study, die StudyInstanceUID ist
  Unique Key (patient-merge-discovery, Schritt 1).
- **E3:** Die Modality Worklist war flach (ADR 0021). Station, Termin und
  Modalität stehen im Item der Scheduled Procedure Step Sequence
  (0040,0100) (PS3.4 Tabelle K.6-1). Ein flacher `-k
  ScheduledStationAETitle=…`-Befehl hilft gegen ein echtes Worklist-System
  nicht weiter.
- **E4:** Die Zeilen von `dcmdump` und `findscu` wichen vom echten Format ab:
  `I: `-Präfix bei `dcmdump`, große Hex-Ziffern, Platzhalterlänge `xx` und
  UIDs immer als Nummer. Echtes `dcmdump` (DCMTK 3.6.7 im Toolbox-Image der
  Spielwiese) zeigt bekannte UIDs als Namen; die Lektionen der Spielwiese
  (1.4, 1.7, 3.7) lehren `-Un` für die Nummer.

## Entscheidung

- Neues Modul `app/dump.py` für das DCMTK-Zeilenformat. Die Goldzeilen in
  `tests/test_dump_format.py` sind Zeichen für Zeichen aus der Toolbox
  abgeschrieben.
- `dcmdump` kennt `+P` (Tag oder Keyword, mehrfach), `-Un`/`+Un` und `+L`;
  unbekannte Optionen sind ein Fehler. Ohne `+P` kommen die Kopfzeilen der
  Datei mit.
- C-FIND gibt nur angefragte Keys aus, dazu QueryRetrieveLevel und auf
  STUDY-/SERIES-Ebene den Retrieve AE Title (0008,0054), den C.4.1.1.3.2
  verlangt. Ein echtes Orthanc liefert ihn ebenso; geprüft mit DCMTK-`findscu`
  aus der Toolbox gegen `dcmlab/orthanc`. Alles in Tag-Reihenfolge.
  Angefragte Keys ohne Wert kommen mit Länge null, nicht unterstützte fallen
  weg (C.2.2.1.3). Das gilt für Records, Worklist und den Bestand-Zweig.
- STUDY-Ebene: Records ohne `study_uid` passen nie.
- Worklist: SPS-Keys über Pfad (`ScheduledProcedureStepSequence[0].X`,
  `(0040,0100)[0].(gggg,eeee)`) oder leere Sequenz. Die Antwort ist
  verschachtelt. **Flache SPS-Keys nimmt die Engine weiter an**, damit die
  veröffentlichten Lösungswege gültig bleiben. Das ist eine bewusste Nachsicht
  der Simulation; strenger wäre, sie zu ignorieren wie ein echtes SCP.

Abgelöst: in ADR 0021 die flachen Worklist-Felder, in ADR 0023 „die
Simulation kennt `+P` nicht“.

## Folgen

- Alle Lösungswege der Nodes bleiben gültig, alle Flags erreichbar
  (`tests/test_real_content.py`, dazu ein Nachfahrlauf aller `$`-Befehle aus
  den Write-ups). Geändert haben sich:
  - **patient-merge-discovery:** Schritt 1 liefert jetzt 0 Treffer statt einer
    „leeren Registrierung“; die Namenssuche liefert einen Treffer statt zwei.
  - **teiltransfer:** `dcmdump` zeigt die SOP Class als Namen; die Nummer, das
    Flag, gibt `dcmdump -Un`.
  - **zwillinge, worklist-query-empty:** Die Antworten enthalten weniger Felder;
    die Worklist ist verschachtelt.
  - Alle dcmdump- und findscu-Ausgaben haben das echte Format.
- Die Write-ups veröffentlichter Nodes stehen in `rich_content`. Sie
  müssen in Studio nachgezogen werden, sonst zeigt das Terminal etwas anderes
  als der Text. Die neuen Ausgaben je Befehl liegen der Fachprüfung bei
  (`node-review/nodelauf-neu.md`).
- Die Entwurfs-Nodes ohne `rich_content` (geteilte-studie, serie-ohne-studie,
  zweiter-hop) sind im selben PR nachgezogen.

## Bewusst offen

- **E6:** Echtes DCMTK-`findscu` druckt je Antwort einen Kopf
  (`I: Find Response: 1 (Pending)`, `I: # Used TransferSyntax: …`) und keine
  Zeile `Number of Matches`. Die Simulation behält `I: Number of Matches: N`,
  weil viele Write-ups und Hints darauf aufbauen. Eine Umstellung wäre ein
  eigener Schritt, mit Studio-Texten.
