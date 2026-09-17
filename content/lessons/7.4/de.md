---
title: Vom Auftrag zur Worklist — ORC/OBR → DICOM MWL
teaser: Die Worklist ist nicht der Anfang des Workflows. Sie ist eine Projektion dessen, was vorher als Auftrag durch KIS, RIS und Broker gelaufen ist.
objectives:
  - HL7-Auftragsdaten und DICOM-Worklist-Daten fachlich miteinander verbinden
  - Patient ID, Order Number und Accession Number entlang der Kette verfolgen
  - Einen Auftrag eingrenzen, der im KIS existiert, aber an der Modalität fehlt
---

## „Im KIS steht er drin“

Das ist der Satz, der eine gute Fehlersuche erst beginnen lässt.

Ein Auftrag kann im KIS sichtbar sein und trotzdem nie das RIS erreichen. Er kann im RIS stehen und trotzdem nicht für die richtige Modalität geplant sein. Er kann im Worklist-Broker vorhanden sein und wegen eines Filters nicht angezeigt werden.

## Der Weg des Auftrags

```text
KIS
 │  HL7 v2 Order
 ▼
Interface Engine
 │  Routing / Mapping / ACK
 ▼
RIS
 │  Terminierung / Ressource / Prozedur
 ▼
MWL Provider
 │  DICOM C-FIND
 ▼
Modalität
```

**Was du daran abliest:** Eine leere Modality Worklist hat mindestens vier mögliche Fehlerdomänen, bevor du überhaupt über Netzwerkprobleme nachdenkst.

## ORC und OBR

Stark gekürzt:

<!-- kein-beispiel -->
```text
MSH|^~\&|KIS|HAUS|RIS|RAD|20260916081500||ORM^O01|MSG4711|P|2.5
PID|1||4711^^^KLINIK^MR||MUSTER^ERIKA
ORC|NW|ORD93821^KIS|||||
OBR|1|ORD93821^KIS||CTTHORAX^CT Thorax nativ|||20260916103000
```

Typisch betrachtet:

- `ORC` beschreibt den Auftrag und seinen Status
- `OBR` beschreibt die angeforderte diagnostische Leistung
- lokale Profile ergänzen weitere Kennungen, Priorität, Zielbereich, Termin- oder Ressourceninformationen

**Was du daran abliest:** Ein Auftrag besitzt bereits vor der DICOM Study eine Identität. Diese Identität muss später mit der geplanten DICOM-Prozedur korrelierbar sein.

## Auf der DICOM-Seite

Eine MWL-Antwort kann unter anderem enthalten:

```text
(0010,0020) LO [4711]          PatientID
(0008,0050) SH [A93821]        AccessionNumber
(0032,1060) LO [CT Thorax]     RequestedProcedureDescription
(0040,0001) AE [CT5-RAUM3]     ScheduledStationAETitle
(0008,0060) CS [CT]            Modality
```

**Was du daran abliest:** Das Datenmodell ist ein anderes, aber dieselbe reale Untersuchung ist wiedererkennbar. Genau dafür sind stabile Auftrags- und Patientenidentifikatoren so wichtig.

## Die Brücke heißt Mapping

Der Worklist-Broker muss Daten aus dem Auftrags-/Terminmodell in DICOM MWL abbilden.

Beispiel:

```text
HL7 / RIS                         DICOM MWL
-------------------------------------------------------------
Patient Identifier        ───►    PatientID
Accession / Order context ───►    AccessionNumber
Procedure code            ───►    Requested Procedure
Room / device scheduling  ───►    Scheduled Station AE Title
Planned modality          ───►    Modality
Date/time                 ───►    Scheduled Procedure Step
```

**Was du daran abliest:** Ein Mappingfehler kann eine Worklist erzeugen, die technisch valide ist, aber fachlich die falsche Modalität, Station oder Untersuchung enthält.

## Ein sauberer Troubleshooting-Pfad

**Fall:** Patient 4711, Auftrag `ORD93821`, Accession `A93821`, heute 10:30 am CT-5.

Prüfreihenfolge:

1. KIS: Auftrag vorhanden und freigegeben?
2. Interface Engine: ausgehende Nachricht vorhanden?
3. ACK: akzeptiert oder Fehler?
4. RIS: Auftrag angekommen und korrekt terminiert?
5. Broker: Eintrag erzeugt?
6. DICOM MWL: ohne enge Filter abfragen
7. Query-Keys der Modalität mit Broker-Daten vergleichen

Diese Reihenfolge verhindert, dass du zehn Minuten am CT konfigurierst, obwohl die Nachricht seit einer Stunde in einer Error Queue liegt.

## Im Alltag heißt das

Nutze **eine Korrelationskarte** für jeden Fall:

<!-- kein-beispiel -->
```text
Patient ID:       4711 / KLINIK
Message Control:  MSG4711
Placer Order:     ORD93821
Accession:        A93821
Station:          CT5-RAUM3
Study UID:        noch nicht vorhanden
```

Nach der Untersuchung ergänzt du die Study Instance UID. Damit kannst du denselben Fall vom KIS bis zum PACS verfolgen.

## Stolperfallen

- **Order Number und Accession Number gleichsetzen.** Manche Installationen machen das absichtlich, andere nicht.
- **Nur nach Patient Name suchen.** Für technische Korrelation ungeeignet.
- **Worklist ohne Filter funktioniert → Modalität muss funktionieren.** Nein. Die Modalität kann einen engeren Filter senden.
- **Mapping als einmalige Einrichtung betrachten.** Neue Prozedurcodes, Standorte und Geräte machen Mappingpflege zu einem laufenden Betriebsprozess.

## Lab

„Auftrag fehlt in der Worklist“ führt dich durch genau diese Kette. Das Ziel ist nicht, einen DICOM-Port zu finden, sondern die **erste Systemgrenze zu identifizieren, an der der Auftrag verschwindet**.

## Selbstcheck

1. Warum kann ein Auftrag im RIS stehen und trotzdem nicht am CT erscheinen?
2. Welche drei Identifikatoren würdest du in allen beteiligten Logs suchen?
3. Was prüfst du zuerst: Worklist-Konfiguration am Gerät oder Error Queue der Schnittstelle — und unter welcher Voraussetzung?

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Ein Auftrag steht im KIS, aber die Modalität zeigt ihn nicht in der Worklist. Wie viele mögliche Fehlerdomänen liegen laut Lektion mindestens dazwischen, bevor Netzwerkprobleme überhaupt relevant werden?**
1. Eine
2. Zwei
3. Mindestens vier
4. Keine, Netzwerk ist immer zuerst zu prüfen

**q2 — Welche Aussagen stimmen?** *(Mehrfachauswahl)*
1. ORC beschreibt Auftrag und Status, OBR die angeforderte Leistung
2. Der Worklist-Broker muss Auftrags-/Termindaten in DICOM-MWL-Felder abbilden
3. Eine technisch valide Worklist ist automatisch auch fachlich korrekt
4. Ein Mappingfehler kann eine valide, aber fachlich falsche Worklist erzeugen
