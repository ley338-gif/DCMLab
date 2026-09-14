---
title: "Anonymisierung, Pseudonymisierung, Forschungsdaten"
teaser: "Ein geschwärzter Name reicht nicht — DICOM definiert selbst über 250 Attribute, die Patientenbezug tragen können, mit einem eigenen Regelwerk dafür, was mit jedem einzelnen passieren muss."
objectives:
  - "Kannst Anonymisierung von Pseudonymisierung unterscheiden (Rückführbarkeit als Kriterium) und DICOMs eigenen Mechanismus dafür benennen"
  - "Kannst mindestens drei der sechs realen DICOM-Aktionscodes (D/Z/X/K/C/U) benennen und ihre Bedeutung erklären"
  - "Kannst einordnen, warum ein Type-1-Attribut wie StudyInstanceUID nicht einfach gelöscht, sondern ersetzt werden muss"
---

## Ein Regelwerk, kein Bauchgefühl

„Anonymisiere die Daten" klingt nach einer einzigen Aktion — ist es
nicht. DICOM PS3.15 Annex E (Attribute Confidentiality Profiles) legt
für jedes einzelne Attribut eines Objekts einen von sechs realen,
standardisierten Aktionscodes fest:

| Code | Bedeutung |
|---|---|
| **D** | durch einen Platzhalterwert ersetzen (konsistent mit dem VR) |
| **Z** | auf Länge Null setzen oder durch einen Platzhalter ersetzen |
| **X** | Attribut vollständig entfernen |
| **K** | unverändert lassen |
| **C** | durch einen bedeutungsähnlichen, nicht-identifizierenden Wert ersetzen |
| **U** | durch eine neue, aber gültige UID ersetzen (innerhalb eines Datensatzes konsistent) |

Drei konkrete, real aus der aktuellen Fassung von PS3.15 Annex E
zitierte Beispiele:

- `PatientName` (0010,0010): **Z**
- `PatientBirthDate` (0010,0030): **Z**
- `StudyInstanceUID` (0020,000D): **U**

**Was du daran abliest:** `StudyInstanceUID` bekommt einen eigenen
Aktionscode (**U**), nicht **Z** oder **X** wie die Patientendaten. Das
ist kein Zufall — Lektion 3.1 hat real gezeigt, dass Orthanc ein
Objekt mit fehlendem `StudyInstanceUID` (Type 1) ablehnt. Ein
Anonymisierer darf dieses Attribut also nicht leeren oder entfernen,
sondern muss es durch eine andere, aber weiterhin gültige UID
ersetzen — genau das ist die Bedeutung von **U**.

## Live: Z und U tatsächlich anwenden

An einem realen, in dieser Spielwiese generierten CT-Objekt:

```
$ dcmdump instance-0001.dcm | grep -E "PatientName|PatientID|StudyInstanceUID"
(0010,0010) PN [MUSTER^ERIKA]                           #  12, 1 PatientName
(0010,0020) LO [4711]                                   #   4, 1 PatientID
(0020,000d) UI [1.2.826.0.1.3680043.8.498.11692141134122618849445519941065676215]                     #  64, 1 StudyInstanceUID
```
**Was du daran abliest:** Ausgangszustand — echter Name, echte
(fiktive) PatientID, echte StudyInstanceUID.

```
$ dcmodify -i "(0010,0010)=" -i "(0020,000d)=1.2.826.0.1.3680043.8.498.83471972995443322067650583942684984927" instance-0001.dcm
$ dcmdump instance-0001.dcm | grep -E "PatientName|PatientID|StudyInstanceUID"
(0010,0010) PN (no value available)                     #   0, 0 PatientName
(0010,0020) LO [4711]                                   #   4, 1 PatientID
(0020,000d) UI [1.2.826.0.1.3680043.8.498.83471972995443322067650583942684984927]                     #  64, 1 StudyInstanceUID
```
**Was du daran abliest:** `PatientName` ist jetzt Länge Null (**Z**),
`StudyInstanceUID` trägt eine neue, aber weiterhin syntaktisch gültige
UID (**U**) — `PatientID` bewusst unverändert gelassen (**K**), da sie
für die spätere Zuordnung noch gebraucht werden könnte (siehe
Pseudonymisierung unten).

```
$ storescu -v -aec ORTHANC 127.0.0.1 4242 instance-0001.dcm
I: Requesting Association
I: Association Accepted
I: Sending file: instance-0001.dcm
I: Sending Store Request: MsgID 1, (CT)
I: Received Store Response (Status: 0x0000 - Success)
I: Releasing Association
```
**Was du daran abliest:** Orthanc nimmt das de-identifizierte Objekt
real an — der Beweis, dass die **U**-Aktion für `StudyInstanceUID`
tatsächlich funktioniert hat: Ein leerer oder fehlender Wert wäre nach
Lektion 3.1s Fund abgelehnt worden, eine neue gültige UID nicht.

