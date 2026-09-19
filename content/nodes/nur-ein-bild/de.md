---
title: Nur ein Bild
scenario_title: Vollständige Study, aber nur ein verarbeitetes Bild
---

## Briefing

Ticket aus der Voxel-Analytics-Abteilung: Für Patient 73915 (Auftrag
A93044, CT Thorax) meldet das neue Analyse-/Exportsystem VOLUME-EXPORT
nach der Verarbeitung „1 Bild verarbeitet". Im PACS ist die Study als
vollständig markiert, und ein Radiologe kann im Viewer denselben
Datensatz vollständig als CT-Volumen öffnen und durchscrollen.

Drei Systeme, drei Zahlen, scheinbar ein Widerspruch:

<!-- kein-beispiel -->
```text
PACS:        Study vollständig, Instances: 1
Viewer:      vollständiger CT-Volumendatensatz
VOLUME-EXPORT: received instances: 1, processed images: 1
```

Deine Aufgabe: aus PACS-Bestand, Query-Antwort, Objektstruktur und
Downstream-Log rekonstruieren, warum „1 Instance" hier weder ein
Datenverlust noch ein Widerspruch ist — und wo die Verarbeitungskette
tatsächlich reißt.

Vorkenntnisse: Lektion 3.4. Rechne mit 20 Minuten.

## Hints

### h1

Prüfe, ob alle beteiligten Systeme mit „Bild", „Instance" und „Frame"
tatsächlich dasselbe zählen, bevor du einem davon einen Fehler
unterstellst.

### h2

Die Anzahl der Study-Related Instances beantwortet nicht automatisch
die Frage, wie viele Frames innerhalb einer einzelnen Instance stecken.

### h3

Prüfe `NumberOfFrames` des gespeicherten Objekts — und bei einem
Enhanced-Objekt zusätzlich, ob und wie die Functional-Groups-Struktur
tatsächlich befüllt ist.

## Write-up

### Symptom

VOLUME-EXPORT meldet nach der Verarbeitung nur „1 Bild", obwohl der
Viewer denselben Datensatz vollständig als CT-Volumen darstellt und das
PACS die Study als vollständig führt. Das äußere Bild — drei Systeme,
drei scheinbar widersprüchliche Zahlen — sagt für sich genommen noch
nicht, auf welcher Ebene die Kette tatsächlich reißt.

### Ebenen, die nicht gleichgesetzt werden dürfen

<!-- kein-beispiel -->
```text
Study
  │
  ▼
Series
  │
  ▼
SOP Instance
  │
  ▼
Frame
```

Diese vier Ebenen sind hierarchisch verschachtelt, aber ihre jeweilige
Anzahl ist unabhängig voneinander. Eine SOP Instance ist keine feste
Bildzahl — sie kann, wie in diesem Fall, mehrere hundert Frames tragen.

### Evidenz

- **C-STORE / PACS-Bestand**: Status Success, Expected SOP Instances 1,
  Stored SOP Instances 1 — die Study ist laut Archiv vollständig.
- **C-FIND (STUDY-Ebene)**: `NumberOfStudyRelatedInstances = 1`. Das
  zählt Instances (Objekte), keine Frames — kein Hinweis auf
  Datenverlust.
- **dcmdump des Objekts**: `SOPClassUID = EnhancedCTImageStorage`,
  `NumberOfFrames = 300`.

  **Was du daran abliest:** Eine einzige SOP Instance erklärt hier
  ausdrücklich 300 Frames — nicht 300 Instanzen und nicht ein
  einzelnes Bild.

- **Functional Groups desselben Objekts**: `SharedFunctionalGroupsSequence`
  mit einem Item (u. a. `PixelSpacing`, für alle Frames gleich),
  `PerFrameFunctionalGroupsSequence` mit 300 Items. Stichprobe:
  `ImagePositionPatient` unterscheidet sich zwischen Frame 1, Frame 150
  und Frame 300.

  **Was du daran abliest:** Die 300 Frames tragen nachweislich
  unterschiedlichen Bildinhalt (unterschiedliche Schichtposition) — kein
  Deklarationsfehler, der nur eine Zahl ohne realen Inhalt setzt.

- **VOLUME-EXPORT-Log**: „Import accepted. SOP instances received: 1.
  declared frames: 300. frames processed: 1." Adapter-Konfiguration:
  `frame_mode: single-image-per-instance`.

  **Was du daran abliest:** Die Instance wurde angenommen, die
  Multiframe-Struktur wurde sogar erkannt (`declared frames: 300`) —
  aber nur ein Frame wurde tatsächlich verarbeitet.

