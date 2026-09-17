---
title: Auftrag fehlt in der Worklist
scenario_title: Im KIS vorhanden, am CT unsichtbar
---

## Briefing

CT-5 zeigt einen erwarteten Patienten nicht an. Netzwerk und DICOM Worklist
funktionieren grundsätzlich. Deine Aufgabe ist, den Auftrag über die
Systemgrenzen zurückzuverfolgen und die **erste Stelle zu finden, an der er
fachlich verloren geht**.

Notiere währenddessen vier Werte:

<!-- kein-beispiel -->
```text
Patient ID:       4711
Order:            ORD93821
Message Control:  MSG4711
Procedure code:   CTTHX2
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
08:15:00 OUT MSG4711 → RIS
08:15:01 IN  ACK
MSA|AE|MSG4711|Unknown procedure code CTTHX2
ERR|||OBR^4^1|103^Table value not found
```

**Was du daran abliest:** Transport und Antwort funktionierten. Die
fachliche Verarbeitung scheiterte an `OBR-4`.

Nach Ergänzung des Mappings wird **genau diese fehlgeschlagene Nachricht**
erneut verarbeitet:

```text
08:24:12 REPROCESS MSG4711
08:24:12 IN ACK
MSA|AA|MSG4711
08:24:14 RIS ORDER CREATED ORD93821 / A93821
08:24:20 MWL ENTRY CREATED CT5-RAUM3
```

**Was du daran abliest:** Erst mit `AA`, angelegtem RIS-Auftrag und erzeugtem
MWL-Eintrag ist die Kette wieder vollständig.

### Was du mitnimmst

Eine leere Worklist ist nicht automatisch ein DICOM-Problem. Gute
PACS-Administration verfolgt den fachlichen Vorgang über **Patient ID,
Message Control ID, Order/Accession und später Study Instance UID**.
