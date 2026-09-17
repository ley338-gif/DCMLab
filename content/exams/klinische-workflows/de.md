---
title: Abschlussprüfung — Klinische Workflows
intro: 10 Fragen aus Track 7 (Klinische Workflows). Ab 80 % ist der Track abgeschlossen. Beliebig oft wiederholbar.
---

### f01 — Ein Auftrag ist im KIS sichtbar, aber die Modalität zeigt ihn nicht in der Worklist. Welche Kette prüfst du?

1. Nur den DICOM-Port der Modalität
2. KIS → Interface Engine → RIS → Broker → DICOM MWL, in dieser Reihenfolge
3. Nur den Worklist-Filter am Gerät
4. Nur die Study Instance UID

**Erklärung:** Eine leere Modality Worklist hat mindestens vier mögliche Fehlerdomänen vor dem Netzwerk — die Prüfreihenfolge folgt dem tatsächlichen Weg des Auftrags.

### f02 — Welche Aussagen zum Mapping zwischen HL7-Auftragsdaten und DICOM MWL stimmen? *(Mehrfachauswahl)*

1. Patient Identifier wird typischerweise auf PatientID gemappt
2. Accession-/Order-Kontext wird typischerweise auf AccessionNumber gemappt
3. Ein Mappingfehler kann eine technisch valide, aber fachlich falsche Worklist erzeugen
4. Mapping ist eine einmalige Einrichtung ohne laufenden Pflegebedarf

**Erklärung:** Der Worklist-Broker bildet Auftrags-/Termindaten auf DICOM-MWL-Felder ab — ein Mappingfehler kann dabei eine technisch valide, aber fachlich falsche Worklist erzeugen. Neue Prozedurcodes, Standorte und Geräte machen Mappingpflege zu einem laufenden Betriebsprozess, keiner einmaligen Einrichtung.

### f03 — Wenn die DICOM-MWL-Abfrage ohne Filter funktioniert, ist die Modalitätskonfiguration automatisch korrekt.

**Richtig / Falsch**

**Erklärung:** Falsch. Die Modalität kann einen engeren Query-Filter senden als die allgemeine Abfrage — „Worklist ohne Filter funktioniert" beweist nicht, dass die Modalität selbst korrekt konfiguriert ist.

### f04 — Welches Segment beschreibt die angeforderte diagnostische Leistung? *(Freitext)*

**Erklärung:** `OBR`. `ORC` beschreibt dagegen den Auftrag und seinen Status.

### f05 — Was bedeutet MLLP im Zusammenhang mit HL7 v2?

1. Ein fachlicher Prüfmechanismus für Patientendaten
2. Eine TCP-Rahmung, die Nachrichtenanfang/-ende markiert, ohne den Inhalt fachlich zu prüfen
3. Ein Ersatz für das ACK
4. Ein Verschlüsselungsverfahren

**Erklärung:** MLLP ist der Umschlag, nicht der Inhalt — es sorgt dafür, dass Sender und Empfänger erkennen, wo eine Nachricht beginnt und endet, prüft aber nicht, ob PID/ORC/OBR fachlich korrekt sind.

### f06 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. `MSA|AA|...` bedeutet fachlich akzeptiert
2. `MSA|AE|...` bedeutet einen Application Error trotz erfolgreichem Transport
3. Ein erhaltenes ACK beweist für sich allein die fachliche Verarbeitung
4. Die Message Control ID erlaubt, Original und ACK über Systeme hinweg zu korrelieren

**Erklärung:** `AA` bedeutet akzeptiert, `AE` einen Application Error trotz erfolgreichem Transport, und die Message Control ID verbindet Original und ACK über die beteiligten Systeme. Ein ACK allein beweist dagegen nur den Transport, nicht die fachliche Verarbeitung.

### f07 — Ein Timeout beim Warten auf ein ACK beweist, dass die Nachricht beim Empfänger nicht verarbeitet wurde.

**Richtig / Falsch**

**Erklärung:** Falsch. Der Timeout beweist nicht, dass die erste Nachricht nicht verarbeitet wurde — ein System ohne idempotente Retry-Behandlung kann denselben Vorgang sogar doppelt anlegen.

### f08 — Welcher MSA-1-Code signalisiert einen Application Error? *(Freitext)*

**Erklärung:** `AE`. `AA` signalisiert dagegen Acknowledge Accept — fachlich akzeptiert.

