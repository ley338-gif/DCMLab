---
title: Abschlussprüfung — HL7 v2
intro: 10 Fragen aus Track 7. Ab 80 % ist der Track abgeschlossen. Beliebig oft wiederholbar.
---

### f01 — Welche zwei Systemklassen tauschen laut Lektion typischerweise HL7 v2 aus, bevor überhaupt ein DICOM-Objekt existiert?

1. RIS und PACS
2. KIS und RIS
3. Modalität und PACS
4. PACS und Viewer

**Erklärung:** Im vereinfachten Radiologie-Workflow sendet das KIS/EHR Patienten- und Auftragsdaten per HL7 v2 an das RIS — bevor überhaupt ein DICOM-Objekt entsteht.

### f02 — Ein Ticket beschreibt: DICOM-Verbindung technisch einwandfrei, Patient fehlt am Gerät. Welche Schlussfolgerung passt?

1. Protokoll-Troubleshooting reicht aus
2. Es handelt sich um Workflow-Troubleshooting, das vor der DICOM-Kette beginnt
3. Der Fehler liegt zwingend an der Bildkompression
4. C-ECHO muss wiederholt werden

**Erklärung:** Wenn der Auftrag nie vom KIS zum RIS gelangt ist, kann eine perfekte DICOM-Verbindung nichts finden — das ist Workflow-, nicht Protokoll-Troubleshooting.

### f03 — Welche Aussagen zur Beziehung zwischen Patient-ID, Auftrag und Bildstudie stimmen? *(Mehrfachauswahl)*

1. Patient ID, Order/Accession Number und Study Instance UID sind drei unterschiedliche Identitäten
2. Diese drei Identitäten sind zwar unterschiedlich, können aber miteinander verknüpft sein
3. Für frühes systemübergreifendes Troubleshooting reicht die Study Instance UID allein
4. Ab der Bildentstehung kommt zusätzlich die Study Instance UID als Identifikator hinzu

**Erklärung:** Vor der Bildentstehung existiert noch keine Study Instance UID — für frühes Troubleshooting brauchst du deshalb mindestens Patient ID und Accession Number, erst ab der Bildentstehung kommt die Study Instance UID dazu.

### f04 — Eine funktionierende DICOM-Worklist-Verbindung beweist, dass der erwartete Auftrag im Worklist-Broker vorhanden ist.

**Richtig / Falsch**

**Erklärung:** Falsch. Wer nur DICOM kennt, prüft AE Title, Port und Filter — das ist sinnvoll, wenn der Auftrag im Worklist-System vorhanden ist, beweist aber nicht, dass er dort überhaupt ankam.

### f05 — Welches Segment trägt Sending/Receiving Application, Message Type und Message Control ID?

1. PID
2. ORC
3. MSH
4. OBR

**Erklärung:** Der Message Header (MSH) trägt die Kommunikationsbeziehung: Sending/Receiving Application und Facility, Message Type und Message Control ID.

### f06 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. Segmente lassen sich an ihren ersten drei Zeichen erkennen (z. B. MSH, PID, OBR)
2. `^` trennt innerhalb eines Feldes Komponenten
3. Zwei identische numerische Patient-IDs meinen immer denselben Patienten
4. Die Assigning Authority kann entscheiden, ob zwei gleich aussehende IDs denselben Patienten meinen

**Erklärung:** Segmente sind an ihrem dreistelligen Präfix erkennbar, `^` trennt Komponenten innerhalb eines Feldes, und die Assigning Authority entscheidet über die tatsächliche Identität — zwei gleiche Ziffernfolgen aus unterschiedlichen Domänen können unterschiedliche Patienten meinen.

### f07 — Die Message Control ID identifiziert dieselbe medizinische Leistung wie die Order Number.

**Richtig / Falsch**

**Erklärung:** Falsch. Die Message Control ID identifiziert die Nachricht selbst, nicht die medizinische Leistung — dafür stehen Placer/Filler Order Number und Accession Number.

### f08 — Welches Segment beschreibt in einer HL7-v2-Nachricht die Patient Identification? *(Freitext)*

**Erklärung:** `PID`. Es trägt unter anderem Patient ID, Assigning Authority, Name und Geburtsdatum.

### f09 — Zwei Systeme zeigen für dieselbe Patient ID `4711` leicht unterschiedliche Namen, aber dasselbe Geburtsdatum. Was folgt daraus laut Lektion?

