---
title: Abschlussprüfung — Fundamente
intro: 24 Fragen aus Track 1. Ab 80 % ist der Track abgeschlossen. Beliebig oft wiederholbar.
---

### f01 — Was sagt dir die Endung `scu` im Namen eines Werkzeugs?

1. Dass es sich um ein Kommandozeilenwerkzeug handelt
2. Dass du die anfragende bzw. sendende Seite bist
3. Dass du die antwortende bzw. empfangende Seite bist
4. Dass es nur mit älteren Systemen funktioniert

**Erklärung:** `scu` steht für Service Class User — die Seite, die einen Dienst in Anspruch nimmt, also anfragt oder sendet. Die Gegenrolle, die antwortet oder empfängt, heißt Service Class Provider (`scp`). Mit „Kommandozeilenwerkzeug" oder „älteren Systemen" hat die Endung nichts zu tun.

### f02 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. Um DICOM zu üben, brauchst du Zugriff auf ein Produktivarchiv
2. Die DCMTK-Werkzeuge geben bei Erfolg in der Regel keine Ausgabe aus
3. Ein Viewer ist ein verlässlicher Test dafür, ob ein Archiv ein Objekt annimmt
4. Wireshark bringt einen DICOM-Dissector mit

**Erklärung:** Die DCMTK-Werkzeuge schweigen bei Erfolg — Erfolg zeigt sich am Exitcode oder mit `-v`. Wireshark erkennt DICOM von Haus aus. Für die ersten Übungen reicht die Spielwiese, ein Produktivarchiv ist nicht nötig; und ein Viewer ist tolerant und ergänzt fehlende Angaben stillschweigend, er beweist also nichts über die Annahme durch ein Archiv.

### f03 — Die DCMTK-Werkzeuge geben bei einer erfolgreichen Verbindung viel Textausgabe aus.

**Richtig / Falsch**

**Erklärung:** Falsch. Ohne `-v` melden die DCMTK-Werkzeuge bei Erfolg gar nichts — diese Stille überrascht viele Einsteiger. Sicherheit gibt der Exitcode (`echo $?`, `0` heißt Erfolg) oder ein Wiederholen mit `-v`.

### f04 — Mit welchem Befehl liest du gezielt nur die `SeriesDescription` aus einer Datei? *(Freitext)*

**Erklärung:** `dcmdump +P SeriesDescription datei.dcm`. Die Option `+P` druckt gezielt einzelne Tags über ihren Namen, statt den ganzen Dump auszugeben, und ist beliebig oft wiederholbar für mehrere Tags gleichzeitig.

### f05 — Wo steht der Name des Patienten bei einer DICOM-Aufnahme?

1. In der Datenbank des Archivs
2. In einer Begleitdatei neben dem Bild
3. In derselben Datei wie die Pixeldaten
4. Im Dateinamen

**Erklärung:** DICOM ist kein Bild mit Beipackzettel — Name, Geburtsdatum und alle weiteren Kontextangaben stehen untrennbar in derselben Datei wie die Pixel. Genau das erlaubt es, ein Objekt auf einen USB-Stick zu legen und es bleibt trotzdem seinem Kontext zugeordnet.

### f06 — Welche vier Zeichen stehen ab Byte 128 einer DICOM-Datei?

1. `DICM`
2. `DCIM`
3. `MDIC`
4. `ICOM`

**Erklärung:** `DICM` markiert ab Byte 128 (nach der 128 Byte langen Preamble) das DICOM-Dateiformat — daran erkennt `dcmftest` eine gültige Datei, unabhängig von Dateiendung oder Kompression.

### f07 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. DICOM ist gleichzeitig Dateiformat und Netzwerkprotokoll
2. Jede DICOM-Datei muss auf `.dcm` enden
3. Ein Structured Report enthält keine Pixeldaten
4. Ein Archiv indexiert Dateien, die man in sein Verzeichnis kopiert

**Erklärung:** DICOM ist Format und Protokoll zugleich, und ein Structured Report ist ein vollwertiges DICOM-Objekt ganz ohne Pixel. Eine Dateiendung ist dagegen nie Pflicht, und ein Archiv nimmt Objekte ausschließlich über einen Dienst entgegen — kopierte Dateien im Speicherverzeichnis bleiben für das System unsichtbar.

### f08 — Ein Structured Report ist kein vollwertiges DICOM-Objekt, weil er keine Pixeldaten enthält.

**Richtig / Falsch**

