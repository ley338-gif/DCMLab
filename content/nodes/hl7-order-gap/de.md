---
title: Auftrag fehlt in der Worklist
scenario_title: Im KIS vorhanden, am CT unsichtbar
---

## Briefing

CT-9 zeigt den Patienten 8842 nicht in der Worklist. Andere Patienten
erscheinen normal, eine allgemeine MWL-Abfrage an den Broker funktioniert.
Deine Aufgabe ist, den Auftrag über die Systemgrenzen zurückzuverfolgen und
die **erste Stelle zu finden, an der er fachlich verloren geht**.

Notiere währenddessen vier Werte:

<!-- kein-beispiel -->
```text
Patient ID:       8842
Order:            ORD-77410
Message Control:  MSG55902
Procedure code:   CTA_EXT
```

## Hints

### h1

Wenn eine allgemeine MWL-Abfrage funktioniert, ist „Broker kaputt" keine gute
erste Hypothese. Prüfe, ob genau dieser Auftrag überhaupt bis zum RIS
gekommen ist.

### h2

Trenne im Interface-Engine-Log zwei Dinge: den **Eingang** einer Nachricht
aus dem KIS und den **Outbound-Transfer** derselben Nachricht zum RIS. Beide
können unterschiedlich ausgehen.

### h3

Ein Outbound-Transfer mit `State: ERROR` und einem fehlenden Ziel-Mapping
für den Prozedurcode zeigt auf die Mapping-Tabelle zwischen Interface Engine
und RIS — nicht auf das Netzwerk und nicht auf die Modalität.

## Write-up

Die DICOM-Seite war in diesem Fall gesund. Der Fehler lag **vor** der
Worklist — und sogar vor dem RIS.

```text
09:12:00 IN  MSG55902 (KIS → Interface Engine)
09:12:01 OUT Transfer zu RIS
State: ERROR
Procedure code: CTA_EXT
Mapping: no target mapping
```

**Was du daran abliest:** Die Nachricht kam im Interface Engine an — der
Eingang ist bestätigt. Der ausgehende Transfer zum RIS scheiterte aber, weil
für den Prozedurcode `CTA_EXT` kein Ziel-Mapping hinterlegt ist. Das RIS hat
den Auftrag deshalb nie gesehen und konnte ihn folglich auch nicht an den
Broker weitergeben.

Nach Ergänzung des Mappings wird **derselbe Auftrag** gezielt erneut an das
RIS übertragen:

```text
09:24:12 REPROCESS MSG55902 → RIS
09:24:12 RIS ORDER CREATED ORD-77410 / A77410
09:24:20 MWL ENTRY CREATED CT-9
```

**Was du daran abliest:** Erst mit angelegtem RIS-Auftrag und erzeugtem
MWL-Eintrag ist die Kette wieder vollständig — die Untersuchung ist jetzt an
CT-9 auffindbar.

### Was du mitnimmst

Eine leere Worklist ist nicht automatisch ein DICOM-Problem und nicht
automatisch ein RIS-Problem. Gute PACS-/Interoperabilitäts-Administration
trennt **Eingang** und **Outbound-Transfer** derselben Nachricht im
Interface-Engine-Log und verfolgt den Auftrag über Patient ID, Message
Control ID, Order/Accession und Prozedurcode — nicht über eine ACK-Auswertung.