## Anonymisierung vs. Pseudonymisierung: Rückführbarkeit

Der Unterschied ist nicht die Stärke der Verschleierung, sondern eine
einzige Eigenschaft: **Kann jemand die Originaldaten wiederherstellen?**

- **Anonymisierung**: Nein, nicht einmal mit einem Schlüssel — die
  Zuordnung ist unwiderruflich zerstört.
- **Pseudonymisierung**: Ja, mit einem geschützt gehaltenen Schlüssel,
  den nur autorisierte Personen haben.

DICOM definiert für Pseudonymisierung einen eigenen, standardisierten
Mechanismus statt einer externen Excel-Tabelle: das
{{term:encrypted-attributes}}. Die Originalwerte werden verschlüsselt
in einer eigenen Sequenz (`0400,0550`) **im de-identifizierten Objekt
selbst** mitgeführt — wer den Schlüssel hat, kann sie extrahieren, wer
ihn nicht hat, sieht nur das anonymisierte Objekt. Das Bauen eines
solchen verschlüsselten Attributsatzes ist für den Rahmen dieser
Lektion unverhältnismäßig; das Prinzip zählt hier mehr als die
Implementierung.

## Weniger offensichtlicher Patientenbezug

Über `PatientName`/`PatientID`/`PatientBirthDate` hinaus nennt PS3.15
Annex E deutlich mehr Attribute mit Aktionscode — darunter
`ReferringPhysicianName`, `InstitutionName`, `OtherPatientIDs`, und
jede UID, die (wie oben gezeigt) Aktionscode **U** statt **K** trägt,
weil sie potenziell auf eine wiedererkennbare Fallnummer oder ein
bestimmtes Gerät zurückführbar ist. Zwei Kategorien bleiben mit den
Werkzeugen dieser Spielwiese nicht prüfbar:

- **Eingebrannte Texte in Pixeldaten** (z. B. ein sichtbarer
  Patientenname auf einem Secondary-Capture-Screenshot) — dafür fehlt
  ein Bildviewer im Werkzeugkasten (dieselbe Grenze wie in Lektion 3.2).
- **Private/herstellerspezifische Tags** — ob sie Patientenbezug
  tragen, hängt vom jeweiligen Hersteller ab und lässt sich nicht
  pauschal per DICOM-Standard beantworten.

## Warum das rechtlich mehr als eine Bequemlichkeit ist

Art. 89 DSGVO nennt Pseudonymisierung ausdrücklich als eine der
technischen Maßnahmen, mit denen Verarbeitung zu wissenschaftlichen
Forschungszwecken den Grundsatz der Datenminimierung einhalten kann —
und erlaubt unter bestimmten Voraussetzungen Ausnahmen von einzelnen
Betroffenenrechten (Art. 15, 16, 18, 21), wenn deren Ausübung den
Forschungszweck ernsthaft beeinträchtigen würde. Das ist der
rechtliche Grund, warum die Unterscheidung Anonymisierung/
Pseudonymisierung nicht nur technisches Vokabular ist: Nur bei echter,
nicht rückführbarer Anonymisierung entfällt der Personenbezug (und
damit die DSGVO) vollständig; Pseudonymisierung bleibt
personenbezogene Verarbeitung mit besonderen Garantien.

## Stolperfallen

- **Nur PatientName/PatientID für patientenbezogen halten.** UIDs,
  Institutions- und Arztangaben tragen ebenfalls Bezug.
- **Ein Type-1-Attribut wie StudyInstanceUID leeren wollen.** Das
  bricht die IOD-Konformität (Lektion 3.1) — dafür existiert die
  eigene **U**-Aktion.
- **Pseudonymisierung mit einer externen Mapping-Tabelle
  gleichsetzen.** DICOM hat mit dem Encrypted Attributes Data Set
  einen eigenen, im Objekt selbst mitgeführten Mechanismus dafür.

## Selbstcheck

1. Welchen der sechs DICOM-Aktionscodes (D/Z/X/K/C/U) bekommt
   `StudyInstanceUID` in PS3.15 Annex E, und warum nicht Z oder X?
2. Ein Kollege will einen Datensatz „richtig anonymisieren" und
   erwägt, `PatientID` zu behalten, um später Vorbefunde desselben
   Patienten zuordnen zu können. Ist das noch Anonymisierung oder
   Pseudonymisierung?
3. Nenne DICOMs eigenen, standardisierten Mechanismus für reversible
   Pseudonymisierung — wo werden die Originalwerte dabei gespeichert?