**Erklärung:** Falsch. Ein Structured Report enthält Messwerte und Befundtexte statt Pixel, ist aber trotzdem ein vollständiges, eigenständiges DICOM-Objekt. „DICOM ist ein Bildformat" gilt also nur zum Teil.

### f09 — Welcher Wert identifiziert eine Untersuchung eindeutig?

1. AccessionNumber
2. StudyDescription
3. StudyInstanceUID
4. StudyDate zusammen mit StudyTime

**Erklärung:** Nur die `StudyInstanceUID` identifiziert eine Study zweifelsfrei. Beschreibungen, Datum und Uhrzeit sind für Menschen gedacht und können übereinstimmen, ohne dass es sich um dieselbe Untersuchung handelt.

### f10 — Auf welcher Ebene steht die Angabe `Modality`?

1. Patient
2. Study
3. Series
4. Instance

**Erklärung:** `Modality` sitzt auf der Series-Ebene, nicht auf der Study. Deshalb kann eine Study gemischt sein — ein PET/CT enthält PT- und CT-Serien in derselben Untersuchung; `ModalitiesInStudy` auf Study-Ebene ist dafür nur eine Zusammenfassung, kein eigenständiger Wert.

### f11 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. Jedes Objekt enthält die Schlüssel aller übergeordneten Ebenen
2. `Modality` steht auf der Series-Ebene
3. Ein CT-Scan erzeugt immer genau eine Serie
4. Der Patientenname ist der Identifikator des Patienten

**Erklärung:** Jedes Objekt trägt PatientID, StudyInstanceUID, SeriesInstanceUID und seine eigene SOPInstanceUID — daraus errechnet das Archiv die Hierarchie. `Modality` gehört zur Series-Ebene. Ein CT liefert dagegen routinemäßig mehrere Serien (dünne/dicke Schichten, unterschiedliche Rekonstruktionskerne), und identifizierend ist die `PatientID`, nicht der Name.

### f12 — Der Patientenname identifiziert den Patienten eindeutig.

**Richtig / Falsch**

**Erklärung:** Falsch. Identifikator ist die `PatientID`. Namen ändern sich, werden falsch geschrieben, und zwei Menschen können gleich heißen — für das Archiv sind zwei Objekte mit gleicher ID derselbe Patient, unabhängig vom Namen.

### f13 — Was sagt dir eine ungerade Group-Nummer?

1. Das Tag ist beschädigt
2. Das Tag ist privat, also herstellerspezifisch
3. Das Tag gehört zu den Pixeldaten
4. Das Tag ist optional

**Erklärung:** Gerade Group heißt Standard, ungerade Group heißt privat. Private Tags sind normal und erlaubt — lesbar sind sie nur mit dem passenden Private Creator, der den Hersteller benennt.

### f14 — Aus wie vielen Bestandteilen besteht ein `PN`-Wert höchstens, getrennt durch `^`?

1. Drei
2. Vier
3. Fünf
4. Sechs

**Erklärung:** `PN` trennt bis zu fünf Bestandteile: Familienname, Vorname, mittlerer Name, Präfix, Suffix. `MUSTER^ERIKA` heißt deshalb „Erika Muster", nicht „Muster hoch Erika" — Systeme, die alles in ein Feld schreiben, machen den Namen unsortierbar.

### f15 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. Der Keyword-Name steht mit in der Datei
2. `IS` und `DS` werden als Text gespeichert
3. Der Backslash trennt mehrere Werte in einem Tag
4. `PN` besteht aus bis zu fünf durch `^` getrennten Bestandteilen

**Erklärung:** `IS`/`DS` sind Text, auch wenn sie Zahlen darstellen — `1.0` und `1.00` sind deshalb zwei verschiedene Werte. Der Backslash trennt Einzelwerte (Value Multiplicity), und `PN` hat bis zu fünf Bestandteile. Der Keyword-Name dagegen steht nirgends in der Datei — er kommt aus dem Wörterbuch des lesenden Werkzeugs.

### f16 — Ein `DA`-Wert wird immer im Format `YYYYMMDD` gespeichert, ohne Punkte.

**Richtig / Falsch**

**Erklärung:** Richtig. `DA` ist immer `YYYYMMDD`. Ein Datum wie `11.09.2026` in einem DA-Feld ist ein Fehler, selbst wenn eine Anzeige es klaglos akzeptiert.

### f17 — Zwei Objekte haben dieselbe SOP Instance UID. Wie behandelt ein Archiv sie?

1. Als zwei verschiedene Bilder
2. Als dasselbe Objekt — es ersetzt oder lehnt ab
3. Es legt beide nebeneinander ab
4. Das hängt vom Patientennamen ab

