---
title: Abschlussprüfung — Troubleshooting
intro: 16 Fragen aus Track 4. Ab 80 % ist der Track abgeschlossen. Beliebig oft wiederholbar.
---

### f01 — Ein Sendeauftrag scheitert mit `Called AE Title Not Recognized`. Was sagt dieser Ablehnungsgrund aus?

1. Der Absender kennt sein eigenes Ziel nicht
2. Die Gegenstelle (Called-Seite) erkennt den angesprochenen Namen nicht als ihren eigenen
3. Die Gegenstelle kennt den Absender nicht
4. Die Firewall blockiert den Port

**Erklärung:** `Called AE Title Not Recognized` betrifft immer das Ziel — die Gegenstelle wurde unter einem Namen angesprochen, den sie nicht als ihren eigenen erkennt. Welche Seite die Konfiguration ändern muss, folgt daraus noch nicht automatisch: Das hängt davon ab, welcher Name zwischen beiden Seiten eigentlich vereinbart war. `Calling AE Title Not Recognized` wäre der umgekehrte Fall: Der Absender ist beim Ziel nicht registriert.

### f02 — Ein Ablehnungsgrund nennt Presentation Context Result 4 (`transfer-syntaxes-not-supported`). Was ist betroffen?

1. Der Objekttyp (SOP Class) ist unbekannt
2. Die angebotene Kodierung wird nicht unterstützt, der Objekttyp ist bekannt
3. Der AE Title ist falsch
4. Die Association wurde komplett abgelehnt

**Erklärung:** Result 4 heißt: Das Archiv kennt den Objekttyp, aber keine der angebotenen Transfer Syntaxes. Result 3 wäre der Fall, dass der Objekttyp selbst unbekannt ist.

### f03 — `findscu` liefert für einen Patienten zwei getrennte Study-Einträge mit unterschiedlichen `StudyInstanceUID`-Werten. Was liegt vor?

1. Ein Split
2. Eine Dublette
3. Ein Coercion-Fall
4. Ein Timeout

**Erklärung:** Ein Split entsteht, wenn dieselbe Untersuchung zwei verschiedene Study Instance UIDs bekommt — das Archiv sieht sie als zwei eigenständige Studies. Eine Dublette dagegen verändert den Bestand nicht, weil dieselben Objekte unter derselben UID erneut eingespielt werden.

### f04 — Ein C-STORE ist erfolgreich, das RIS zeigt die Untersuchung trotzdem als „geplant". Welche zwei Dienste prüfst du als Nächstes?

1. MPPS und Storage Commitment
2. C-FIND und C-MOVE
3. AE Title und Transfer Syntax
4. DICOMweb und STOW-RS

**Erklärung:** MPPS meldet den Untersuchungsstatus, Storage Commitment die dauerhafte Übernahme — beide sind vom Bildtransfer unabhängige Statusdienste, die getrennt geprüft werden müssen.

### f05 — Welche vier Stellen können laut Lektion 4.4 hinter einem „Timeout" stecken? *(Mehrfachauswahl)*

1. Netzweg/Firewall
2. Falscher Port
3. DICOM-Aufbau (Association antwortet nicht)
4. Laufender Transfer bricht ab

**Erklärung:** „Timeout" ist eine Sammelmeldung für vier unterscheidbare Ursachenklassen, die jede ihren eigenen nächsten Diagnoseschritt braucht.

### f06 — Welche Werkzeuge ordnet Lektion 4.10 welcher Eingrenzungsstufe zu? *(Mehrfachauswahl)*

1. `ping` für den Netzweg
2. `echoscu -v` für die Association
3. `storescu -d -cx` für den Presentation Context
4. `tshark -Y dicom` prüft ausschließlich die Pixeldaten

**Erklärung:** `ping`, `echoscu -v` und `storescu -d -cx` sind je einer Eingrenzungsstufe zugeordnet. `tshark -Y dicom` zeigt den gesamten DICOM-Verkehr einer Verbindung, nicht nur Pixeldaten.

### f07 — Wenn `storescu` für jede gesendete Datei `Status: 0x0000 - Success` meldet, ist die gesamte Study garantiert vollständig im Archiv angekommen.

**Richtig / Falsch**

**Erklärung:** Falsch. Jeder `storescu`-Aufruf ist unabhängig — ein Erfolg sagt nichts über andere Objekte derselben Study, etwa wenn ein Aufruf schlicht ausgelassen wurde. `NumberOfStudyRelatedInstances` gegen die lokale Anzahl zu prüfen ist der zuverlässige Nachweis.