### f09 — Die Studie ist im PACS sichtbar und laut RIS final befundet, im KIS erscheint aber kein Befund. Wo liegt der Fehler mit hoher Wahrscheinlichkeit?

1. Im DICOM-Bildtransport
2. Im Ergebnisweg zwischen Befundsystem/RIS und KIS
3. In der Study Instance UID
4. Im PACS-Speicher

**Erklärung:** Bild- und Befundweg sind getrennte Pfade — ein sichtbares Bild beweist nichts über den Ergebnisweg, der hier den Fehler trägt.

### f10 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. Ein Befund kann vorläufig, korrigiert oder final sein
2. „Nachricht zugestellt" beweist automatisch den korrekten Befundstatus im Zielsystem
3. OBX trägt in einer Ergebnisnachricht die eigentlichen Befunddaten
4. Order- und Patient-Identifier sind für die Korrelation belastbarer als der Patientenname

**Erklärung:** Ein Befund durchläuft Zustände (vorläufig/korrigiert/final), OBX trägt die Ergebnisdaten, und Order-/Patient-Identifier sind belastbarer als der Name. „Zugestellt" und „richtiger Status im KIS" sind dagegen zwei unterschiedliche Prüfungen.

### f11 — Bildverfügbarkeit und Befundverfügbarkeit sind notwendigerweise derselbe Betriebszustand.

**Richtig / Falsch**

**Erklärung:** Falsch. Ein System kann vollständig funktionieren, während der andere Pfad gestört ist — beide sind getrennte Betriebszustände.

### f12 — Welches Segment enthält in einer ORU-Ergebnisnachricht den eigentlichen Befundtext?

1. ORC
2. OBR
3. OBX
4. PID

**Erklärung:** `OBX` trägt die eigentlichen Ergebnisdaten. `OBR` beschreibt dagegen nur die angeforderte Untersuchung, zu der das Ergebnis gehört.

### f13 — Ein Auftrag durchläuft KIS, Interface Engine, RIS, Broker und Modalität, später kommt ein Befund zurück. Wie viele grundsätzlich unabhängige Pfade beschreibt der Track dafür?

1. Einen gemeinsamen Pfad in beide Richtungen
2. Zwei unabhängige Pfade — Auftragsweg hin, Ergebnisweg zurück
3. Drei Pfade, je einen pro System
4. Keinen, da beides über DICOM läuft

**Erklärung:** Auftragsweg und Ergebnisweg sind zwei getrennte, unabhängig störbare Pfade — nicht derselbe Weg in Gegenrichtung.

### f14 — Sowohl beim Auftrags- als auch beim Befundweg gilt: Ein technischer Zustellerfolg beweist nicht automatisch was?

1. Dass überhaupt eine TCP-Verbindung bestand
2. Dass die fachliche Verarbeitung im Zielsystem korrekt erfolgte
3. Dass ein ACK zurückkam
4. Dass die Nachricht MLLP-gerahmt war

**Erklärung:** Transport und fachliche Verarbeitung sind auf beiden Wegen getrennte Ebenen — ein Zustellerfolg beweist nur den Transport.

### f15 — Welche Aussagen gelten für den Weg vom Auftrag zur Worklist? *(Mehrfachauswahl)*

1. Ein Mappingfehler kann sich als leere oder falsche Worklist zeigen
2. Ein ACK mit `AA` schließt einen späteren Mappingfehler beim Broker aus
3. Retries ohne Ursachenbehebung produzieren denselben Fehler erneut
4. Die Prüfreihenfolge KIS→Interface Engine→RIS→Broker→MWL verhindert unnötiges Konfigurieren am Gerät

**Erklärung:** Ein Mappingfehler kann die Worklist leeren oder verfälschen, blindes Wiederholen ohne Ursachenbehebung hilft nicht, und die systematische Prüfreihenfolge verhindert unnötiges Herumkonfigurieren am Gerät. Ein `AA` bestätigt nur die Annahme auf dieser Stufe — ein nachgelagerter Mappingfehler beim Broker ist damit nicht ausgeschlossen.

### f16 — Order Number/Accession Number und Study Instance UID sind über den gesamten Workflow hinweg identische, austauschbare Werte.

**Richtig / Falsch**

**Erklärung:** Falsch. Es sind eigenständige Identifikatoren auf unterschiedlichen Ebenen, die miteinander korrelierbar, aber nicht austauschbar sind.
