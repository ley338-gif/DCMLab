---
title: "Modality Worklist (MWL): das meistunterschätzte Thema"
teaser: Der Dienst, der jeder Untersuchung ihre Patientendaten mitgibt — bevor irgendein Bild existiert.
objectives:
  - Modality Worklist von einer Study/Series-Abfrage abgrenzen
  - Die typischen Query-Keys einer Modalität benennen
  - Eine leere Worklist-Antwort als gültiges Ergebnis einordnen
---

## Eine Modalität, die nichts abtippen will

Ein CT soll den Patientennamen, die Patient ID und die Untersuchungsart
nicht mehr von Hand erfassen — Tippfehler an dieser Stelle sind teuer.
Stattdessen fragt das Gerät selbst nach: „Was ist heute für mich
geplant?" Genau diese Frage beantwortet die {{term:worklist}}.

## Ein eigenes Informationsmodell, keine Study-Suche

Eine Study/Series-Abfrage (Lektion 2.3) braucht immer eine
`QueryRetrieveLevel` — PATIENT, STUDY, SERIES oder IMAGE. Die Worklist
kennt das nicht: Sie ist ein eigenes, flaches Informationsmodell
(PS3.4 Annex K), eine Liste geplanter *Scheduled Procedure Steps*, kein
Ausschnitt aus einer Hierarchie.

```
$ findscu -S -k QueryRetrieveLevel=STUDY -k PatientName -aec ORTHANC 127.0.0.1 4242
I: Requesting Association
I: Association Accepted
I: Sending Find Request: MsgID 1
I: 
I: # Request Identifier
I: (0008,0052) CS [STUDY]                                  # 1 QueryRetrieveLevel
I: (0010,0010) PN (no value available)                     # 0 PatientName
I: 
I: Find SCP Result: 0x0000 (Success)
I: Releasing Association
```
**Was du daran abliest:** Die Study-Abfrage trägt `QueryRetrieveLevel`
als eigenes Pflichtfeld. Eine Worklist-Anfrage (unten) hat dieses Feld
gar nicht — an seiner Stelle steht direkt eine Sequenz geplanter
Schritte. Zwei unterschiedliche Informationsmodelle, keine Variante
voneinander.

## Das flache Feld-Set einer Worklist-Antwort

```
$ findscu -W -k PatientName -k PatientID -k AccessionNumber \
          -k "0040,0100[0].0008,0060" -k "0040,0100[0].0040,0001" \
          -k "0040,0100[0].0040,0002" -k "0040,0100[0].0040,0003" \
          -aec ORTHANC 127.0.0.1 4242
I: Find SCP Response: 1 - 0xFF00 (Pending)
I: 
I: # Response Identifier
I: (0008,0005) CS [ISO_IR 100]                             # 1 SpecificCharacterSet
I: (0008,0050) SH [4711-0001]                              # 1 AccessionNumber
I: (0010,0010) PN [MUSTER^ERIKA]                           # 1 PatientName
I: (0010,0020) LO [4711]                                   # 1 PatientID
I: (0040,0100) SQ (Sequence with 1 item)                   # 1 ScheduledProcedureStepSequence
I:   (Sequence item #1)
I:     (0008,0060) CS [CT]                                     # 1 Modality
I:     (0040,0001) AE [CT01]                                   # 1 ScheduledStationAETitle
I:     (0040,0002) DA [20260913]                               # 1 ScheduledProcedureStepStartDate
I:     (0040,0003) TM [144729]                                 # 1 ScheduledProcedureStepStartTime
I: 
I: Find SCP Result: 0x0000 (Success)
I: Releasing Association
```
**Was du daran abliest:** Ohne einen einzigen Matching-Key — nur
Rückgabefelder — liefert die Abfrage den vollständigen geplanten
Auftrag: Patient, Accession Number und die Scheduled Procedure Step
Sequence mit Modalität, Zielstation, Datum und Uhrzeit. Genau diese
vier Felder (`Modality`, `ScheduledStationAETitle`,
`ScheduledProcedureStepStartDate/-Time`) sind die typischen
Matching-Keys, mit denen eine echte Modalität ihre Anfrage einschränkt.

## Eine eigene, saubere Presentation Context

```
$ findscu -d -W -k PatientName -aec ORTHANC 127.0.0.1 4242
D: Presentation Contexts:
D:   Context ID:        1 (Proposed)
D:     Abstract Syntax: =FINDModalityWorklistInformationModel
D:     Proposed Transfer Syntax(es):
D:       =LittleEndianExplicit
D:       =BigEndianExplicit
D:       =LittleEndianImplicit
...
D:   Context ID:        1 (Accepted)
D:     Abstract Syntax: =FINDModalityWorklistInformationModel
D:     Accepted Transfer Syntax: =LittleEndianExplicit
```
**Was du daran abliest:** `-W` schlägt genau **eine** Presentation
Context vor — die Modality Worklist Information Model FIND, real
`1.2.840.10008.5.1.4.31` — statt des ganzen Bündels aus
Patient-/Study-Root-FIND/MOVE/GET, das eine gewöhnliche Study-Abfrage
mitschleppt. Wer hier vergisst, `/usr/bin/findscu` statt des
`pynetdicom`-Skripts gleichen Namens aufzurufen (Lektion 4.2), sieht
stattdessen die volle Liste — beide Werkzeuge heißen `findscu`,
verhandeln aber unterschiedlich viele Presentation Contexts.