**Erklärung:** Identität steckt in der UID, nicht im Dateinamen oder Speicherort. Zwei Objekte mit gleicher SOP Instance UID sind für jedes Archiv dasselbe Objekt — eine neue Fassung ersetzt die alte oder wird abgelehnt, ein drittes Verhalten gibt es nicht.

### f18 — Welche Aussagen über UIDs stimmen? *(Mehrfachauswahl)*

1. Sie dürfen nur Ziffern und Punkte enthalten
2. Sie sind auf 64 Zeichen begrenzt
3. Eine gelöschte UID darf wiederverwendet werden
4. Der Anfang verrät, wer die UID erzeugt hat

**Erklärung:** UIDs bestehen ausschließlich aus Ziffern und Punkten und sind auf höchstens 64 Zeichen begrenzt. Die Wurzel am Anfang verrät den Erzeuger — `1.2.840.10008` etwa gehört dem Standard selbst. Eine einmal vergebene UID wird dagegen nie wiederverwendet, auch nicht nach dem Löschen des Objekts.

### f19 — Nach dem Löschen eines Objekts darf seine UID später wiederverwendet werden.

**Richtig / Falsch**

**Erklärung:** Falsch. Eine UID wird nie wiederverwendet, auch nicht nach Jahren. Manche Archive führen sogar Listen gelöschter UIDs, damit ein Objekt nicht versehentlich unter alter Identität zurückkommt.

### f20 — Welche UID-Wurzel gehört dem DICOM-Standard selbst? *(Freitext)*

**Erklärung:** `1.2.840.10008`. Darunter liegen well-known UIDs wie SOP-Class- und Transfer-Syntax-UIDs, die weltweit dieselbe, nachschlagbare Bedeutung haben — im Unterschied zu erzeugten UIDs (Study, Series, Instance), die die Wurzel des jeweiligen Herstellers tragen.

### f21 — Ein CT schickt Bilder an das Archiv. Welche Rolle hat das CT?

1. Storage SCP
2. Storage SCU
3. Kommt auf das Archiv an
4. Beides gleichzeitig

**Erklärung:** Wer den Dienst anfragt, ist SCU — hier also das CT, das Bilder abschickt. Die Rolle hängt an der Situation, nicht am Gerät: Dasselbe CT ist bei einer Worklist-Abfrage ebenfalls SCU, beim Beantworten eines C-ECHO dagegen SCP.

### f22 — Ein erfolgreiches C-ECHO beweist, dass …

1. … die beiden Seiten eine Association aufbauen können
2. … Bilder übertragen werden können
3. … das Archiv genug Speicher hat
4. … die Transfer Syntax für Bilddaten passt

**Erklärung:** Ein C-ECHO handelt ausschließlich die Verification SOP Class aus — es beweist nur, dass beide Seiten zueinander finden und eine Association aufbauen können. Über Bildobjekttypen oder deren Kodierung wird dabei nichts verhandelt; das geschieht erst bei einer eigenen Association für den Storage-Dienst.

### f23 — Welche Aussagen über AE Titles stimmen? *(Mehrfachauswahl)*

1. Sie entsprechen immer dem Hostnamen
2. Maximal 16 Zeichen
3. Groß- und Kleinschreibung ist egal
4. Führende und angehängte Leerzeichen sind bedeutungslos

**Erklärung:** AE Titles sind auf 16 Zeichen begrenzt und werden zeichengenau verglichen, Groß-/Kleinschreibung zählt also mit. Führende und angehängte Leerzeichen sind laut Standard dagegen bedeutungslos. Mit dem Hostnamen hat der AE Title technisch nichts zu tun, auch wenn viele Häuser sie aus Konvention gleich benennen.

### f24 — Der AE Title ist technisch dasselbe wie der Hostname eines Geräts.

**Richtig / Falsch**

**Erklärung:** Falsch. AE Title und Hostname sind zwei unabhängige Dinge. Dass viele Häuser sie gleich benennen, ist eine Konvention, die das Leben erleichtert — technisch hat der AE Title mit DNS nichts zu tun.

### f25 — An wie vielen Stellen muss eine neue DICOM-Verbindung eingetragen werden?

1. Nur beim Sender
2. Nur beim Empfänger
3. Bei beiden, und meist zusätzlich in der Firewall
4. Nur im DNS

