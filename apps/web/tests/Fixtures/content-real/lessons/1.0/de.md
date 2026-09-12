---
title: Die Werkzeugkiste
teaser: Die Werkzeuge, die du für DICOM brauchst, gibt es seit Jahrzehnten, sie kosten nichts — und niemand sagt dir, dass sie existieren.
objectives:
  - Für eine Frage das passende Werkzeug auswählen
  - Die Namenslogik der DICOM-Kommandozeilenwerkzeuge lesen
  - In der Spielwiese den ersten C-ECHO und das erste C-STORE fahren
---

## „Und womit schaust du da rein?"

Du hast eine Datei, von der jemand behauptet, sie sei kaputt. Du hast ein Archiv, das Bilder nicht annimmt. Du hast eine Modalität, die angeblich sendet.

In jeder anderen Ecke der IT wüsstest du sofort, was du aufrufst. Bei DICOM stehen die meisten an genau dieser Stelle still — nicht, weil sie zu wenig wüssten, sondern weil ihnen nie jemand gesagt hat, dass es überhaupt etwas aufzurufen gibt.

Das ist keine persönliche Lücke. Es ist eine strukturelle. Wer PACS „mit dazu" bekommt, bekommt ein System übergeben und keine Werkzeugkiste. Die Herstellerschulung zeigt das Produkt, nicht den Standard. Und für alles darüber hinaus ist selten Budget da.

Diese Lektion schließt die Lücke. Jeder Abschnitt endet mit einem Befehl, den du sofort ausführen kannst — **in der Spielwiese rechts neben diesem Text.** Du installierst nichts, du beantragst nichts, du brauchst keine Adminrechte. Ein Browser genügt.

## Die eine Regel, die den halben Werkzeugkasten erklärt

Bevor die Namen kommen, die Logik dahinter. Sonst wirken sie beliebig — sind sie aber nicht:

```
      echoscu      findscu      storescu      storescp
      ────┬───     ────┬───     ─────┬──      ─────┬──
       Dienst       Dienst       Dienst        Dienst
          └── Rolle     └── Rolle    └── Rolle     └── Rolle

   scu  =  du fragst an, du schickst    (Service Class User)
   scp  =  du nimmst an, du antwortest  (Service Class Provider)
```

Damit liest sich der ganze Kasten von selbst: `echoscu` fragt „bist du da?", `storescu` schickt Bilder weg, `storescp` nimmt welche entgegen, `findscu` sucht, `movescu` fordert an.

Was {{term:scu}} und {{term:scp}} wirklich bedeuten, kommt in Lektion 1.5. Für den Moment reicht: **Die Endung sagt dir, auf welcher Seite du stehst.**

---

## Schritt 0: Deine Spielwiese

Alles Folgende braucht ein Gegenüber. Das steht schon bereit — ein Klick auf **Spielwiese starten**, und du hast ein laufendes Archiv samt Werkzeugkasten:

```
$ status
Spielwiese bereit.
  Archiv (Orthanc)   AET ORTHANC   127.0.0.1:4242   Weboberflaeche :8042
  Werkzeuge          dcmtk 3.7.x, python3 + pydicom/pynetdicom, wireshark-cli
  Testdaten          ~/daten/ct-thorax/  (60 Dateien, synthetisch)
  Sitzung laeuft ab in 60 Minuten Inaktivitaet.
```

**Was du daran abliest:** Du hast ein vollständiges Archiv und drei Angaben, die du für jede DICOM-Verbindung brauchst — AE Title, Host, Port. Mehr dazu in Lektion 1.6. Die Testdaten liegen schon da; du musst nichts besorgen.

Das ist ein echter Orthanc, kein Nachbau. Was hier funktioniert, funktioniert auch gegen ein echtes Archiv — und was hier scheitert, scheitert dort genauso.

> **Trotzdem ein Vorbehalt:** Übungsserver sind großzügig konfiguriert und nehmen oft an, was ein echtes Archiv abweisen würde — etwa einen unbekannten Called AE Title. Aus einem „geht hier" folgt nicht „geht im Haus". Genau diese Differenz ist das Thema der Node *Silent CT*.

