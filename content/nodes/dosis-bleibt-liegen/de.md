---
title: Die Dosis bleibt liegen
scenario_title: Dosisbericht im PACS vorhanden, aber nie beim Dose-System angekommen
---

## Briefing

Ticket: CT 73984 / Auftrag A94421 ist seit 45 Minuten im PACS vollständig
— Bilder und Dosisbericht erwartet. Im Dose-Management-System ist die
Untersuchung dagegen nicht vorhanden.

Der Dosisbericht (RDSR) wurde von der Modalität bereits korrekt erzeugt
und erfolgreich ins PACS übertragen. Die eigentliche Frage lautet
deshalb nicht „hat die Modalität gesendet?", sondern: Wo zwischen
PACS-Speicherung und Dose-System-Verarbeitung reißt die Kette?

<!-- kein-beispiel -->
```text
CT
 │
 ├── CT-Bilder ───────→ PACS
 │
 └── Dosisbericht ────→ PACS
                          │
                          │ Routingregel
                          ▼
                      Dose-System
```

Deine Aufgabe: Modalität → PACS und PACS → Dose-System als zwei
unabhängige DICOM-Strecken behandeln und für jede einzeln Beleg statt
Vermutung sammeln.

Vorkenntnisse: Lektion 3.8, idealerweise auch 3.7. Rechne mit 22
Minuten.

## Hints

### h1

Behandle Modalität → PACS und PACS → Dose-System als zwei unabhängige
Übertragungsstrecken. Ein Erfolg auf der einen sagt nichts über die
andere aus.

### h2

Entscheidend ist nicht nur, ob der Dosisbericht im PACS liegt, sondern
ob für genau dieses Objekt überhaupt ein Weiterleitungsauftrag zum
Dose-System angelegt wurde.

### h3

Wenn kein Auftrag existiert, prüfe die Selektionsbedingungen der
Routingregel gegen die tatsächlichen Attribute bzw. die SOP Class des
Dosisberichts.

## Write-up

### Symptom

Das PACS führt die Study vollständig — CT-Bilder und Dosisbericht sind
beide vorhanden. Das Dose-Management-System zeigt dieselbe Untersuchung
dagegen überhaupt nicht an.

### Kette

<!-- kein-beispiel -->
```text
Dosisbericht erzeugt?             ✓
Modalität → PACS?                 ✓
Dosisbericht im PACS gespeichert? ✓
Weiterleitungsauftrag angelegt?   ✗
PACS → Dose-System                nie gestartet
```

Vier unabhängig prüfbare Grenzen, keine automatisch aus der vorherigen
ableitbar.

### Evidenz

- **CT-Konsolen-Exportlog**: CT Image Storage 60/60 Success, X-Ray
  Radiation Dose SR 1/1 Success — die Modalität hat den Dosisbericht
  erzeugt und erfolgreich ans PACS übertragen.
- **PACS-Bestand**: CT Image Storage 60, X-Ray Radiation Dose SR
  Storage 1. dcmdump bestätigt `SOPClassUID = XRayRadiationDoseSRStorage`,
  `Modality = SR` — ein echter, gespeicherter strukturierter
  Dosisbericht, kein Screenshot.
- **Routing-Jobübersicht (Ziel DOSE-SCP)**: CT Image Storage 60 queued
  / 60 gesendet und von DOSE-SCP empfangen. X-Ray Radiation Dose SR — 0
  queued.

  **Was du daran abliest:** Kein Job heißt nicht „Transfer
  fehlgeschlagen" — es wurde für den Dosisbericht nie ein
  Übertragungsauftrag angelegt. Der Fehler muss vor der
  Association/vor C-STORE des zweiten Hops liegen.

- **C-ECHO PACS → DOSE-SCP**: Success — reine Erreichbarkeit, keine
  Aussage über unterstützte Storage-SOP-Classes.
- **Konformitätsprotokoll DOSE-SCP**: akzeptiert
  `XRayRadiationDoseSRStorage` als Storage-SOP-Class. Historisches Log:
  gestern erfolgreich einen Dosisbericht einer anderen Modalität
  empfangen und verarbeitet.
