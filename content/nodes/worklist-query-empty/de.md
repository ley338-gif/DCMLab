---
title: Worklist leer
scenario_title: CT-5 zeigt keine Patienten, obwohl heute fünf CTs geplant sind
---

## Briefing

Am CT in Raum 3 zeigt die Patientenliste nichts an, obwohl laut
Anmeldung heute fünf CT-Untersuchungen geplant sind. Das Gerät selbst
läuft, die Verbindung zum RIS-Broker steht — die Abfrage liefert nur
keine Treffer.

Deine Umgebung:

| System | Adresse | Was du darfst |
|---|---|---|
| Deine Workstation | 10.70.0.50 | Shell mit findscu — plus Befehlsvorlagen |
| RIS-Broker | 10.70.0.10 | Modality-Worklist-C-FIND stellen; Konfiguration gesperrt (Herstellerzugang) |

Deine Aufgabe: Finde heraus, unter welchem Scheduled Station AE Title
der RIS-Broker den Raum tatsächlich führt, und gib ihn als Flag ein.

Vorkenntnisse: Lektion 1.5. Rechne mit 15 Minuten.

## Hints

### h1

Eine leere Antwort ist eine gültige Antwort, kein Fehler — `findscu`
meldet keinen Fehlercode. Prüf zuerst, mit welchem Filterwert für
"Scheduled Station AE Title" überhaupt gefragt wird.

### h2

`geraetekonfiguration.txt` zeigt den Calling AE Title des Geräts
(`CT-5`) — das ist der Name, unter dem sich das Gerät selbst meldet,
nicht zwingend der Name, unter dem der RIS-Broker den Raum in seiner
Terminplanung führt. Frag testweise ohne diesen Filter, um zu sehen,
was der Broker tatsächlich für heute geplant hat.

### h3

Trag `CT5-RAUM3` als Scheduled Station AE Title ein — unter diesem
Namen führt der RIS-Broker den Raum, nicht unter `CT-5`.

## Write-up

### Der Weg

1. Erst mit dem naheliegenden Filter fragen — dem eigenen AE Title des Geräts

```
$ findscu -W -k PatientName= -k ScheduledStationAETitle=CT-5 \
          -k ScheduledProcedureStepStartDate=20260913 \
          -aet CT-5 -aec RIS-BROKER 10.70.0.10 104
I: Number of Matches: 0
```
**Was du daran abliest:** Kein Fehler, keine Ablehnung — nur null
Treffer. Genau das ist die Falle: eine leere Worklist-Antwort sieht
identisch aus, egal ob wirklich nichts geplant ist oder ob nur der
Filter nicht passt.

2. Denselben Tag ohne den Stations-Filter abfragen

```
$ findscu -W -k PatientName= -k ScheduledStationAETitle= \
          -k ScheduledProcedureStepStartDate=20260913 \
          -aet CT-5 -aec RIS-BROKER 10.70.0.10 104
I: # Dicom-Data-Set
I: (0010,0020) LO [20261]  # xx, 1 PatientID
I: (0010,0010) PN [MEYER^HANS]  # xx, 1 PatientName
I: (0008,0050) SH [A20261]  # xx, 1 AccessionNumber
I: (0040,0001) AE [CT5-RAUM3]  # xx, 1 ScheduledStationAETitle
I: (0040,0002) DA [20260913]  # xx, 1 ScheduledProcedureStepStartDate
I: (0008,0060) CS [CT]  # xx, 1 Modality
I: # Dicom-Data-Set
I: (0010,0020) LO [20262]  # xx, 1 PatientID
I: (0010,0010) PN [SCHMIDT^ANNA]  # xx, 1 PatientName
I: (0008,0050) SH [A20262]  # xx, 1 AccessionNumber
I: (0040,0001) AE [CT5-RAUM3]  # xx, 1 ScheduledStationAETitle
I: (0040,0002) DA [20260913]  # xx, 1 ScheduledProcedureStepStartDate
I: (0008,0060) CS [CT]  # xx, 1 Modality
I: # Dicom-Data-Set
I: (0010,0020) LO [20263]  # xx, 1 PatientID
I: (0010,0010) PN [WEBER^JONAS]  # xx, 1 PatientName
I: (0008,0050) SH [A20263]  # xx, 1 AccessionNumber
I: (0040,0001) AE [CT5-RAUM3]  # xx, 1 ScheduledStationAETitle
I: (0040,0002) DA [20260913]  # xx, 1 ScheduledProcedureStepStartDate
I: (0008,0060) CS [CT]  # xx, 1 Modality
I: # Dicom-Data-Set
I: (0010,0020) LO [20264]  # xx, 1 PatientID
I: (0010,0010) PN [FISCHER^LENA]  # xx, 1 PatientName
I: (0008,0050) SH [A20264]  # xx, 1 AccessionNumber
I: (0040,0001) AE [CT5-RAUM3]  # xx, 1 ScheduledStationAETitle
I: (0040,0002) DA [20260913]  # xx, 1 ScheduledProcedureStepStartDate
I: (0008,0060) CS [CT]  # xx, 1 Modality
I: # Dicom-Data-Set
I: (0010,0020) LO [20265]  # xx, 1 PatientID
I: (0010,0010) PN [KOCH^PAUL]  # xx, 1 PatientName
I: (0008,0050) SH [A20265]  # xx, 1 AccessionNumber
I: (0040,0001) AE [CT5-RAUM3]  # xx, 1 ScheduledStationAETitle
I: (0040,0002) DA [20260913]  # xx, 1 ScheduledProcedureStepStartDate
I: (0008,0060) CS [CT]  # xx, 1 Modality
I: Number of Matches: 5
```
**Was du daran abliest:** Fünf geplante Verfahren für heute — sie waren
die ganze Zeit da. Jedes trägt `ScheduledStationAETitle` `CT5-RAUM3`,
nicht `CT-5`. Der RIS-Broker führt den Raum unter einem anderen Namen,
als am Gerät selbst als "Calling AE Title" eingetragen ist.