---

## „Was steht in dieser Datei?"

**DCMTK** von OFFIS in Oldenburg. Kommandozeilenwerkzeuge, BSD-lizenziert, seit den Neunzigern gepflegt, für Windows, Linux und macOS. Das wichtigste Werkzeug daraus heißt `dcmdump`.

### Alles sehen

```
$ dcmdump daten/ct-thorax/0001.dcm | head -14

# Dicom-File-Format

# Dicom-Meta-Information-Header
# Used TransferSyntax: Little Endian Explicit
(0002,0000) UL 198                                      #   4, 1 FileMetaInformationGroupLength
(0002,0002) UI [1.2.840.10008.5.1.4.1.1.2]              #  26, 1 MediaStorageSOPClassUID
(0002,0010) UI [1.2.840.10008.1.2.1]                    #  20, 1 TransferSyntaxUID

# Dicom-Data-Set
# Used TransferSyntax: Little Endian Explicit
(0008,0008) CS [ORIGINAL\PRIMARY\AXIAL]                 #  22, 3 ImageType
(0008,0016) UI [1.2.840.10008.5.1.4.1.1.2]              #  26, 1 SOPClassUID
(0008,0060) CS [CT]                                     #   2, 1 Modality
(0010,0010) PN [MUSTER^ERIKA]                           #  12, 1 PatientName
```

**Was du daran abliest:** Die Datei ist lesbar, sie enthält ein CT-Bild, und sie ist unkomprimiert kodiert (`1.2.840.10008.1.2.1` = Little Endian Explicit). Der Header oben und der Datensatz unten sind zwei getrennte Teile — warum, steht in Lektion 1.1.

### Nur das sehen, was dich interessiert

Bei 300 Zeilen Ausgabe willst du selten alles. `+P` druckt gezielt einzelne Tags und ist wiederholbar:

```
$ dcmdump +P PatientName +P Modality +P SeriesDescription daten/ct-thorax/0001.dcm
(0008,0060) CS [CT]                                     #   2, 1 Modality
(0008,103e) LO [Thorax  1.0  B70f]                      #  18, 1 SeriesDescription
(0010,0010) PN [MUSTER^ERIKA]                           #  12, 1 PatientName
```

**Was du daran abliest:** Du kannst Tags über ihren Namen ansprechen, nicht nur über `(gggg,eeee)`. Die Ausgabe kommt in Tag-Reihenfolge, nicht in deiner Abfragereihenfolge — sortiert wird nach dem Datensatz, nicht nach deiner Eingabe.

### Über einen ganzen Ordner

Der häufigste echte Fall: Ein Ordner mit hunderten Dateien, und du willst wissen, was da eigentlich drin liegt.

```
$ for f in daten/ct-thorax/*.dcm; do dcmdump +P SeriesDescription "$f"; done | sort | uniq -c
     12 (0008,103e) LO [Thorax  1.0  B70f]              #  18, 1 SeriesDescription
     48 (0008,103e) LO [Thorax  5.0  B31f]              #  18, 1 SeriesDescription
```