- **Routingregel CT-TO-DOSE** (Ziel DOSE-SCP), Bedingung `Modality ==
  CT`. Auswertung für Study A94421: CT Image Storage → `matched =
  true`; X-Ray Radiation Dose SR → `matched = false, reason =
  "condition Modality==CT not satisfied"`.

  **Was du daran abliest:** Für Structured-Report-Objekte wie diesen
  Dosisbericht schreibt das SR Document Series Module den Wert
  `Modality = SR` vor — unabhängig davon, dass er von einem CT-Gerät
  erzeugt wurde. Die Regel wurde offenbar mit der Absicht „alles von
  diesem CT" geschrieben, trifft aber nur Objekte, deren eigene
  `Modality` tatsächlich `CT` ist.

### Hypothesen

1. **Die Modalität hat keinen Dosisbericht erzeugt.** Widerlegt:
   Exportlog zeigt 1/1 Success für X-Ray Radiation Dose SR.
2. **Das PACS hat den Dosisbericht abgelehnt.** Widerlegt: PACS-Bestand
   und dcmdump bestätigen die gespeicherte Instanz.
3. **DOSE-SCP ist nicht erreichbar.** Widerlegt: C-ECHO Success.
4. **DOSE-SCP unterstützt die Dosisbericht-SOP-Class nicht.**
   Widerlegt: Konformitätsprotokoll plus historischer erfolgreicher
   Transfer.
5. **Der zweite C-STORE (PACS → DOSE-SCP) ist fehlgeschlagen.**
   Widerlegt: Die Jobübersicht zeigt 0 queued, nicht einen gescheiterten
   Versuch — es wurde nie ein Auftrag angelegt.
6. **Die PACS-Routingregel selektiert den Dosisbericht nicht, weil er
   nicht `Modality == CT` erfüllt.** Bestätigt: Auswertungslog zeigt
   `matched = false` exakt aus diesem Grund, während die CT-Bilder
   dieselbe Bedingung erfüllen und geroutet werden.

### Ausschluss

Modalität → PACS ist für beide Objekttypen durch positive Evidenz
bestätigt. PACS-Speicherung des Dosisberichts ist durch Bestand und
dcmdump bestätigt. DOSE-SCPs Erreichbarkeit und SOP-Class-Unterstützung
sind beide positiv belegt, nicht nur unterstellt. Übrig bleibt die
PACS-interne Objektauswahl für den zweiten Hop — und genau dort zeigt
das Auswertungslog die Ursache.

### Erste fehlerhafte Stelle

Nicht das PACS als Ganzes, nicht DICOM als Standard und nicht das
Dose-System. Die erste fehlerhafte Stelle ist die Objektauswahl des
PACS-Routers vor Erzeugung des Weiterleitungsauftrags: Die Routingregel
CT-TO-DOSE prüft `Modality == CT` und schließt damit jedes Objekt aus,
dessen eigene `Modality` nicht `CT` ist — einschließlich des
Dosisberichts, der als Structured-Report-Objekt `Modality = SR` trägt.

### Betriebliche Maßnahme

Die Routingregel so korrigieren, dass sie den gewünschten
Dosisbericht-Objekttyp explizit einschließt, statt sich allein auf
`Modality == CT` zu verlassen — beispielsweise durch eine zusätzliche
Bedingung auf die SOP Class `XRayRadiationDoseSRStorage`. Danach: einen
Test-Dosisbericht erneut auswerten lassen, prüfen, dass tatsächlich ein
Auftrag entsteht, die Association/den C-STORE des zweiten Hops
verifizieren und den Empfang im Dose-System bestätigen. Kein erneutes
Senden von der Modalität, kein Neustart des Dose-Systems — keiner
dieser Eingriffe betrifft die tatsächlich gestörte Auswahl-Logik.

### Was du mitnimmst

„Im PACS vorhanden" und „an ein Downstream-System weitergeleitet" sind
zwei getrennte Zustände — ein erfolgreicher erster Hop sagt nichts über
einen zweiten, unabhängigen Hop aus. Bei Multi-Hop-Workflows muss jede
Grenze einzeln belegt werden, und „kein Auftrag" ist etwas anderes als
„Auftrag fehlgeschlagen". Eine Routingregel, die nach `Modality`
filtert, trifft nur Objekte mit genau diesem Attributwert — ein
Dosisbericht trägt als Structured-Report-Objekt `Modality = SR`, auch
wenn er von einem CT-Gerät stammt.

### Verwandte Inhalte

Lektion 3.7 — Gespeichert, aber nicht darstellbar
Lektion 3.8 — RDSR lesen: Dosisdaten im PACS verstehen