3. Mit dem korrigierten Filter erneut fragen

```
$ findscu -W -k PatientName= -k ScheduledStationAETitle=CT5-RAUM3 \
          -k ScheduledProcedureStepStartDate=20260913 \
          -aet CT-5 -aec RIS-BROKER 10.70.0.10 104
I: # Dicom-Data-Set
I: (0010,0020) LO [20261]  # xx, 1 PatientID
I: (0010,0010) PN [MEYER^HANS]  # xx, 1 PatientName
I: (0008,0050) SH [A20261]  # xx, 1 AccessionNumber
I: (0040,0001) AE [CT5-RAUM3]  # xx, 1 ScheduledStationAETitle
I: (0040,0002) DA [20260913]  # xx, 1 ScheduledProcedureStepStartDate
I: (0008,0060) CS [CT]  # xx, 1 Modality
I: # Dicom-Data-Set
I: (0010,0020) LO [20262]  # xx, 1 PatientID
I: (0010,0010) PN [SCHMIDT^ANNA]  # xx, 1 PatientName
I: (0008,0050) SH [A20262]  # xx, 1 AccessionNumber
I: (0040,0001) AE [CT5-RAUM3]  # xx, 1 ScheduledStationAETitle
I: (0040,0002) DA [20260913]  # xx, 1 ScheduledProcedureStepStartDate
I: (0008,0060) CS [CT]  # xx, 1 Modality
I: # Dicom-Data-Set
I: (0010,0020) LO [20263]  # xx, 1 PatientID
I: (0010,0010) PN [WEBER^JONAS]  # xx, 1 PatientName
I: (0008,0050) SH [A20263]  # xx, 1 AccessionNumber
I: (0040,0001) AE [CT5-RAUM3]  # xx, 1 ScheduledStationAETitle
I: (0040,0002) DA [20260913]  # xx, 1 ScheduledProcedureStepStartDate
I: (0008,0060) CS [CT]  # xx, 1 Modality
I: # Dicom-Data-Set
I: (0010,0020) LO [20264]  # xx, 1 PatientID
I: (0010,0010) PN [FISCHER^LENA]  # xx, 1 PatientName
I: (0008,0050) SH [A20264]  # xx, 1 AccessionNumber
I: (0040,0001) AE [CT5-RAUM3]  # xx, 1 ScheduledStationAETitle
I: (0040,0002) DA [20260913]  # xx, 1 ScheduledProcedureStepStartDate
I: (0008,0060) CS [CT]  # xx, 1 Modality
I: # Dicom-Data-Set
I: (0010,0020) LO [20265]  # xx, 1 PatientID
I: (0010,0010) PN [KOCH^PAUL]  # xx, 1 PatientName
I: (0008,0050) SH [A20265]  # xx, 1 AccessionNumber
I: (0040,0001) AE [CT5-RAUM3]  # xx, 1 ScheduledStationAETitle
I: (0040,0002) DA [20260913]  # xx, 1 ScheduledProcedureStepStartDate
I: (0008,0060) CS [CT]  # xx, 1 Modality
I: Number of Matches: 5
```
**Was du daran abliest:** Derselbe Tag, nur der Filterwert hat sich
geändert — jetzt liefert dieselbe Abfrage mit dem korrekten Filter exakt
dieselben fünf Verfahren, die schon in Schritt 2 sichtbar waren.

4. Flag: der tatsächliche Scheduled Station AE Title — `CT5-RAUM3`.

### Was du mitnimmst

Calling AE Title (wie sich ein Gerät selbst meldet) und Scheduled
Station AE Title (wie der RIS-Broker denselben Raum in seiner
Terminplanung führt) sind zwei unabhängig gepflegte Werte — nichts
zwingt sie, identisch zu sein. Eine leere Worklist-Antwort ist technisch
immer korrekt; sie sagt nichts darüber, ob wirklich nichts geplant ist.
Bevor man das RIS oder den Broker verdächtigt, lohnt sich der Blick auf
die tatsächlich gesendeten Query-Keys — oft reicht eine testweise
Abfrage ohne den vermuteten Filter, um zu sehen, was der Broker
wirklich kennt.

### Verwandte Inhalte

Lektion 1.5 — SCU und SCP, Called und Calling AE Title
Lektion 4.7 — „Worklist ist leer"