1. Es sind sicher zwei unterschiedliche Personen
2. Übereinstimmung beweist noch nicht dieselbe Identifier-Domäne, Abweichung beweist noch nicht zwei Personen
3. Die Patient ID ist ungültig
4. Beide Systeme haben denselben Fehler

**Erklärung:** Ähnlich aussehende Daten beweisen nicht dieselbe Identifier-Domäne, und ein abweichender Name beweist nicht automatisch zwei Personen — beide Abkürzungen sind riskant.

### f10 — Welche Aussagen zu ADT stimmen? *(Mehrfachauswahl)*

1. ADT-Nachrichten können Stammdaten- und Aufenthaltsänderungen transportieren
2. Ein Patient kann mehrere Fälle/Encounter mit jeweils eigenen Aufträgen haben
3. Ein doppelter Patient im PACS ist immer ein PACS-Softwarefehler
4. Ob eine Änderung automatisch übernommen wird, hängt vom vereinbarten Profil und der Konfiguration ab

**Erklärung:** ADT transportiert Stammdaten-/Aufenthaltsänderungen, ein Patient kann mehrere Fälle haben, und die Übernahme hängt vom Profil ab. Ein doppelter Patient im PACS ist dagegen eine typische PACS-*Meldung*, aber noch keine PACS-*Ursache*.

### f11 — Ein Patienten-Merge lässt sich sicher durchführen, indem man an einer Stelle die alte durch die neue Patient ID ersetzt.

**Richtig / Falsch**

**Erklärung:** Falsch. Ein Merge ist ein Workflow, kein String-Replace — Systeme müssen nachvollziehen, welche Identität führend ist und welche bereits erzeugten Untersuchungen betroffen sind.

### f12 — Wie lautet die gebräuchliche Abkürzung für die HL7-v2-Nachrichtenfamilie „Admit, Discharge, Transfer"? *(Freitext)*

**Erklärung:** `ADT`. Sie umfasst Ereignisse rund um Patientenstammdaten und Aufenthaltskontext.

### f13 — Ein Kollege sagt: „Die Verbindung steht, also muss der Patient da sein." Was ist an dieser Aussage am ehesten falsch?

1. Nichts, die Aussage stimmt uneingeschränkt
2. Eine funktionierende Verbindung beweist weder den vorgelagerten Auftrag noch eine korrekte Patientenidentität
3. DICOM-Verbindungen benötigen niemals HL7
4. Der Patient existiert unabhängig vom System

**Erklärung:** Eine technisch einwandfreie Verbindung beweist weder, dass der Auftrag je im Worklist-System ankam, noch dass Patientenstammdaten über alle Systeme hinweg konsistent sind.

### f14 — Welcher Identifikator eignet sich am wenigsten als technischer Primärschlüssel für Patientenabgleich?

1. Patient ID mit Assigning Authority
2. Patientenname
3. Message Control ID zur Nachrichtenkorrelation
4. Study Instance UID nach Bildentstehung

**Erklärung:** Namen ändern sich, sind nicht eindeutig und können unterschiedlich geschrieben werden — für technischen Patientenabgleich ist die Patient ID mit Assigning Authority belastbarer.

### f15 — Welche Aussagen gelten track-übergreifend für HL7 v2 im Radiologie-Kontext? *(Mehrfachauswahl)*

1. HL7 v2 kann fachlich vor jedem DICOM-Objekt existieren (z. B. als Auftrag)
2. Segmente wie MSH, PID, ORC, OBR sind an ihren ersten drei Zeichen erkennbar
3. Eine ADT-Änderung erreicht automatisch immer alle nachgelagerten Systeme
4. Patient-, Auftrags- und Bildidentität sind grundsätzlich getrennte Konzepte

**Erklärung:** HL7 v2 kann Aufträge fachlich vor der Bildentstehung transportieren, Segmente sind an ihrem Präfix erkennbar, und die drei Identitätsebenen sind grundsätzlich getrennt. Ob eine ADT-Änderung automatisch übernommen wird, hängt dagegen vom Profil und der Konfiguration des Zielsystems ab.

### f16 — Jede HL7-v2-Nachricht muss zwingend ein OBX-Segment enthalten.

**Richtig / Falsch**

**Erklärung:** Falsch. Welche Segmente vorkommen, hängt vom Nachrichtentyp ab — ein ORM-Auftrag zeigt typischerweise MSH/PID/PV1/ORC/OBR ohne OBX, das Ergebnissegment OBX gehört zur Ergebnisnachricht.