### f08 — Eine `findscu -W`-Abfrage, die mit `Success` endet, aber keine einzige Antwortzeile liefert, ist ein Fehler der Worklist-Abfrage selbst.

**Richtig / Falsch**

**Erklärung:** Falsch. Eine leere, erfolgreiche Antwort ist ein gültiges Ergebnis — es war einfach nichts geplant, das zum Filter passte, oder der Filter war zu eng.

### f09 — Welchen DIMSE-Statuscode liefert ein C-STORE, wenn das Archiv Patientendaten aktiv gegen seinen Bestand überschreibt (Coercion)? *(Freitext)*

**Erklärung:** `0xB000` — „Warning: Coercion of Data Elements". Das Objekt wurde angenommen, aber nicht unverändert übernommen.

### f10 — Mit welchem Präfix beginnt ein TLS-Handshake-Fehlercode bei den DCMTK-Werkzeugen, im Unterschied zu einem DICOM-Statuscode? *(Freitext)*

**Erklärung:** `000b:`. Ein TLS-Fehler liegt vor jeder DICOM-Nachricht — die Gegenstelle sieht nie eine DICOM-Ablehnung oder einen DIMSE-Status, weil der Handshake schon vorher scheitert.

### f11 — `echoscu` läuft grün, ein anschließender Sendeauftrag scheitert sofort mit einem Ablehnungsgrund. Auf welcher Ebene liegt das Problem?

1. TCP-Ebene (Host/Port)
2. Association-Ebene (AE Title)
3. Presentation Context (Objekttyp/Kodierung)
4. Das kann C-ECHO nicht ausschließen, es könnte jede der drei sein

**Erklärung:** C-ECHO prüft nur die TCP-Ebene und dass überhaupt eine Association mit der Verification SOP Class zustande kommt. Ein sofort scheiternder Sendeauftrag nach grünem C-ECHO zeigt auf die Presentation-Context-Aushandlung für den eigentlichen Bildtyp — eine eigene, unabhängige Aushandlung.

### f12 — Ein Sendeauftrag über 60 Dateien meldet für jede einzelne Datei Erfolg, dauert aber ungewöhnlich lange, und eine Datei fehlt am Ende im Archiv. Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. Ein einzelner Erfolgsstatus je Datei beweist nichts über die Vollständigkeit der ganzen Study
2. Eine lange Dauer allein beweist einen Timeout-Fehler
3. `NumberOfStudyRelatedInstances` gegen die lokale Dateianzahl zu prüfen ist der zuverlässige Nachweis
4. Ein fehlendes Objekt kann an einer nicht akzeptierten SOP Class liegen, ohne dass die Association je scheiterte

**Erklärung:** Einzelne Erfolgsmeldungen beweisen nie die Vollständigkeit der Study, der UID-basierte Zählabgleich ist der verlässliche Nachweis, und eine stille Ablehnung einzelner Objekte (SOP Class, Größenlimit) tritt unabhängig von der Association auf. Eine lange Laufzeit allein ist dagegen kein Timeout-Beweis.

### f13 — Welcher DICOM-Wert entscheidet bei zwei äußerlich identischen Studies, ob es sich um einen echten Split handelt (nicht der angezeigte Name)? *(Freitext)*

**Erklärung:** `StudyInstanceUID`. Namensgleichheit beweist nichts — erst zwei unterschiedliche Study Instance UIDs belegen, dass das Archiv zwei eigenständige Untersuchungen sieht.

### f14 — Der Warnstatus `0xB000` aus Lektion 2.2 (C-STORE) und die in Lektion 4.6 beschriebene Coercion hängen zusammen — `0xB000` ist genau der Status, den ein C-STORE bei einer Coercion-Umschreibung durch das Archiv zurückgibt.

**Richtig / Falsch**

**Erklärung:** Richtig. `0xB000` ("Warning: Coercion of Data Elements") ist der konkrete DIMSE-Status, den ein C-STORE liefert, wenn das Archiv ankommende Patientendaten aktiv gegen seinen eigenen Bestand überschreibt.

### f15 — Eine Worklist-Abfrage läuft technisch fehlerfrei, liefert aber keinen erwarteten Auftrag. Wo suchst du als Nächstes, laut Lektion 4.7?

1. Beim RIS/Broker, nicht am Gerät oder Archiv selbst
2. In der Firewall-Konfiguration
3. In der Transfer-Syntax-Aushandlung
4. Im Storage-Commitment-Status

**Erklärung:** Die Worklist-Abfrage selbst kann korrekt sein, während der Auftrag nie im RIS/Broker ankam — eine dieser Übergaben liegt außerhalb dessen, was am Gerät oder am Archiv sichtbar ist.

