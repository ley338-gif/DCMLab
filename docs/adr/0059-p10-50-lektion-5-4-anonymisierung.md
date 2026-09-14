# 0059 — P10.50: Lektion 5.4 (Anonymisierung/Pseudonymisierung) — realer Hands-on-Baustein trägt

Status: akzeptiert
Datum: 2026-09-14

## Kontext

Lektion 5.4 lag seit P10.46 (ADR 0055) als Gerüst vor, mit einem als
zu prüfen markierten Hands-on-Kandidaten: `dcmodify`-basierte
Tag-Entfernung an einem real generierten Objekt, Vorher/Nachher per
`dcmdump`. Vor dem Schreiben real getestet, nicht angenommen.

## Recherche: reale DICOM-Aktionscodes statt einer erfundenen Liste

Per `curl` direkt die aktuelle Fassung von DICOM PS3.15 Annex E
(„Attribute Confidentiality Profiles", 2026c) abgerufen. Real
bestätigt: sechs standardisierte Aktionscodes (**D/Z/X/K/C/U**,
Tabelle E.1-1a), mit konkreten, real zitierten Beispielwerten aus
Tabelle E.1-1 für `PatientName` (0010,0010, Aktion **Z**),
`PatientBirthDate` (0010,0030, Aktion **Z**) und `StudyInstanceUID`
(0020,000D, Aktion **U**). Der **U**-Code (Ersatz durch eine neue,
aber gültige UID statt Löschen/Leeren) verbindet sich direkt mit
Lektion 3.1s real verifiziertem Fund, dass Orthanc ein Objekt mit
fehlendem `StudyInstanceUID` (Type 1) ablehnt — die Standard-Aktion
für genau dieses Attribut existiert also nicht zufällig getrennt von
**Z**/**X**.

Für die Pseudonymisierungsfrage zusätzlich real gefunden: DICOM
definiert einen eigenen, standardisierten Mechanismus dafür — das
**Encrypted Attributes Data Set** (`0400,0550` Modified Attributes
Sequence), das Originalwerte verschlüsselt im de-identifizierten
Objekt selbst mitführt, statt in einer externen Tabelle. Live gebaut
wurde dieser Mechanismus nicht (unverhältnismäßiger Kryptografie-Aufwand
für den Lernertrag dieser einen Lektion) — anders als der
Aktionscode-Teil, der vollständig live verifiziert wurde.

Für den rechtlichen Teil real per `WebSearch` bestätigt: Art. 89 DSGVO
nennt Pseudonymisierung ausdrücklich als zulässige technische Maßnahme
für Forschungszwecke und erlaubt unter Bedingungen Ausnahmen von den
Rechten aus Art. 15/16/18/21 — nicht aus dem Gedächtnis paraphrasiert.

## Entscheidung — echter Hands-on-Anteil: Z- und U-Aktion live angewendet

**`content/lessons/5.4/de.md`**: vollständig neu geschrieben.
- Reale Aktionscode-Tabelle (D/Z/X/K/C/U) mit den drei real zitierten
  PS3.15-Beispielen.
- **Live in der echten Spielwiese:** `dcmodify -i "(0010,0010)=" -i
  "(0020,000d)=<neue-UID>"` auf einem real generierten CT-Objekt
  angewendet — `PatientName` real auf Länge Null gesetzt (**Z**),
  `StudyInstanceUID` real durch eine neue, gültige UID ersetzt (**U**),
  `PatientID` bewusst unverändert gelassen (**K**). Anschließend real
  per `storescu` bestätigt: Orthanc nimmt das de-identifizierte Objekt
  an (`0x0000 Success`) — der empirische Beleg, dass die **U**-Aktion
  tatsächlich funktioniert, während ein leerer/fehlender
  `StudyInstanceUID` nach Lektion 3.1 abgelehnt worden wäre.
- Anonymisierung/Pseudonymisierung über Rückführbarkeit abgegrenzt,
  DICOMs Encrypted-Attributes-Mechanismus als reale, standardisierte
  Antwort auf die Pseudonymisierungsfrage benannt (konzeptionell, mit
  begründetem Verzicht auf einen Live-Aufbau).
- Zwei ehrlich als nicht prüfbar markierte Kategorien (eingebrannte
  Pixeldaten-Texte, private/herstellerspezifische Tags) — mit
  Cross-Referenz auf Lektion 3.2s Viewer-Grenze.
- Art. 89 DSGVO korrekt zitiert (Pseudonymisierung als genannte
  Maßnahme, Ausnahmen von Art. 15/16/18/21 unter Bedingungen).

**`content/lessons/5.4/meta.yml`**: `sandbox.required` von `false` auf
`true` korrigiert (Dataset `ct-thorax-60`), `tools` von `[]` auf
`[dcmdump, dcmodify, storescu]` gesetzt, `glossary_terms:
[de-identification, encrypted-attributes]`, `duration_minutes` von 10
auf 15, `status: draft` → `fertig`.

**`content/glossary/de.yml`**: zwei neue Begriffe
(`de-identification`, `encrypted-attributes`), gegenseitig verknüpft.

## Ein während der Verifikation aufgetretener, dokumentierter Fehlversuch

Ein geplanter dritter Test (Überleben eines frei erfundenen privaten
Tags über `dcmodify -i`) scheiterte zweimal mit „Corrupted data" —
vermutlich eine VR-Mehrdeutigkeit bei unbekannten privaten
Datenelementen, die `dcmodify` ohne explizite VR-Angabe nicht auflösen
kann. Nicht weiterverfolgt (unverhältnismäßiger Aufwand gegenüber dem
bereits starken Befund aus dem Z-/U-Test) — im Gegensatz zu Lektion
5.3, wo ein ähnlicher Fehlversuch dokumentiert wurde, kommt dieser Punkt
hier gar nicht als Behauptung in der Lektion vor (private Tags werden
nur als „nicht pauschal beantwortbar" erwähnt, ohne eine ungeprüfte
Aussage über ihr Verhalten zu treffen).

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Isolierter Compose-Stack (`docker compose -p p10-50`, eigene Ports
  15437/16384/18094).
- Test-Nutzer angelegt, echte Anmeldung, echte Sitzung über Lektion 2.1
  gestartet, realer Sitzungscontainer gefunden.
- `dcmodify -i "(0010,0010)=" -i "(0020,000d)=<neue-UID>"` real
  ausgeführt, `dcmdump`-Vorher/Nachher-Vergleich real erfasst,
  anschließender `storescu` real mit `0x0000 (Success)` bestätigt.
- `content:validate`: 0 Verstöße (41 Lektionen, 16 Nodes, 23 Werkzeuge,
  50 Glossarbegriffe).
- Lektion im Browser gegen den echten Stack aufgerufen — rendert
  vollständig fehlerfrei.
- Vor dem Entfernen des eigenen Sitzungscontainers geprüft (Standard-
  praxis seit ADR 0056/0057/0058): nur die eigene Sitzungs-UUID war
  unter `dcmlab-sandbox-*` vorhanden, Valkey-Quota-Schlüssel weiterhin
  vom Vortag. Alle Docker-Ressourcen dieser Slice danach vollständig
  entfernt; `dcmlab/toolbox:latest`/`dcmlab/orthanc:latest` unangetastet.
- `datasets/build`/`services/sandbox`-Regressionssuites nicht erneut
  ausgeführt — diese Slice ändert keinen Python-/PHP-Code.

## Nicht Teil dieser Slice

- Kein Node-Stub für Lektion 5.4 (`lab.node` bleibt `null`).
- Kein live gebautes Encrypted Attributes Data Set (siehe oben) —
  bleibt konzeptionell.
- Private-Tag-Überleben nicht verifiziert (siehe „Fehlversuch" oben) —
  keine entsprechende Behauptung in der Lektion.
- Keine Entscheidung über die offene Hands-on-Frage für 5.7 (siehe ADR
  0055) — wird erst bei dieser Lektion geprüft.
