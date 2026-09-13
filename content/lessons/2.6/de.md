---
title: "MPPS: Status-Rückmeldung der Modalität"
teaser: Die Meldung, die sagt, dass eine Untersuchung stattgefunden hat — unabhängig davon, ob auch ein Bild ankam.
objectives:
  - N-CREATE und N-SET als die beiden MPPS-Nachrichten unterscheiden
  - Die drei möglichen Zustände (IN PROGRESS, COMPLETED, DISCONTINUED) unterscheiden
  - Erklären, warum MPPS unabhängig vom Bildtransfer scheitern kann
---

## Ein Status, der beim Bild nicht mitgeliefert wird

Ein C-STORE überträgt Bilder — nichts weiter. Ob eine Untersuchung
*begonnen* oder *beendet* wurde, ob sie überhaupt stattgefunden hat,
steht in keinem Bildobjekt. Genau diese Lücke füllt {{term:mpps}}: eine
eigene Meldung der Modalität, unabhängig von jedem Bildtransfer.

## Zwei Nachrichten, eine Assoziation

MPPS ist ein eigener DIMSE-N-Dienst (N-CREATE/N-SET, nicht C-STORE
oder C-FIND) — die Modalität meldet den Beginn einer Untersuchung mit
`N-CREATE` und deren Ende mit `N-SET`, beide auf derselben Assoziation:

```
$ python3 - <<'PY'
from pynetdicom import AE
from pynetdicom.sop_class import ModalityPerformedProcedureStep
from pydicom.dataset import Dataset
from pydicom.uid import generate_uid

ae = AE(ae_title="CT01")
ae.add_requested_context(ModalityPerformedProcedureStep)
assoc = ae.associate("127.0.0.1", 11112, ae_title="MPPS-SCP")
sop_instance = generate_uid()

ds = Dataset()
ds.PerformedProcedureStepStatus = "IN PROGRESS"
ds.PerformedProcedureStepStartDate = "20260913"
ds.PerformedProcedureStepStartTime = "144729"
ds.PerformedStationAETitle = "CT01"
ds.PatientName = "MUSTER^ERIKA"
ds.PatientID = "4711"
ds.PerformedProcedureStepID = "SPS-0001"
ds.PerformedProcedureStepDescription = "CT Thorax nativ"
status, _ = assoc.send_n_create(ds, ModalityPerformedProcedureStep, sop_instance)
print("N-CREATE:", hex(status.Status))

ds2 = Dataset()
ds2.PerformedProcedureStepStatus = "COMPLETED"
ds2.PerformedProcedureStepEndDate = "20260913"
ds2.PerformedProcedureStepEndTime = "145112"
status2, _ = assoc.send_n_set(ds2, ModalityPerformedProcedureStep, sop_instance)
print("N-SET:", hex(status2.Status))
assoc.release()
PY
N-CREATE: 0x0
N-SET: 0x0
```
**Was du daran abliest:** Beide Nachrichten laufen über dieselbe
Assoziation, beide mit Status `0x0` (Success) beantwortet. `N-CREATE`
trägt bereits die vollständigen Stammdaten (Patient, geplante
Prozedur, Startzeit) — `N-SET` meldet nur noch, was sich geändert hat
(Status, Endzeit). Keine dieser beiden Nachrichten enthält ein
einziges Pixel.

## Drei mögliche Endzustände, nicht nur zwei

`COMPLETED` ist nicht der einzige gültige Endzustand — eine
abgebrochene Untersuchung meldet sich genauso gültig als
`DISCONTINUED`:

```
$ python3 - <<'PY'
from pynetdicom import AE
from pynetdicom.sop_class import ModalityPerformedProcedureStep
from pydicom.dataset import Dataset
from pydicom.uid import generate_uid

ae = AE(ae_title="CT01")
ae.add_requested_context(ModalityPerformedProcedureStep)
assoc = ae.associate("127.0.0.1", 11112, ae_title="MPPS-SCP")
sop_instance = generate_uid()

ds = Dataset()
ds.PerformedProcedureStepStatus = "IN PROGRESS"
ds.PerformedProcedureStepStartDate = "20260913"
ds.PerformedProcedureStepStartTime = "144729"
ds.PerformedStationAETitle = "CT01"
ds.PatientName = "MUSTER^ERIKA"
ds.PatientID = "4711"
ds.PerformedProcedureStepID = "SPS-0001"
ds.PerformedProcedureStepDescription = "CT Thorax nativ"
status, _ = assoc.send_n_create(ds, ModalityPerformedProcedureStep, sop_instance)
print("N-CREATE:", hex(status.Status))

ds2 = Dataset()
ds2.PerformedProcedureStepStatus = "DISCONTINUED"
ds2.PerformedProcedureStepEndDate = "20260913"
ds2.PerformedProcedureStepEndTime = "145112"
status2, _ = assoc.send_n_set(ds2, ModalityPerformedProcedureStep, sop_instance)
print("N-SET:", hex(status2.Status))
assoc.release()
PY
N-CREATE: 0x0
N-SET: 0x0
```
**Was du daran abliest:** Derselbe Mechanismus, derselbe
Erfolgsstatus `0x0` — der MPPS-SCP nimmt eine gemeldete Discontinuation
genauso an wie einen Abschluss. Der DIMSE-Status beschreibt nur, ob die
*Meldung* ankam, nicht ob die Untersuchung erfolgreich war. Eine
abgebrochene Untersuchung ist damit im Log genauso sichtbar wie eine
abgeschlossene — nur mit einem anderen Wert im selben Feld.