**Erklärung:** Die Konfiguration ist immer symmetrisch: Der Sender braucht vier Felder (eigener AE Title, Ziel-AE-Title, Ziel-Host, Ziel-Port), der Empfänger muss den Absender als erlaubten AE Title kennen — und oft kommt eine Firewall-Freigabe für Port und Richtung dazu.

### f26 — Mit welchem Werkzeug machst du dich selbst zum Empfänger, um zu prüfen, ob ein Gerät wirklich sendet?

1. `dcmdump`
2. `echoscu`
3. `findscu`
4. `storescp`

**Erklärung:** `storescp` startet einen eigenen Empfänger. Damit siehst du, was wirklich ankommt und welchen Calling AE Title die Gegenstelle tatsächlich schickt — das beantwortet die häufigste Streitfrage bei Inbetriebnahmen zuverlässiger als eine Konsolenmeldung.

### f27 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. Ein AE Title darf höchstens 16 Zeichen haben
2. Port 104 ist der einzige zulässige DICOM-Port
3. `Calling AE Title Not Recognized` heißt, die Gegenstelle kennt dich nicht
4. Ein erfolgreiches C-ECHO beweist, dass Bilder ankommen

**Erklärung:** 16 Zeichen sind die Obergrenze für einen AE Title, und `Calling AE Title Not Recognized` heißt: Die Gegenstelle kennt meinen eigenen Namen nicht. Es gibt aber zwei registrierte Ports (104 und 11112), keinen einzigen zulässigen — und ein C-ECHO verhandelt nur die Verification SOP Class, nichts über Bildübertragung.

### f28 — Ein „Verbindung OK" auf der Gerätekonsole beweist, dass die DICOM-Verbindung funktioniert.

**Richtig / Falsch**

**Erklärung:** Falsch. Viele Konsolen melden das schon, wenn nur die TCP-Verbindung zustande kam — bevor irgendein DICOM-Name geprüft wurde. Aussagekräftig ist erst das Log der Gegenstelle: Taucht dort eine Association auf, gab es wirklich ein DICOM-Gespräch.

### f29 — Welche Transfer Syntax muss jede DICOM-Anwendung beherrschen?

1. Explicit VR Little Endian
2. Implicit VR Little Endian
3. JPEG Lossless
4. Explicit VR Big Endian

**Erklärung:** Implicit VR Little Endian ist die verpflichtende Rückfallebene, die jede konforme Anwendung beherrschen muss. Explicit VR Big Endian ist dagegen zurückgezogen und kommt nur noch in Altbeständen vor.

### f30 — Wie lautet die UID von Explicit VR Little Endian? *(Freitext)*

**Erklärung:** `1.2.840.10008.1.2.1`. Das ist der Normalfall in Dateien und auf der Leitung — im Unterschied zu `1.2.840.10008.1.2` (Implicit VR Little Endian, die verpflichtende Rückfallebene).

### f31 — Verlustfreie Kompression lässt sich vollständig rückgängig machen.

**Richtig / Falsch**

**Erklärung:** Richtig. Aus einer verlustfreien Kompression (etwa JPEG Lossless oder RLE Lossless) kommt man Pixel für Pixel wieder heraus. Aus einer verlustbehafteten Kompression dagegen nicht — dort ist der Unterschied nach dem ersten Durchgang unwiderruflich weg.

### f32 — Wie lautet die UID von Implicit VR Little Endian? *(Freitext)*

**Erklärung:** `1.2.840.10008.1.2`. Sie ist der gemeinsame Nenner, auf den sich zwei Systeme notfalls immer einigen können, weil jede konforme Anwendung sie beherrschen muss — nur eben unkomprimiert und ohne die VR explizit in der Datei zu nennen.

### f33 — Woraus besteht ein Presentation Context?

1. Aus AE Title und Port
2. Aus einer ID, einer Abstract Syntax und einer Liste von Transfer Syntaxes
3. Aus Absender und Empfänger
4. Aus der Max-PDU-Größe

**Erklärung:** Ein Presentation Context hat drei Teile: eine ungerade, nur für diese Verbindung gültige ID, eine Abstract Syntax (der Objekttyp) und eine Liste angebotener Transfer Syntaxes, aus der die Gegenseite wählt.

### f34 — Welche Aussagen stimmen? *(Mehrfachauswahl)*

1. Jeder Presentation Context wird einzeln angenommen oder abgelehnt
2. Eine angenommene Association garantiert, dass Objekte übertragen werden
3. Kontext-IDs sind ungerade und gelten nur für diese Verbindung
4. Beim C-ECHO wird nur die Verification SOP Class ausgehandelt