**Was du daran abliest:** Der Ordner enthält zwei Serien, nicht eine — 12 dünne und 48 dicke Schichten. Genau diese Frage („kommt eine Serie an oder zwei?") taucht in Track 4 ständig auf, und sie ist mit einer Zeile beantwortet statt mit einem Ticket an den Hersteller.

Auf einem Windows-Rechner im Haus sieht dieselbe Schleife so aus:

```
PS> Get-ChildItem *.dcm | ForEach-Object { dcmdump +P SeriesDescription $_.FullName } |
        Group-Object | Select-Object Count, Name
```

---

## „Kommt eine Verbindung überhaupt zustande?"

Dieselbe Sammlung: `echoscu`, `findscu`, `storescu`, `movescu`. Damit stellst du eine Verbindung von *deiner* Seite aus nach, statt zu warten, bis die Modalität es nochmal versucht.

### Der erste Test

```
$ echoscu -v -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242
I: Requesting Association
I: Association Accepted (Max Send PDV: 16372)
I: Sending Echo Request (MsgID 1)
I: Received Echo Response (Success)
I: Releasing Association
```

**Was du daran abliest:** Die Gegenstelle lebt, spricht DICOM und akzeptiert dich unter dem Namen `MEINE-WS`. Ohne `-v` sagt das Werkzeug gar nichts — die DCMTK-Werkzeuge schweigen bei Erfolg. Den Erfolg prüfst du dann am Exitcode:

```
$ echoscu -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242
$ echo $?
0
```

Unter Windows heißt das `echo %ERRORLEVEL%`.

### Der interessantere Fall: wenn es nicht geht

```
$ echoscu -aet MEINE-WS -aec ORTHANC 127.0.0.1 104
F: Association Request Failed: TCP Initialization Error: Connection refused
F: cannot process association request
```

**Was du daran abliest:** Auf Port 104 hört niemand. Das ist eine *Transport*-Aussage — noch gar keine DICOM-Aussage. Hättest du stattdessen `Association Rejected` mit einem Grund bekommen, wäre die Verbindung zustande gekommen und die Gegenstelle hätte dich abgewiesen. Diese beiden Fälle zu unterscheiden ist die halbe Miete in Track 4.

Merk dir das Muster: **Fehlermeldungen sind der Lernstoff, nicht die Erfolgsausgaben.** Deshalb steht in dieser Lektion zu jedem Werkzeug auch ein Fehlerfall.

### Etwas hinschicken

```
$ storescu -v -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242 daten/ct-thorax/0001.dcm
I: Requesting Association
I: Association Accepted (Max Send PDV: 16372)
I: Sending Store Request (MsgID 1, CT)
I: Received Store Response (Success)
I: Releasing Association
```

**Was du daran abliest:** Erst dieser Schritt beweist etwas. Ein `ping` beweist gar nichts, ein C-ECHO beweist wenig — ein erfolgreiches C-STORE beweist, dass die Gegenstelle deinen konkreten Objekttyp in deiner konkreten Kodierung annimmt. Schau danach in der Weboberfläche auf Port 8042 nach: Das Bild ist da.

### Etwas suchen

```
$ findscu -v -S -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242 \
          -k QueryRetrieveLevel=STUDY -k PatientName="MUSTER*" \
          -k StudyDate -k StudyDescription -k StudyInstanceUID

I: Find Response: 1 (Pending)
I: # Dicom-Data-Set
I: (0008,0020) DA [20260911]                            #   8, 1 StudyDate
I: (0008,0052) CS [STUDY]                               #   6, 1 QueryRetrieveLevel
I: (0008,1030) LO [CT Thorax nativ]                     #  16, 1 StudyDescription
I: (0010,0010) PN [MUSTER^ERIKA]                        #  12, 1 PatientName
I: (0020,000d) UI [1.2.276.0.7230010.3.1.2.8323329...]  #  50, 1 StudyInstanceUID
I: Received Final Find Response (Success)
```

**Was du daran abliest:** Ein `-k` mit Wert ist ein Suchkriterium, ein `-k` ohne Wert ist ein Rückgabefeld. `MUSTER*` zeigt, dass Wildcards funktionieren. Und `-S` heißt Study Root — welche Ebenen es gibt und warum das wichtig ist, steht in Lektion 2.3.

### Selbst empfangen

Das Gegenstück, wenn du prüfen willst, ob ein Gerät überhaupt etwas losschickt:

```
$ mkdir eingang
$ storescp -v -aet MEIN-EMPFANG -od ./eingang 11112
I: Association Received (127.0.0.1: MEINE-WS -> MEIN-EMPFANG)
I: Association Acknowledged (Max Send PDV: 16372)
I: Received Store Request (MsgID 1, CT)
I: Sending Store Response (Success)
```

**Was du daran abliest:** Du bist jetzt selbst das Archiv. Die empfangenen Objekte liegen in `./eingang` und lassen sich sofort mit `dcmdump` ansehen. Wenn hier nichts ankommt, während das Gerät „gesendet" meldet, weißt du, auf welcher Seite du weitersuchen musst. Probier es in einem zweiten Terminal aus: `storescp` laufen lassen und mit `storescu` auf Port 11112 etwas hinschicken.

---

## „Was geht wirklich über die Leitung?"

**Wireshark** — dasselbe Wireshark, das du vermutlich schon kennst. Es bringt einen DICOM-Dissector mit. Anzeigefilter:

```
dicom
```

oder enger, wenn viel los ist:

```
dicom && ip.addr == 10.20.0.30
```

**Was du daran abliest:** Nicht die Interpretation eines Systems, sondern die Bytes. Du siehst, welche Objekttypen ausgehandelt und welche abgelehnt wurden, und wo genau die Gegenstelle aufgelegt hat. Wireshark erkennt DICOM auf den üblichen Ports von allein; läuft die Verbindung auf einem ungewöhnlichen Port, hilft *Rechtsklick → Decode As → DICOM*.

Das ist die letzte Instanz. Wenn zwei Hersteller sich gegenseitig die Schuld geben, entscheidet ein Mitschnitt die Diskussion.

---

## „Wie mache ich das für 300 Dateien?"

**pydicom**, wenn Shell-Schleifen nicht mehr reichen:

```python
from pathlib import Path
from pydicom import dcmread

for f in sorted(Path("daten/ct-thorax").glob("*.dcm")):
    ds = dcmread(f, stop_before_pixels=True)
    print(ds.SeriesNumber, ds.Modality, ds.SeriesDescription, sep="\t")
```

```
2	CT	Thorax  1.0  B70f
2	CT	Thorax  1.0  B70f
3	CT	Thorax  5.0  B31f
```

**Was du daran abliest:** Tags sind in Python normale Attribute. `stop_before_pixels=True` überspringt die Pixeldaten — bei 300 CT-Schichten ist das der Unterschied zwischen einer Sekunde und einer Minute. Für Netzwerkdienste gibt es dazu passend `pynetdicom`.

**dcm4che** ist die Alternative in Java: dieselben Dienste, teilweise mächtigere Abfragen. Beide Sammlungen sind in Ordnung — such dir eine aus und lerne die.

---

## „Ich will das Bild sehen"

**Weasis** ist ein freier, quelloffener Viewer für alle drei Betriebssysteme. Daneben gibt es eine Reihe kostenloser Windows-Viewer; die Lizenzbedingungen unterscheiden sich (manche sind nur nichtkommerziell frei), das gehört vor einer Installation im Haus einmal gelesen.

**Wichtig:** Ein Viewer ist ein Werkzeug für Menschen, kein Prüfwerkzeug für Dateien. Viewer sind großzügig und ergänzen Fehlendes stillschweigend — Archive sind es nicht. Was der Viewer anzeigt, sagt wenig darüber, ob ein Archiv das Objekt annimmt.

---

## „Woher nehme ich Bilder zum Üben?"

In der Spielwiese liegen sie schon unter `~/daten/`. Alles darin ist synthetisch erzeugt, kein einziger Datensatz stammt aus einem echten Haus.

Falls du später eigene brauchst: mit `pydicom` baust du ein minimales, gültiges Objekt, und die Toolkits bringen Beispieldaten mit. Oder du nimmst öffentlich freigegebene, anonymisierte Datensätze.

**Nie echte Patientendaten aus dem eigenen Haus**, auch nicht anonymisiert. Der Herkunftsnachweis wäre nicht zu führen, und es braucht genau einen eingebrannten Namen im Bild, damit aus einer Übung ein Meldefall wird.

---

## Im Alltag heißt das

| Deine Frage | Der Befehl |
|---|---|
| Was steckt in dieser Datei? | `dcmdump datei.dcm` |
| Nur ein bestimmter Wert? | `dcmdump +P PatientName datei.dcm` |
| Was liegt in diesem Ordner? | `for f in *.dcm; do dcmdump +P SeriesDescription "$f"; done \| sort \| uniq -c` |
| Lebt die Gegenstelle? | `echoscu -v -aet MEINE-WS -aec ZIEL host port` |
| Welche Studien kennt das Archiv? | `findscu -v -S -k QueryRetrieveLevel=STUDY -k PatientName="X*" …` |
| Nimmt es mein Objekt an? | `storescu -v -aet MEINE-WS -aec ZIEL host port datei.dcm` |
| Sendet das Gerät überhaupt? | `storescp -v -aet MEIN-EMPFANG -od ./eingang 11112` |
| Warum bricht die Verbindung ab? | Wireshark, Anzeigefilter `dicom` |

Die Tabelle ist kürzer, als der Respekt vor dem Thema vermuten lässt. Mit diesen acht Zeilen bist du bei den allermeisten Störungen handlungsfähig.

> ### Stolperfallen
>
> **„Ich probier das mal schnell am Produktivarchiv."**
> Zwei Gründe dagegen. Eine zu weit gefasste Abfrage kann ein Archiv ernsthaft beschäftigen. Und ein `storescu` legt echte Objekte ab, die anschließend jemand wieder herausräumen muss — mit allem, was das an Dokumentation nach sich zieht. Zum Üben ist die Spielwiese da; sie gehört niemandem und wird nach der Sitzung weggeräumt.
>
> **„Der Übungsserver nimmt es an, also passt es."**
> Die Spielwiese ist großzügig konfiguriert. Ein echtes Archiv prüft mehr — Called AE Title, erlaubte SOP Classes, Absender-IP. Was hier läuft, muss im Haus nicht laufen.
>
> **„Diagnosewerkzeuge sind unkritisch."**
> Sind sie nicht. Ende Juni 2026 hat die US-Behörde CISA eine Advisory zu DCMTK veröffentlicht (ICSMA-26-181-01): mehrere Schwachstellen, darunter eine mit Höchstbewertung, betroffen sind Versionen bis einschließlich 3.7.0, empfohlen wird der aktuelle Stand aus dem Projekt-Repository. Für die Spielwiese ist das erledigt. Sobald du dieselben Werkzeuge im Haus einsetzt, ist es deine Aufgabe: aktuell halten und nicht dauerhaft auf einem Gerät liegen lassen, das im Modalitätennetz hängt.
>
> **„Ein Werkzeug reicht."**
> `dcmdump` beantwortet keine Netzwerkfrage, Wireshark keine Inhaltsfrage. Das ist die Zweiteilung aus Lektion 1.1: Format und Protokoll. Die Werkzeugwahl ist die praktische Seite dieser Unterscheidung.
>
> **„Das kann ich mir nicht merken."**
> Musst du nicht. Jedes Werkzeug zeigt mit `--help` seine Möglichkeiten, und die Reihenfolge ist bei den DCMTK-Werkzeugen fast überall gleich: erst Optionen, dann Host, dann Port, dann Datei.

---

## Deine ersten drei Befehle

Spielwiese starten, dann der Reihe nach:

```
$ echoscu -v -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242
$ storescu -v -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242 daten/ct-thorax/0001.dcm
$ dcmdump +P PatientName +P SeriesDescription daten/ct-thorax/0001.dcm
```

Wenn `echoscu` „Received Echo Response (Success)" meldet und das Bild danach in der Weboberfläche auf Port 8042 auftaucht, hast du eine Association aufgebaut, ein DICOM-Objekt übertragen und einen Datensatz gelesen — die drei Dinge, um die sich der Rest dieses Tracks dreht.

> **Für später, freiwillig:** Wenn du dasselbe zu Hause haben willst, reicht eine Zeile — `docker run --rm -p 4242:4242 -p 8042:8042 jodogne/orthanc` — plus DCMTK aus den Paketquellen. Dieselben AE Titles, dieselben Ports, dieselben Befehle. Auf einem Dienstrechner gilt dafür allerdings weiterhin euer Freigabeprozess; der gute Nebeneffekt ist, dass ein Antrag über null Euro leichter zu begründen ist als einer über eine vierstellige Schulung.

## Selbstcheck

<details>
<summary>Ein Kollege sagt: „Die Datei ist kaputt, der Viewer zeigt sie nicht." Was prüfst du, und womit?</summary>

Erst `dcmdump datei.dcm`. Der Viewer ist keine Prüfinstanz — er kann tolerant über Fehler hinweggehen oder an einer Kleinigkeit scheitern, die ein Archiv nicht stören würde. Kommt ein Lesefehler, ist die Datei wirklich beschädigt; kommt ein sauberer Dump, liegt es am Viewer oder an einzelnen Werten, nicht an der Datei als Ganzes.
</details>

<details>
<summary>`echoscu` läuft durch und gibt nichts aus. Hat es funktioniert?</summary>

Vermutlich ja — die DCMTK-Werkzeuge schweigen bei Erfolg. Sicher weißt du es über den Exitcode (`echo $?` bzw. `echo %ERRORLEVEL%`, 0 heißt Erfolg) oder indem du es mit `-v` wiederholst. Diese Stille ist der erste Stolperstein für Einsteiger und kein Fehler.
</details>

<details>
<summary>Was ist der Unterschied zwischen „Connection refused" und „Association Rejected"?</summary>

„Connection refused" heißt: Auf dem Port hört niemand — eine reine Transportaussage, noch bevor DICOM ins Spiel kommt. „Association Rejected" heißt: Die Verbindung kam zustande, die Gegenstelle hat dich aber abgewiesen, und sie nennt dazu meistens einen Grund. Das eine ist Netzwerk, das andere Konfiguration. Die beiden zu verwechseln kostet die meiste Zeit bei Störungssuchen.
</details>

<details>
<summary>Warum solltest du im Haus nicht am Produktivarchiv üben — obwohl du die Rechte hättest?</summary>

Weil Übungsobjekte echte Objekte sind, sobald sie dort liegen. Sie tauchen in Listen auf, werden mitgesichert und müssen von jemandem wieder entfernt werden. Dazu kommt, dass unbedachte Abfragen ein Archiv spürbar belasten können. Die Spielwiese kostet dich einen Klick und diese ganze Kategorie von Ärger nie wieder.
</details>

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Was sagt dir die Endung `scu` im Namen eines Werkzeugs?**
1. Dass es sich um ein Kommandozeilenwerkzeug handelt
2. Dass du die anfragende bzw. sendende Seite bist
3. Dass du die antwortende bzw. empfangende Seite bist
4. Dass es nur mit älteren Systemen funktioniert

**q2 — Welche Aussagen stimmen?** *(Mehrfachauswahl)*
1. Um DICOM zu üben, brauchst du Zugriff auf ein Produktivarchiv
2. Die DCMTK-Werkzeuge geben bei Erfolg in der Regel keine Ausgabe aus
3. Ein Viewer ist ein verlässlicher Test dafür, ob ein Archiv ein Objekt annimmt
4. Wireshark bringt einen DICOM-Dissector mit

**q3 — Mit welchem Befehl liest du gezielt nur die `SeriesDescription` aus einer Datei?** *(Freitext)*

---

**Als Nächstes:** [1.1 — Was DICOM eigentlich ist](../1.1/) trennt die beiden Dinge, die denselben Namen tragen — und sortiert damit, welches der Werkzeuge von oben du bei welchem Störungsbild überhaupt in die Hand nimmst.

---

*Werkzeuglage geprüft am 12.09.2026. Ausgaben sind gekürzt, aber nicht erfunden. Versionsnummern stehen nur dort, wo sie eine Aussage tragen — alles andere veraltet schneller, als die Lektion überarbeitet wird.*