### f16 — Welche Aussagen zur Statuskette aus MPPS und Storage Commitment stimmen? *(Mehrfachauswahl)*

1. Beide sind vom eigentlichen Bildtransfer unabhängig
2. MPPS bestätigt, dass Bilddaten angekommen sind
3. Eine Storage-Commitment-Ablehnung für ein tatsächlich gespeichertes Objekt deutet auf ein Konfigurationsproblem des Rückrufs hin, nicht auf einen fehlenden Bildtransfer
4. Beide Dienste laufen immer über dieselbe Verbindung wie der Bildtransfer

**Erklärung:** MPPS und Storage Commitment sind eigene, vom Bildtransfer unabhängige Meldewege — eine Commitment-Ablehnung für ein real gespeichertes Objekt zeigt eher ein Problem im Rückruf-Mechanismus als einen gescheiterten Transfer. MPPS bestätigt dabei nie den Bildinhalt, und beide Dienste laufen über eigene Verbindungen.

### f17 — Ein TLS-Handshake scheitert. Welche DICOM-spezifische Information sieht die Gegenstelle davon?

1. Keine — der Handshake liegt vor jeder DICOM-Nachricht, auch vor einer Ablehnung
2. Eine reguläre Association-Ablehnung mit Grund
3. Ein Presentation-Context-Result-Code
4. Einen DIMSE-Status wie bei C-STORE

**Erklärung:** TLS setzt sich zwischen TCP und die DICOM-Aushandlung. Scheitert der Handshake, sieht die Gegenstelle nie eine DICOM-Nachricht — weder eine Ablehnung noch sonst etwas.

### f18 — Wer bei einer Störungssuche AE Title und Portnummer gleichzeitig ändert und das Problem danach behoben ist, weiß damit sicher, welche der beiden Änderungen gewirkt hat.

**Richtig / Falsch**

**Erklärung:** Falsch. Zwei Änderungen in einem Schritt lassen offen, welche davon gewirkt hat — eine Variable pro Versuch ist die einzig verlässliche Vorgehensweise.

### f19 — Ein Presentation Context wird mit Result 3 (`abstract-syntax-not-supported`) abgelehnt. Welches Fehlerbild aus Lektion 4.3 kann dieselbe Ursache haben?

1. Ein Teiltransfer, weil ein bestimmter Objekttyp (z. B. ein Screenshot) regelmäßig abgelehnt wird
2. Ein Split der Study
3. Ein TLS-Handshake-Fehler
4. Eine leere Worklist-Antwort

**Erklärung:** Eine nicht registrierte SOP Class ist eine der beiden realen Ursachen für einen Teiltransfer aus Lektion 4.3 — dieselbe Result-3-Ablehnung, die eine Presentation-Context-Verhandlung scheitern lässt, erklärt dort ein regelmäßig fehlendes Objekt.

### f20 — Welche Aussagen stimmen für Fehlerbilder, die vor jeder DICOM-Antwort auftreten (kein Association-Reject, keine DIMSE-Antwort)? *(Mehrfachauswahl)*

1. Ein TCP-Timeout (Netzweg/Firewall) gehört dazu
2. Ein gescheiterter TLS-Handshake gehört dazu
3. Ein abgelehnter Presentation Context gehört dazu
4. Beide genannten Fälle liefern gar keine DICOM-Nachricht zurück

**Erklärung:** Sowohl ein TCP-Timeout als auch ein gescheiterter TLS-Handshake liegen vor jeder DICOM-Verhandlung — beide liefern keine DICOM-Nachricht zurück. Ein abgelehnter Presentation Context dagegen setzt voraus, dass die Association bereits steht und mindestens eine DICOM-Antwort kam.

### f21 — Namensgleichheit beweist weder bei einem vermuteten Split noch bei einer vermuteten Doppelregistrierung etwas über die tatsächliche Identität.

**Richtig / Falsch**

**Erklärung:** Richtig. Bei einem Split entscheidet die StudyInstanceUID, bei einer Doppelregistrierung die PatientID — in beiden Fällen sieht ein Namens- oder Datumsvergleich allein wie ein harmloser Zufall aus, beweist aber nichts.

### f22 — Eine Storage-Commitment-Ablehnung mit `FailureReason 274` für eine tatsächlich gespeicherte SOP Instance UID beweist, dass der ursprüngliche Bildtransfer fehlgeschlagen ist.

**Richtig / Falsch**

**Erklärung:** Falsch. Bilder können vollständig angekommen sein, während die Commitment-Anfrage für ein *anderes*, nie übermitteltes Objekt scheitert — zwei getrennte Prüfungen mit zwei getrennten Fehlerquellen.