**Erklärung:** Die Association wird als Ganzes angenommen, aber jeder einzelne Presentation Context einzeln — eine Association kann also stehen, während alle Kontexte abgelehnt sind. Kontext-IDs sind ungerade und gelten nur innerhalb der aktuellen Verbindung. Beim C-ECHO wird ausschließlich die Verification SOP Class verhandelt.

### f35 — `Association Accepted` im Log beweist, dass die Bilder übertragen wurden.

**Richtig / Falsch**

**Erklärung:** Falsch. Angenommen wurde die Verhandlung, nicht die Übertragung. Jeder Presentation Context wird einzeln beantwortet; ist genau deiner abgelehnt, steht die Association und trotzdem kommt `No presentation context for: CT Image Storage`.

### f36 — Welche Meldung bekommst du, wenn die Gegenstelle den Objekttyp gar nicht kennt? *(Freitext)*

**Erklärung:** `No presentation context for:` — gefolgt vom betroffenen Objekttyp. Sie erscheint sowohl, wenn die Abstract Syntax unbekannt ist, als auch, wenn nur die angebotene Transfer Syntax nicht unterstützt wird; das genaue Kontextergebnis im Association-Log unterscheidet beide Fälle.

### f37 — Ein C-ECHO zwischen Modalität und Archiv ist grün, trotzdem kommt kein Bild an. Was prüfst du als Nächstes?

1. Das Presentation-Context-Ergebnis für den Bild-Objekttyp (Abstract Syntax und Transfer Syntax)
2. Ob das C-ECHO wirklich erfolgreich war, indem du es wiederholst
3. Ob der AE Title noch einmal neu eingetragen werden muss
4. Ob Port 104 in der Firewall freigegeben ist

**Erklärung:** C-ECHO verhandelt ausschließlich die Verification SOP Class — Namen und Netzwerkweg sind damit schon bewiesen. Der nächste Schritt ist deshalb, das Presentation-Context-Ergebnis der eigentlichen Bild-Association anzusehen: Entweder kennt die Gegenstelle den Objekttyp nicht, oder sie kennt ihn, aber nicht die angebotene Kodierung. AE Title und Firewall sind bereits durch das grüne C-ECHO belegt.

### f38 — Zwei Studies sehen in jeder sichtbaren Angabe gleich aus — woran entscheidest du, ob es wirklich zwei sind? *(Freitext)*

**Erklärung:** An der `StudyInstanceUID`. Sichtbare Angaben wie Patientenname, Datum oder Beschreibung sind für Menschen gemacht und identifizieren nichts — nur ein Vergleich der UID entscheidet, ob zwei Objekte zur selben Untersuchung gehören oder zwei UIDs für einen Termin vergeben wurden.

### f39 — Ein Gerät sendet nur manche Serien einer Untersuchung, der Rest kommt nie an. Welcher Unterschied zwischen den Serien erklärt das am wahrscheinlichsten?

1. Die abgelehnten Serien sind in einer Kodierung komprimiert, die die Gegenstelle nicht akzeptiert
2. Die abgelehnten Serien haben eine andere PatientID
3. Die abgelehnten Serien haben mehr Instances
4. Die abgelehnten Serien wurden zuerst gesendet

**Erklärung:** Objekttyp und Kodierung werden gemeinsam ausgehandelt. Serien, die anders — etwa verlustbehaftet — kodiert sind als der Rest, können an genau dieser Kombination scheitern, während unkomprimierte Serien derselben Untersuchung anstandslos durchgehen. PatientID, Instance-Zahl und Sendereihenfolge erklären ein serienselektives Scheitern dagegen nicht.

### f40 — Ein neues Gerät kommt ins Haus, es sendet nichts ins Archiv. Welche zwei Angaben müssen dafür in jedem Fall stimmen? *(Mehrfachauswahl)*

1. Der Calling AE Title des neuen Geräts ist beim Archiv als erlaubter Absender eingetragen
2. Das neue Gerät hat Zugriff auf das Internet
3. Der Empfänger kennt seinen eigenen AE Title
4. Der im Gerät eingetragene Called AE Title stimmt zeichengenau mit dem AE Title des Archivs überein

**Erklärung:** Die Konfiguration ist immer symmetrisch: Das Archiv muss den Absender kennen (1), und das Gerät muss das Archiv unter dessen tatsächlichem, zeichengenauen AE Title ansprechen (4). Ob der Empfänger seinen eigenen Namen „kennt" ist keine sinnvolle Fehlerquelle, und ein Internetzugang hat mit einer lokalen DICOM-Verbindung nichts zu tun.
