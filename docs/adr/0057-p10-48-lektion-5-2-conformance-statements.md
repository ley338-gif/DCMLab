# 0057 — P10.48: Lektion 5.2 (Conformance Statements) — echte Quelle gefunden, Zitierfrage geklärt

Status: akzeptiert
Datum: 2026-09-14

## Kontext

Lektion 5.2 lag seit P10.46 (ADR 0055) als Gerüst vor, mit zwei offenen
Fragen: (1) Existiert ein echtes, frei zitierbares Conformance
Statement, das als Referenzbeispiel taugt? (2) Ist ein Hands-on-Anteil
überhaupt realistisch, oder bleibt die Lektion reine Textarbeit?

**Beide Fragen wurden vor dem Schreiben recherchiert, nicht
angenommen.**

## Recherche: Orthanc veröffentlicht ein echtes, frei zitierbares Conformance Statement

Per `WebSearch` gefunden und per `curl` direkt abgerufen: Orthanc — das
Archiv, das in dieser gesamten Plattform verwendet wird — pflegt sein
DICOM Conformance Statement offen im eigenen Quellcode-Repository
(`OrthancServer/Resources/DicomConformanceStatement.txt`,
`https://orthanc.uclouvain.be/hg/orthanc/raw-file/default/...`).
Anders als das Conformance Statement eines proprietären
Medizingeräte-Herstellers ist dieses Dokument Teil der
Open-Source-Projektdokumentation und ausdrücklich zur Weiterverwendung
durch Integratoren gedacht — dieselbe Kategorie wie DCMTKs eigene,
bereits an anderer Stelle dieses Projekts zitierte Tool-Dokumentation.
Die Zitierfrage aus dem Scaffold ist damit erledigt: kein
anonymisiertes oder nachgebautes Beispiel nötig, das echte Dokument
kann direkt (auszugsweise, mit expliziter Quellenangabe) zitiert
werden.

Vollständiger Abruf: 383 Zeilen, gegliedert exakt nach Echo/Store/
Find/Move/Get SCP, Echo/Store/Find/Move/Get SCU, Transfer Syntaxes,
Implementation Notes — reale Struktur, kein Nacherzähltes.

## Recherche: reale PS3.2-Pflichtstruktur

Per `WebSearch` (DICOM PS3.2, NEMA) bestätigt: Ein Conformance
Statement gliedert sich verbindlich in Einleitung, Implementation
Model, AE Specifications (Rolle, SOP-Klassen, Transfer-Syntaxen pro
SOP-Klasse), Networking, Media Interchange — diese Struktur wird in
der Lektion zitiert, nicht selbst erfunden.

## Entscheidung — echter Hands-on-Anteil: Behauptung gegen Verhalten prüfen

**`content/lessons/5.2/de.md`**: vollständig neu geschrieben.
- Reales Zitat aus Orthancs Conformance Statement (Store-SCP-Auszug,
  Transfer-Syntaxen-Auszug, die reale Aussage „Orthanc does not
  support extended negotiation").
- **Live-Gegenprobe in der echten Spielwiese:** Die im Dokument
  behauptete Präferenz für `LittleEndianExplicitTransferSyntax` wird
  direkt gegen das tatsächliche Verhandlungsergebnis von `storescu -d`
  geprüft. Dabei ein wichtiger, vor dem endgültigen Text real
  aufgedeckter Stolperstein: Der erste `storescu`-Aufruf ohne
  expliziten Pfad traf das `pynetdicom`-Skript gleichen Namens
  (`storescu.py v0.3.0`, PYNETDICOM_304) statt des echten DCMTK-Tools —
  derselbe, bereits in ADR 0025 dokumentierte Namenskonflikt. Erst
  `/usr/bin/storescu -d` liefert das reale DCMTK-Verhandlungsprotokoll,
  das dann tatsächlich verwendet wurde.
- Drei-Punkte-Kompatibilitätsprüfung (SOP-Klasse gemeinsam, Rolle
  passend, gemeinsame Transfer-Syntax), mit Verweis auf Lektion 1.7 für
  den dritten Punkt.
- Ehrlicher Abschnitt zu den Grenzen eines Conformance Statement
  (keine Aussage über Performance, Kantenfall-Verhalten, tatsächlich
  getestete Kombinationen) — direkt verknüpft mit Lektion 3.1s echtem
  Fund zu inkonsistenter Type-1-Durchsetzung als Beispiel für
  „das Dokument sagt nichts über tatsächliches Verhalten".

**`content/lessons/5.2/meta.yml`**: `sandbox.required` von `false` auf
`true` korrigiert (Dataset `ct-thorax-60`), `tools` von `[]` auf
`[storescu]` gesetzt, `glossary_terms: [conformance-statement]`,
`duration_minutes` von 10 auf 15, `status: draft` → `fertig`.

**`content/glossary/de.yml`**: neuer Begriff `conformance-statement`,
verknüpft mit den bereits bestehenden Begriffen `sop-class` und
`association`.

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Isolierter Compose-Stack (`docker compose -p p10-48`, eigene Ports
  15435/16382/18092).
- Test-Nutzer angelegt, echte Anmeldung, echte Sitzung über Lektion 2.1
  gestartet, realer Sitzungscontainer gefunden.
- `storescu -d` (ohne Pfad) real als `pynetdicom`-Skript identifiziert
  — Fehlversuch dokumentiert, nicht stillschweigend korrigiert.
- `/usr/bin/storescu -d -aec ORTHANC 127.0.0.1 4242
  instance-0005.dcm`: vollständiges reales Verhandlungsprotokoll
  erfasst (1452 Zeilen). Bestätigt: Context 41 (`CTImageStorage` /
  `LittleEndianExplicit`) wird akzeptiert, der C-STORE läuft real über
  Context 41, Antwortstatus `0x0000 (Success)` — exakt die im
  Conformance Statement behauptete Präferenz.
- `content:validate`: 0 Verstöße (41 Lektionen, 16 Nodes, 23 Werkzeuge,
  48 Glossarbegriffe).
- Lektion 5.2 im Browser gegen den echten Stack aufgerufen — rendert
  vollständig fehlerfrei.
- `datasets/build`/`services/sandbox`-Regressionssuites nicht erneut
  ausgeführt — diese Slice ändert keinen Python-/PHP-Code.
- Vor dem Entfernen des eigenen Sitzungscontainers real geprüft (wie
  seit ADR 0056 Standardpraxis), dass keine Verwechslung mit der
  Live-Umgebung des Nutzers vorliegt: nur die eigene Sitzungs-UUID war
  unter `dcmlab-sandbox-*` vorhanden, der Valkey-Quota-Schlüssel stammt
  weiterhin vom Vortag. Alle Docker-Ressourcen dieser Slice danach
  vollständig entfernt; `dcmlab/toolbox:latest`/`dcmlab/orthanc:latest`
  unangetastet.

## Nicht Teil dieser Slice

- Kein Node-Stub für Lektion 5.2 (`lab.node` bleibt `null`) —
  Conformance-Statement-Lektüre ist kein Engine-Feature-Kandidat.
- Keine Entscheidung über die offenen Hands-on-Fragen für 5.4/5.7
  (siehe ADR 0055) — wird erst bei diesen Lektionen geprüft.