### f23 — Ein Sendeauftrag scheitert mit `Calling AE Title Not Recognized`. Was sagt dieser Ablehnungsgrund aus?

1. Der eigene AE Title der Modalität ist beim Empfänger nicht als erlaubter Absender registriert
2. Der Called AE Title der Modalität ist falsch eingetragen
3. Der Port des Empfängers muss geändert werden
4. Die Transfer Syntax muss angepasst werden

**Erklärung:** `Calling AE Title Not Recognized` heißt: Der Empfänger kennt den vom Absender verwendeten Namen nicht als registrierten Calling AE Title. Ob dafür der Empfänger ergänzt oder die Modalität auf einen bereits registrierten Namen umgestellt wird, entscheidet der Vergleich mit der eigentlich vereinbarten Konfiguration — der Ablehnungsgrund allein schreibt das nicht vor.

### f24 — Welche Aussagen zur systematischen Fehlereingrenzung aus Lektion 4.10 stimmen? *(Mehrfachauswahl)*

1. Ein Fehler sollte reproduziert werden, bevor man ihn erklärt
2. Die ausführlichste Verbositätsstufe (`-d`) beantwortet grundsätzlich mehr relevante Fragen als `-v`
3. Netzweg, Port, Association, Presentation Context und einzelnes Objekt sind unterscheidbare Eingrenzungsstufen
4. Ein ungefilterter Mitschnitt ist für die Diagnose meist genauso brauchbar wie ein gezielt gefilterter

**Erklärung:** Reproduzieren vor Erklären und die schichtweise Eingrenzung sind der Kern der Systematik. `-d` liefert dagegen nur mehr Zeilen insgesamt, nicht automatisch mehr relevante — und ein ungefilterter Mitschnitt macht die eigentliche Association eher unlesbar als brauchbar.

### f25 — Der C-MOVE-Auftrag einer Workstation enthält den Move Destination AE Title. Woher kennt das PACS anschließend IP-Adresse und Port dieses Ziels?

1. Aus dem C-MOVE-Request selbst
2. Aus der eigenen Konfiguration des PACS
3. Aus einer DNS-Abfrage zur Laufzeit
4. Von der anfragenden Workstation direkt

**Erklärung:** Der C-MOVE-Request trägt nur den Move Destination AE Title als Namen. IP und Port dieses Ziels muss das PACS aus seiner eigenen, lokal gepflegten Konfiguration kennen — sonst kann es die zweite, separate C-STORE-Association gar nicht aufbauen.

### f26 — Welche Aussagen zu C-MOVE stimmen? *(Mehrfachauswahl)*

1. C-MOVE besteht aus einem Auftrag ans Archiv und einer separaten C-STORE-Association zum Ziel
2. Ein erfolgreiches C-ECHO zum PACS beweist, dass auch die spätere Storage-Association zum Ziel funktioniert
3. Move Destination AE Title und Called AE Title des PACS erfüllen unterschiedliche Rollen
4. Ein C-FIND-Treffer beweist automatisch einen erfolgreichen C-MOVE

**Erklärung:** C-MOVE zerfällt in zwei unabhängige Vorgänge — Auftrag und Rückrichtung. C-ECHO prüft nur die erste Verbindung zum PACS, nichts über den zweiten Hop zum Ziel. Move Destination AE und Called AE des PACS sind zwei verschiedene Rollen, und ein C-FIND-Treffer belegt nur, dass die Studie auffindbar ist — nicht, dass der anschließende Retrieve gelingt.

### f27 — Ein C-STORE meldet Success. Was genau beweist dieser Status?

1. Dass der Viewer das Objekt anzeigen kann
2. Dass der angesprochene Storage SCP das Objekt angenommen hat
3. Dass die Studie bereits im Archivindex durchsuchbar ist
4. Dass es sich um ein darstellbares Bild handelt

**Erklärung:** Ein C-STORE-Erfolg belegt ausschließlich, dass der angesprochene Storage SCP das Objekt angenommen hat. Indexierung, Darstellbarkeit im Viewer und Objektart sind davon unabhängige, getrennt zu prüfende Schichten.

### f28 — Eine leere Trefferliste im Viewer beweist, dass das gesuchte Objekt nicht im Archiv gespeichert wurde.

**Richtig / Falsch**

**Erklärung:** Falsch. Ein Viewer ist nur eine Sicht auf den Archivbestand und kann durch Filter, Berechtigungen oder eine nicht bildhafte SOP Class leer bleiben, obwohl das Objekt korrekt gespeichert und über C-FIND auffindbar ist.