## Auf der Leitung: zwei vollständige Assoziationen

```
$ tshark -i lo -f "tcp port 11112" -Y dicom
4   0.000263   127.0.0.1 → 127.0.0.1   DICOM 359 A-ASSOCIATE request CT01 --> MPPS-SCP
6   0.003620   127.0.0.1 → 127.0.0.1   DICOM 260 A-ASSOCIATE accept  CT01 <-- MPPS-SCP
8   0.006280   127.0.0.1 → 127.0.0.1   DICOM 224 P-DATA, N-CREATE-RQ ID=1
10  0.046717   127.0.0.1 → 127.0.0.1   DICOM 212 P-DATA, N-CREATE-RQ-DATA
12  0.058207   127.0.0.1 → 127.0.0.1   DICOM 234 P-DATA, N-CREATE-RSP ID=1 (Success)
14  0.098701   127.0.0.1 → 127.0.0.1   DICOM 212 P-DATA, N-CREATE-RSP-DATA
16  0.101157   127.0.0.1 → 127.0.0.1   DICOM 224 P-DATA, N-SET-RQ ID=1
18  0.142713   127.0.0.1 → 127.0.0.1   DICOM 126 P-DATA, N-SET-RQ-DATA
20  0.144314   127.0.0.1 → 127.0.0.1   DICOM 234 P-DATA, N-SET-RSP ID=1 (Success)
22  0.186685   127.0.0.1 → 127.0.0.1   DICOM 126 P-DATA, N-SET-RSP-DATA
24  0.189216   127.0.0.1 → 127.0.0.1   DICOM 76  A-RELEASE request
25  0.190588   127.0.0.1 → 127.0.0.1   DICOM 76  A-RELEASE response
32  0.203601   127.0.0.1 → 127.0.0.1   DICOM 359 A-ASSOCIATE request CT01 --> MPPS-SCP
34  0.206554   127.0.0.1 → 127.0.0.1   DICOM 260 A-ASSOCIATE accept  CT01 <-- MPPS-SCP
36  0.209404   127.0.0.1 → 127.0.0.1   DICOM 224 P-DATA, N-CREATE-RQ ID=1
...
48  0.340797   127.0.0.1 → 127.0.0.1   DICOM 234 P-DATA, N-SET-RSP ID=1 (Success)
52  0.385107   127.0.0.1 → 127.0.0.1   DICOM 76  A-RELEASE request
53  0.386832   127.0.0.1 → 127.0.0.1   DICOM 76  A-RELEASE response
```
**Was du daran abliest:** Zwei vollständig getrennte Assoziationen,
eine je Untersuchung — die zweite (DISCONTINUED) sieht auf der Leitung
identisch aus wie die erste (COMPLETED), bis auf den Wert im
`N-SET-RQ-DATA`-Paket selbst. Von außen ist ohne den DIMSE-Inhalt
nicht zu erkennen, welcher der beiden Fälle vorliegt — das steht nur
im Nachrichteninhalt, nicht im Ablaufmuster.

## Was MPPS nicht ist

MPPS bestätigt nicht, dass ein Bild ankam — das ist Aufgabe von
C-STORE (Lektion 2.2) — und erst recht nicht, dass ein Archiv es
dauerhaft übernommen hat — das ist Storage Commitment (Lektion 2.7).
Alle drei sind unabhängige Meldewege, die parallel für dieselbe
Untersuchung stehen können.

## Wer MPPS in der Praxis entgegennimmt

Wie bei der Worklist (Lektion 2.5) ist es in echten Häusern meist ein
RIS oder ein eigener Broker, nicht das Archiv selbst, der MPPS
entgegennimmt — Orthanc (das Archiv dieser Spielwiese) unterstützt den
Dienst gar nicht, deshalb übernimmt hier ein eigener, echter
`pynetdicom`-SCP diese Rolle.

## Im Alltag

| Frage | Womit prüfen |
|---|---|
| Wurde die Untersuchung überhaupt als begonnen gemeldet? | `N-CREATE`-Log der Gegenstelle, nicht das PACS |
| Ist sie sauber abgeschlossen oder abgebrochen? | `PerformedProcedureStepStatus` im `N-SET` — `COMPLETED` oder `DISCONTINUED` |
| Kam trotzdem ein Bild an? | Getrennte Prüfung per C-STORE-Log — keine Abhängigkeit zu MPPS |

## Stolperfallen

- **MPPS im PACS-Log suchen.** Das Archiv ist oft gar nicht beteiligt
  — die Meldung geht direkt an RIS/Broker.
- **`DISCONTINUED` für einen Fehler halten.** Ein `N-SET` mit
  `DISCONTINUED` ist eine erfolgreich zugestellte Meldung
  (`0x0`), keine Ablehnung — der Inhalt der Meldung ist etwas anderes
  als ihr Zustellungsstatus.
- **MPPS-Erfolg mit Bildübertragung gleichsetzen.** Beide sind
  unabhängige Meldewege für dieselbe Untersuchung.

## Selbstcheck

1. Welche zwei Nachrichten bilden einen vollständigen MPPS-Zyklus, und
   worüber informiert jede davon?
2. Ein `N-SET` mit `PerformedProcedureStepStatus=DISCONTINUED` wird mit
   Status `0x0` beantwortet. Ist das ein Fehler?
3. Ein Bild ist im Archiv angekommen, aber keine MPPS-Meldung wurde je
   gesendet. Was folgt daraus über die Untersuchung — und was nicht?