### Hypothesen

1. **Der Bildtransfer zum PACS ist unvollständig geblieben.** Widerlegt:
   C-STORE Success, Stored = Expected SOP Instances.
2. **Die Study im PACS ist unvollständig.** Widerlegt: Instanzzahl
   entspricht der Erwartung, und das eine gespeicherte Objekt enthält
   nachweislich alle 300 Frames vollständig.
3. **Die Transfer Syntax wird falsch dekodiert.** Widerlegt: Das Objekt
   lässt sich vollständig und fehlerfrei mit allen 300 deklarierten
   Frames auslesen.
4. **VOLUME-EXPORT unterstützt die SOP Class Enhanced CT Image Storage
   nicht.** Widerlegt: Das Log zeigt „Import accepted" — die Instance
   wurde angenommen und in die Verarbeitung übernommen.
5. **VOLUME-EXPORT setzt intern eine SOP Instance mit einem Bild gleich
   und verarbeitet deshalb nur den ersten Frame.** Bestätigt: Die
   Adapter-Konfiguration (`frame_mode: single-image-per-instance`)
   erklärt exakt das beobachtete Verhalten — Multiframe-Struktur erkannt,
   aber nicht iteriert.

### Ausschluss

PACS-Bestand und Query-Antwort sind durch positive Evidenz bestätigt
(Success-Status, übereinstimmende Instanzzahl) — Storage und Transfer
scheiden als Ursache aus. Das Objekt selbst dekodiert vollständig und
enthält nachweislich 300 unterschiedliche Frames — weder eine
Transfer-Syntax-Störung noch ein Deklarationsfehler ohne realen Inhalt
erklären das Symptom. VOLUME-EXPORT nimmt die Instance an und erkennt
die deklarierte Frame-Zahl — ein SOP-Class-Support-Problem scheidet
damit ebenfalls aus. Übrig bleibt die tatsächliche Frame-Verarbeitung
nach der Annahme.

### Erste fehlerhafte Stelle

Association, C-STORE und Speicherung sind vollständig erfolgreich, und
das Objekt ist als Enhanced-CT-Instance mit 300 real unterschiedlichen
Frames vollständig und normkonform. VOLUME-EXPORT nimmt diese Instance
korrekt an und liest sogar `declared frames: 300` korrekt aus dem
Objekt aus — die erste nachweislich fehlerhafte Stelle liegt danach: in
der anwendungsinternen Verarbeitungslogik, die pro akzeptierter SOP
Instance nur einen einzigen Frame durchläuft, statt über alle
deklarierten Frames zu iterieren.

### Betriebliche Maßnahme

Den Multiframe-/Enhanced-fähigen Importpfad von VOLUME-EXPORT
konfigurieren bzw. verwenden, sofern einer existiert, und dazu die
Produktdokumentation bzw. das aktuelle Conformance Statement von
VOLUME-EXPORT für Enhanced CT Image Storage prüfen. Falls kein
Multiframe-fähiger Pfad existiert und ein Konvertierungs- oder
Normalisierungsschritt nötig wird, vorher klären, was dabei mit SOP
Instance UIDs, Frame-Geometrie, Referenzen und Provenance geschieht —
ein Enhanced-Objekt pauschal in einzelne klassische Objekte zu zerlegen
ist keine automatisch sichere Standardlösung. Kein erneutes Senden, kein
Neustart, keine Study-Korrektur — keiner dieser Eingriffe betrifft die
tatsächlich gestörte Stelle.

### Was du mitnimmst

„Instance", „Frame" und „Bild" sind drei unterschiedliche Zähleinheiten.
`NumberOfStudyRelatedInstances` zählt Instances, nicht Frames — ein
Wert von 1 ist bei einem Multiframe-/Enhanced-Objekt weder ein Fehler
noch ein Beleg für Datenverlust. Und `NumberOfFrames > 1` beweist für
sich allein nicht Enhanced — das entscheiden SOP Class und
Functional-Groups-Struktur. Ein System kann eine SOP Class vollständig
akzeptieren und trotzdem falsch verarbeiten, wenn es intern noch von
„eine Instance = ein Bild" ausgeht.

### Verwandte Inhalte

Lektion 3.4 — Multiframe, Enhanced IODs