## Ein Matching-Key, der tatsächlich filtert

```
$ findscu -v -W -k "0040,0100[0].0008,0060=CT" -k PatientName -k AccessionNumber \
          -aec ORTHANC 127.0.0.1 4242
I: Requesting Association
I: Association Accepted (Max Send PDV: 16372)
I: Sending Find Request (MsgID 1)
I: ---------------------------
I: Find Response: 1 (Pending)
I: 
I: # Dicom-Data-Set
I: (0008,0005) CS [ISO_IR 100]                             #  10, 1 SpecificCharacterSet
I: (0008,0050) SH [4711-0001 ]                             #  10, 1 AccessionNumber
I: (0010,0010) PN [MUSTER^ERIKA]                           #  12, 1 PatientName
I: (0040,0100) SQ (Sequence with explicit length #=1)      #  18, 1 ScheduledProcedureStepSequence
I:   (fffe,e000) na (Item with explicit length #=1)          #  10, 1 Item
I:     (0008,0060) CS [CT]                                     #   2, 1 Modality
I: 
I: Received Final Find Response (Success)
I: Releasing Association
```
**Was du daran abliest:** Der Matching-Key `Modality=CT`, verschachtelt
in der Scheduled Procedure Step Sequence, liefert genau den einen für
diese Sitzung vorbereiteten Auftrag. Die Sequenz-Item-Darstellung
(`(fffe,e000) Item`) ist die native Anzeige von DCMTKs eigenem
`findscu` — anders formatiert als die kompaktere Darstellung
weiter oben, aber dieselben Werte.

## Eine leere Antwort bleibt eine gültige Antwort

```
$ findscu -v -W -k "0040,0100[0].0008,0060=MR" -k PatientName -aec ORTHANC 127.0.0.1 4242
I: Requesting Association
I: Association Accepted (Max Send PDV: 16372)
I: Sending Find Request (MsgID 1)
I: Received Final Find Response (Success)
I: Releasing Association
```
**Was du daran abliest:** Derselbe Aufbau, nur `Modality=MR` statt
`CT` — `Received Final Find Response (Success)` erscheint **ohne**
eine einzige `Find Response: n (Pending)`-Zeile davor. Kein
Fehlercode, keine Ablehnung: Der Dienst hat korrekt gearbeitet, es gab
nur nichts, was zum Filter passte.

## Wer die Worklist in der Praxis beantwortet

In dieser Spielwiese beantwortet Orthanc selbst die Worklist-Abfrage
(sein eingebautes Worklists-Plugin) — eine bewusste Vereinfachung des
Aufbaus. In echten Häusern übernimmt das fast immer ein RIS oder ein
eigener Broker, nicht das Archiv: Das Archiv speichert Bilder, ein
RIS/Broker verwaltet Termine. Beide Rollen können, müssen aber nicht
dieselbe Instanz sein.

## Im Alltag

| Frage | Werkzeug |
|---|---|
| Was ist für eine Station heute geplant? | `findscu -W` mit `ScheduledStationAETitle`- und Datumsfilter |
| Welche Felder liefert ein Auftrag überhaupt? | `findscu -W` ohne Matching-Keys, nur Rückgabefelder |
| Ist "nichts geplant" echt oder nur ein zu enger Filter? | Dieselbe Abfrage erst mit, dann ohne den vermuteten Filter |

## Stolperfallen

- **Worklist mit einer Study-Abfrage verwechseln.** Kein
  `QueryRetrieveLevel`, keine Study-/Series-Hierarchie — ein eigenes,
  flaches Modell.
- **Das falsche `findscu` aufrufen.** Ein pip-installiertes
  `pynetdicom`-Skript kann denselben Namen tragen wie das echte
  DCMTK-Werkzeug, verhandelt aber andere Presentation Contexts.
- **Eine leere Antwort für einen Fehler halten.** `Success` ohne
  Treffer ist ein gültiges, vollständiges Ergebnis — kein Hinweis auf
  eine Störung.

## Selbstcheck

1. Eine Worklist-Abfrage hat kein `QueryRetrieveLevel`-Feld. Womit
   grenzt sie stattdessen die geplanten Schritte ein?
2. Welche vier Felder schickt eine Modalität in der Praxis am
   häufigsten als Matching-Keys?
3. `findscu -W` liefert `Success` ohne eine einzige Antwortzeile davor.
   Ist das ein Fehler?
