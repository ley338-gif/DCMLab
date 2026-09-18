---
title: HL7 v2 — was läuft eigentlich neben DICOM?
teaser: Das Bild kommt per DICOM. Aber wer sagt dem RIS, dass es den Patienten, den Auftrag und später den Befund überhaupt gibt?
objectives:
  - DICOM und HL7 v2 nach ihrer Aufgabe im Radiologie-Workflow unterscheiden
  - Typische Informationsflüsse zwischen KIS, RIS, PACS und Modalität einordnen
  - Bei einem fehlenden Auftrag entscheiden, in welchem Systemteil die Suche beginnt
---

## „Das CT funktioniert — aber der Patient ist nicht da“

Das PACS antwortet auf C-ECHO. Die Worklist-Verbindung der Modalität ist erreichbar. Trotzdem fehlt der Patient.

Wer nur DICOM kennt, beginnt jetzt häufig am falschen Ende: AE Title prüfen, Port testen, Worklist-Filter verändern. Das ist sinnvoll, **wenn der Auftrag im Worklist-System vorhanden ist**. Wenn der Auftrag aber nie vom KIS zum RIS gelangt ist, kann eine perfekte DICOM-Verbindung nichts finden.

Genau an dieser Stelle beginnt für PACS-Administratoren HL7 v2.

## Zwei Standards, zwei Aufgaben

DICOM beschreibt vor allem Bildobjekte, Bildmetadaten und bildgebungsnahe Dienste. HL7 v2 transportiert in vielen Installationen klinische Ereignisse zwischen Informationssystemen: ein Patient wird aufgenommen, Stammdaten ändern sich, ein Auftrag wird angelegt, ein Befund wird freigegeben.

Ein vereinfachter Radiologie-Workflow sieht so aus:

```text
KIS / EHR
   │
   │  HL7 v2: Patient / Auftrag
   ▼
RIS ───────────────► Worklist-Broker
   │                      │
   │                      │ DICOM MWL C-FIND
   │                      ▼
   │                   Modalität
   │                      │
   │                      │ DICOM C-STORE
   │                      ▼
   └──────────────────► PACS
                          │
                          │ Viewer / Befundung
                          ▼
                       Radiologie
                          │
                          │ HL7 v2: Befund
                          ▼
                       KIS / EHR
```

**Was du daran abliest:** Derselbe Untersuchungsfall bewegt sich durch mehrere Protokolle. „PACS funktioniert“ sagt nichts darüber aus, ob der Auftrag vorher korrekt im RIS angekommen ist.

## Eine HL7-v2-Nachricht sieht anders aus

Ein stark gekürztes Beispiel:

```text
MSH|^~\&|KIS|HAUS|RIS|RAD|20260916081500||ORM^O01|MSG0004711|P|2.5
PID|1||4711^^^KLINIK^MR||MUSTER^ERIKA||19750314|F
PV1|1|O|RAD^ANMELDUNG
ORC|NW|ORD93821^KIS|||
OBR|1|ORD93821^KIS||CTTHORAX^CT Thorax nativ|||20260916083000
```

**Was du daran abliest:** Du siehst keine DICOM-Tags und keine Study Instance UID. Stattdessen stehen dort Segmente wie `PID`, `ORC` und `OBR`. Der Auftrag existiert zu diesem Zeitpunkt fachlich, bevor überhaupt ein Bild erzeugt wurde.

> Das konkrete Nachrichtenprofil ist installationsabhängig. Nicht jedes Haus verwendet dieselbe HL7-Version, denselben Message Type oder dieselben Feldbelegungen. Für den Betrieb zählt immer die vereinbarte Schnittstellenspezifikation.

## Vier Ebenen, die du auseinanderhalten musst

Ein häufiger Fehler ist, alle IDs wie „die Auftragsnummer“ zu behandeln. Tatsächlich baut sich ein Untersuchungsfall in Schichten auf:

<!-- kein-beispiel -->
```text
Patient
  └─ Fall / Encounter
       └─ Auftrag / Procedure
            └─ DICOM Study
```

