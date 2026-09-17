---
title: Auftrag fehlt in der Worklist
scenario_title: Im KIS vorhanden, am CT unsichtbar
---

## Briefing

CT-5 zeigt einen erwarteten Patienten nicht an. Netzwerk und DICOM Worklist
funktionieren grundsätzlich. Deine Aufgabe ist, den Auftrag über die
Systemgrenzen zurückzuverfolgen und die **erste Stelle zu finden, an der er
fachlich verloren geht** — und die Ursache selbst zu beheben.

Auf der Workstation stehen dir drei Werkzeuge zur Verfügung:

- `mllpq` — Status und Inhalt von HL7-v2-Nachrichten in der Interface-Engine-Queue
- `mllpsend <id>` — eine Nachricht erneut verarbeiten, nachdem du die Ursache behoben hast
- `findscu -W ...` — die DICOM Modality Worklist des Brokers abfragen

<!-- kein-beispiel -->
```text
Patient ID:       4711
Order:            ORD93821
Message Control:  MSG4711
```

## Hints

### h1

Wenn eine allgemeine MWL-Abfrage funktioniert, ist „Port kaputt“ keine gute
erste Hypothese. Prüfe, ob der Auftrag überhaupt bis zum RIS gekommen ist.

### h2

Ein ACK ist nicht automatisch Erfolg. Lies den `MSA`-Code und danach `ERR`.

### h3

`MSA|AE|MSG4711` plus `ERR ... OBR^4 ... Unknown procedure code CTTHX2`
zeigt auf das Mapping der angeforderten Prozedur.

## Write-up

Die DICOM-Seite war in diesem Fall gesund. Der Fehler lag **vor** der
Worklist: Das RIS hatte die HL7-Auftragsnachricht wegen eines unbekannten
Prozedurcodes abgewiesen.

```text
$ mllpq MSG4711
MSH|^~\&|KIS|HAUS|RIS|RAD|20260916081500||ORM^O01|MSG4711|P|2.5
PID|1||4711^^^KLINIK^MR||MUSTER^ERIKA||19750314|F
PV1|1|O|RAD^ANMELDUNG
ORC|NW|ORD93821^KIS|||
OBR|1|ORD93821^KIS||CTTHX2^CT Thorax mit KM|||20260916083000

MSA|AE|MSG4711|Unknown procedure code CTTHX2
ERR|||OBR^4^1|103^Table value not found
```

**Was du daran abliest:** Transport und Antwort funktionierten. Die
fachliche Verarbeitung scheiterte an `OBR-4` — dem angeforderten
Prozedurcode `CTTHX2`.

Nach Ergänzung des Mappings (Konfigurationsfeld `procedure_codes` auf der
Workstation) wird **genau diese fehlgeschlagene Nachricht** erneut
verarbeitet:

```text
$ mllpsend MSG4711
14:24:12  REPROCESS MSG4711
14:24:12  IN ACK
MSA|AA|MSG4711
14:24:12  RIS ORDER CREATED ORD93821
14:24:12  MWL ENTRY CREATED CT5-RAUM3
```

**Was du daran abliest:** Erst mit `AA`, angelegtem RIS-Auftrag und erzeugtem
MWL-Eintrag ist die Kette wieder vollständig.

Eine unabhängige DICOM-Abfrage bestätigt das:

```text
$ findscu -W -k PatientID=4711 -aet WORKSTATION -aec BROKER 10.60.0.10 104
I: # Dicom-Data-Set
I: (0010,0020) LO [4711]  # xx, 1 PatientID
I: (0010,0010) PN [MUSTER^ERIKA]  # xx, 1 PatientName
I: (0008,0050) SH [ORD93821]  # xx, 1 AccessionNumber
I: (0040,0001) AE [CT5-RAUM3]  # xx, 1 ScheduledStationAETitle
I: (0040,0002) DA [20260916]  # xx, 1 ScheduledProcedureStepStartDate
I: (0008,0060) CS [CT]  # xx, 1 Modality
I: Number of Matches: 1
```

**Was du daran abliest:** Der Auftrag ist jetzt auch aus DICOM-Sicht in der
Worklist sichtbar — nicht weil sich am Netzwerk etwas geändert hat, sondern
weil die HL7-Seite die Ursache behoben hat.

### Was du mitnimmst

Eine leere Worklist ist nicht automatisch ein DICOM-Problem. Gute
PACS-Administration verfolgt den fachlichen Vorgang über **Patient ID,
Message Control ID, Order/Accession und später Study Instance UID**.
