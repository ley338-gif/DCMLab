---
title: „Worklist ist leer"
teaser: Der meistunterschätzte Dienst — und der, bei dem Filter und Zeitfenster fast immer die Ursache sind.
objectives:
  - Eine leere Worklist von einer fehlgeschlagenen Worklist-Abfrage unterscheiden
  - Die Query-Keys nachvollziehen, die die Modalität tatsächlich schickt
  - Modality-Filter und Zeitfenster als häufigste Ursache prüfen
---

## Ein Ticket aus der Anmeldung

„Das Gerät zeigt keine Patienten." Die Modalität läuft, die Verbindung
steht — aber die Patientenliste bleibt leer. Bevor man irgendetwas
umkonfiguriert, lohnt sich die Frage: Ist das wirklich ein Fehler, oder
ist die Antwort nur leer, weil danach gefragt wurde?

## Wer die Worklist beantwortet

Ein Archiv wie Orthanc speichert Bilder — es weiß von sich aus nichts
über geplante Untersuchungen. Die Modality Worklist ist ein eigener
DICOM-Dienst (C-FIND mit dem Worklist-Informationsmodell), den in
echten Häusern fast immer ein RIS oder ein eigener Broker beantwortet,
nicht das Archiv selbst.

In der Spielwiese gibt es keine dritte Rolle: Orthanc übernimmt hier
zusätzlich den Worklist-Dienst (ein bereits vorbereiteter Auftrag liegt
bei jedem Sitzungsstart bereit — siehe „Liegt bereit" oben). Das
vereinfacht den Aufbau, verschiebt aber nichts an der eigentlichen
Lektion: Die Modalität stellt genau dieselbe Art Anfrage, ob die
Antwort nun vom Archiv, vom RIS oder von einem eigenen Broker kommt.

## Was die Modalität tatsächlich schickt

Eine Modality-Worklist-Anfrage ist ein C-FIND mit Filterwerten
(„Query-Keys") — meist mindestens die Modalität und der Scheduled
Station AE Title, oft zusätzlich ein Datum. Jeder dieser Filter kann
zu eng sein, ohne dass irgendetwas „kaputt" ist.

```
$ findscu -W -k "0040,0100[0].0008,0060=CT" -k PatientName -aec ORTHANC 127.0.0.1 4242
I: (0040,0100) SQ (Sequence with 1 item)                   # 1 ScheduledProcedureStepSequence
I:   (Sequence item #1)
I:     (0008,0060) CS [CT]                                     # 1 Modality
I:
I: Find SCP Response: 1 - 0xFF00 (Pending)
I: (0010,0010) PN [MUSTER^ERIKA]                           # 1 PatientName
I:
I: Find SCP Result: 0x0000 (Success)
```
**Was du daran abliest:** Der Filter auf `Modality=CT` (verschachtelt
in `ScheduledProcedureStepSequence`, nicht auf oberster Ebene — die
Modalität steckt in der geplanten Prozedur, nicht im Auftrag selbst)
liefert genau einen Treffer: den vorbereiteten Auftrag aus „Liegt
bereit". `PatientName` als leerer Rückgabeschlüssel bittet um den Wert,
ohne selbst zu filtern.

## Eine leere Antwort ist eine gültige Antwort

```
$ findscu -W -k "0040,0100[0].0008,0060=MR" -k PatientName -aec ORTHANC 127.0.0.1 4242
I: (0040,0100) SQ (Sequence with 1 item)                   # 1 ScheduledProcedureStepSequence
I:   (Sequence item #1)
I:     (0008,0060) CS [MR]                                     # 1 Modality
I:
I: Find SCP Result: 0x0000 (Success)
```
**Was du daran abliest:** Derselbe Aufruf, nur `Modality=MR` statt
`CT` — `Find SCP Result: 0x0000 (Success)` **ohne** eine einzige
`Find SCP Response`-Zeile davor. Kein Fehlercode, keine Ablehnung, kein
Hinweis, dass etwas fehlt — die Anfrage war einfach zu eng für das, was
wirklich geplant ist. Genau diese Ununterscheidbarkeit ist die Falle:
„nichts geplant" und „falsch gefragt" sehen am Terminal identisch aus.

## Auf der Leitung: eine Worklist-Anfrage ist ein C-FIND

```
$ tshark -i lo -f "tcp port 4242" -Y dicom
4   0.001530   127.0.0.1 → 127.0.0.1   DICOM 2731  A-ASSOCIATE request FINDSCU --> ORTHANC
6   0.001850   127.0.0.1 → 127.0.0.1   DICOM 792   A-ASSOCIATE accept  FINDSCU <-- ORTHANC
8   0.006496   127.0.0.1 → 127.0.0.1   DICOM 160   P-DATA, C-FIND-RQ ID=1
10  0.048269   127.0.0.1 → 127.0.0.1   DICOM 116   P-DATA, C-FIND-RQ-DATA
13  0.048859   127.0.0.1 → 127.0.0.1   DICOM 148   P-DATA, C-FIND-RSP ID=1
15  0.048877   127.0.0.1 → 127.0.0.1   DICOM 134   P-DATA, C-FIND-RSP-DATA
17  0.048901   127.0.0.1 → 127.0.0.1   DICOM 148   P-DATA, C-FIND-RSP ID=1 (Success)
19  0.051630   127.0.0.1 → 127.0.0.1   DICOM 76    A-RELEASE request
20  0.051696   127.0.0.1 → 127.0.0.1   DICOM 76    A-RELEASE response
```
**Was du daran abliest:** Auf der Leitung ist eine Worklist-Abfrage
nichts anderes als das C-FIND aus Lektion 1.0/2.3 — `C-FIND-RQ` mit den
Query-Keys als eigenes `C-FIND-RQ-DATA`-Paket, danach `C-FIND-RSP` mit
der Antwort. Wireshark unterscheidet hier nicht nach Worklist oder
Bildsuche — nur das Informationsmodell in der Anfrage selbst (das man
erst im DIMSE-Inhalt sieht, nicht im Paket-Überblick) macht den
Unterschied.

## Wo die Kette zum HIS/RIS reißt

Ein Auftrag entsteht in der Regel im Krankenhausinformationssystem
(HIS), wird ans RIS gemeldet und von dort an einen Worklist-Broker
weitergereicht, den die Modalität abfragt. Jede dieser Übergaben kann
scheitern, ohne dass die Worklist-Abfrage selbst einen Fehler zeigt:
ein Auftrag, der nie im RIS ankam, taucht in keiner noch so korrekten
Abfrage auf. Das unterscheidet diesen Fall von einem falschen Filter —
die Abfrage selbst ist korrekt, nur die Quelle hat nichts zu melden.

## Im Alltag

| Frage | Werkzeug |
|---|---|
| Ist wirklich nichts geplant, oder ist der Filter zu eng? | `findscu -W` erst ohne, dann mit den vermuteten Filtern |
| Welche Query-Keys schickt die Modalität wirklich? | `tshark -Y dicom`, DIMSE-Inhalt des `C-FIND-RQ` |
| Kam der Auftrag überhaupt im RIS/Broker an? | Nicht am Gerät zu sehen — Rückfrage bei RIS/HIS nötig |

## Stolperfallen

- **Die Worklist im Archiv suchen.** Ein Archiv wie Orthanc kann den
  Dienst technisch mitbeantworten (wie in dieser Spielwiese) oder auch
  nicht — in echten Häusern ist es fast nie dieselbe Instanz wie das
  RIS/der Broker. Der Fehler kann in einem ganz anderen System stecken.
- **Zeitfenster in der falschen Zeitzone.** Ein `ScheduledProcedureStepStartDate`-Filter
  auf „heute" ist nur dann korrekt, wenn Modalität und Broker dieselbe
  Zeitzone meinen — ein Auftrag knapp vor/nach Mitternacht ist ein
  klassischer Kandidat für „scheinbar nichts geplant".
- **Scheduled Station AE Title mit dem eigenen Calling AE Title
  verwechseln.** Wie ein Gerät sich selbst nennt und wie der Broker
  denselben Raum in seiner Terminplanung führt, sind zwei unabhängig
  gepflegte Werte (siehe Node „Worklist leer").

## Lab

Node „Worklist leer" übertägt genau diese Falle auf ein zweites Detail:
nicht die Modalität, sondern der **Scheduled Station AE Title**
unterscheidet sich vom Namen, den das Gerät für sich selbst verwendet.

## Selbstcheck

1. Eine `findscu -W`-Abfrage liefert `Find SCP Result: 0x0000 (Success)`
   ohne eine einzige Antwortzeile davor. Ist das ein Fehler?
2. Welche zwei Query-Keys grenzen eine Worklist-Abfrage in der Praxis
   am häufigsten zu stark ein?
3. Die Worklist-Abfrage selbst läuft fehlerfrei — trotzdem fehlt ein
   erwarteter Auftrag. Wo suchst du als Nächstes, wenn nicht am Gerät?