| Ebene | Typischer Identifikator | Wofür er steht |
|---|---|---|
| Patient | Patient Identifier (z. B. Patient ID / MRN) | Identifiziert den Patienten innerhalb einer Identifier-Domäne |
| Fall / Encounter | Encounter-/Fallnummer | Ein eigener administrativer Kontext, etwa ein Aufenthalt oder Termin |
| Auftrag / Procedure | Placer/Filler Order Number, Accession Number | Welche Leistung wurde beauftragt — gehören zusammen, sind aber nicht pauschal dasselbe |
| Bildstudie | Study Instance UID | Welche konkrete DICOM-Studie wurde erzeugt |

Diese Werte können miteinander verknüpft sein, sind aber **eigene Identitäten**.

Im DICOM-Objekt findest du zum Beispiel:

```text
(0010,0020) LO [4711]                 # PatientID
(0008,0050) SH [A93821]               # AccessionNumber
(0020,000d) UI [1.2.276.0.7230010...] # StudyInstanceUID
```

**Was du daran abliest:** Der Patient Identifier identifiziert den Patienten innerhalb seiner Identifier-Domäne — nicht automatisch den Fall oder den Auftrag. Der Fall/Encounter ist ein eigener Kontext, unter dem mehrere Aufträge liegen können. Placer/Filler Order Number und Accession Number gehören in den Auftrags-/Untersuchungskontext, müssen aber nicht identisch sein. Erst die Study Instance UID identifiziert die tatsächlich erzeugte DICOM-Studie.

## Im Alltag heißt das

Wenn „der Patient nicht am CT auftaucht“, stellst du nicht sofort die Frage „Ist DICOM kaputt?“, sondern arbeitest die Kette rückwärts:

1. Liefert die Modalität überhaupt eine Worklist-Antwort?
2. Ist der erwartete Auftrag im Worklist-Broker/RIS vorhanden?
3. Hat das RIS den Auftrag aus dem KIS erhalten?
4. Wurde die eingehende Nachricht akzeptiert oder abgewiesen?
5. Stimmen Patient, Auftrag, Termin und Zielressource?

Das ist der Unterschied zwischen **Protokoll-Troubleshooting** und **Workflow-Troubleshooting**.

## Stolperfallen

- **„HL7 ist das Protokoll für Befunde.“** Zu eng. HL7 v2 kann unter anderem Patienten-, Bewegungs-, Auftrags- und Ergebnisinformationen transportieren.
- **„DICOM Worklist kommt aus dem PACS.“** Technisch kann ein System mehrere Rollen übernehmen, fachlich stammt die geplante Leistung aber typischerweise aus RIS/KIS-Workflows.
- **„Eine Accession Number ist eine Study UID.“** Nein. Die eine identifiziert einen fachlichen Auftrag/Untersuchungskontext, die andere eine DICOM-Studie.
- **„Wenn C-ECHO geht, muss der Patient auftauchen.“** C-ECHO prüft eine DICOM-Kommunikationsbeziehung, nicht die vorgelagerte Auftragskette.

## Selbstcheck

1. Ein CT kann den Worklist-SCP erreichen, der erwartete Auftrag fehlt aber. Welche Systemgrenze prüfst du als Nächstes?
2. Warum reicht die Study Instance UID nicht, um einen Auftrag vor der Bildentstehung zu verfolgen?
3. Welche Information würdest du in einem Ticket verlangen, damit du denselben Fall in KIS/RIS und PACS wiederfindest?

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Ein CT beantwortet C-ECHO erfolgreich, die MWL-Verbindung ist erreichbar. Trotzdem fehlt der erwartete Patient. Wo beginnt die Suche sinnvollerweise?**
1. Am DICOM-Netzwerk des CT
2. Beim Auftrag im KIS/RIS, der möglicherweise nie im Worklist-System ankam
3. An der Bildkompression der Modalität
4. An der Study Instance UID

**q2 — Welche Aussagen stimmen?** *(Mehrfachauswahl)*
1. HL7 v2 kann Patienten-, Bewegungs-, Auftrags- und Ergebnisinformationen transportieren
2. DICOM Worklist entsteht fachlich unabhängig von vorgelagerten KIS/RIS-Aufträgen
3. Eine funktionierende DICOM-Verbindung beweist, dass der Auftrag im Worklist-System vorhanden ist
4. Patient, Auftrag und Bildstudie tragen jeweils eigene, unterschiedliche Identifikatoren
