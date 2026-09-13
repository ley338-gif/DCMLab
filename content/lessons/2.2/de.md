---
title: "C-STORE: Bilder senden und empfangen"
teaser: Der Dienst, der DICOM überhaupt zu einem Netzwerkprotokoll macht — einmal ganz von vorne, mit beiden Rollen.
objectives:
  - Den Ablauf eines C-STORE als Absender und als Empfänger beschreiben
  - Den DIMSE-Status einer Store Response lesen und einordnen
  - Eine komplette Serie statt einer einzelnen Datei übertragen
---

## Eine ganze Serie soll rüber, nicht nur eine Testdatei

Eine Datei zu senden ist schnell gezeigt. Aber eine echte Untersuchung
besteht aus Dutzenden Objekten — und die Frage, ob dafür eine
Association reicht oder viele nötig sind, entscheidet direkt darüber,
wie ein Store-Log zu lesen ist.

## Ein Objekt, eine Bestätigung

{{term:c-store}} bestätigt jedes Objekt einzeln — auch wenn mehrere
Objekte über dieselbe Association laufen:

```
$ storescu -v -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242 instance-0001.dcm
I: Requesting Association
I: Association Accepted
I: Sending file: instance-0001.dcm
I: Sending Store Request: MsgID 1, (CT)
I: Received Store Response (Status: 0x0000 - Success)
I: Releasing Association
```
**Was du daran abliest:** Eine Association, eine Anfrage, eine
Bestätigung mit explizitem Status `0x0000`. Erst diese Bestätigung
beweist etwas — nicht schon der Verbindungsaufbau.

## Eine Association, mehrere Objekte

```
$ storescu -v -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242 \
           instance-0001.dcm instance-0002.dcm instance-0003.dcm
I: Requesting Association
I: Association Accepted
I: Sending file: instance-0001.dcm
I: Sending Store Request: MsgID 1, (CT)
I: Received Store Response (Status: 0x0000 - Success)
I: Sending file: instance-0002.dcm
I: Sending Store Request: MsgID 2, (CT)
I: Received Store Response (Status: 0x0000 - Success)
I: Sending file: instance-0003.dcm
I: Sending Store Request: MsgID 3, (CT)
I: Received Store Response (Status: 0x0000 - Success)
I: Releasing Association
```
**Was du daran abliest:** Nur **eine** Association (ein
`Requesting Association`/`Releasing Association`-Paar), aber drei
eigenständige `MsgID`s mit je eigener Bestätigung. Ein
Store-Vorgang für eine ganze Serie ist keine Aneinanderreihung
einzelner Verbindungen — es ist eine Verbindung mit vielen Anfragen
darin.

```
$ storescu -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242 daten/ct-thorax-60/
$ echo $?
0
```
**Was du daran abliest:** Ein ganzer Ordner mit 60 Dateien lässt sich
genauso mit einem einzigen Aufruf senden — `storescu` iteriert selbst
über alle passenden Dateien. Ohne `-v` bleibt es dabei still, wie bei
jedem anderen DCMTK-Werkzeug (Lektion 1.0): Der Exitcode ist die
einzige Bestätigung.

## Auf der Leitung: ein Objekt in einer Association

```
$ tshark -i lo -f "tcp port 4242" -Y dicom
4   0.004977   127.0.0.1 → 127.0.0.1   DICOM 16195 A-ASSOCIATE request MEINE-WS --> ORTHANC
6   0.006142   127.0.0.1 → 127.0.0.1   DICOM 3947  A-ASSOCIATE accept  MEINE-WS <-- ORTHANC
8   0.012538   127.0.0.1 → 127.0.0.1   DICOM 236   P-DATA, C-STORE-RQ ID=1
10  0.053308   127.0.0.1 → 127.0.0.1   DICOM 572   P-DATA, CT Image Storage
13  0.053690   127.0.0.1 → 127.0.0.1   DICOM 224   P-DATA, C-STORE-RSP ID=1 (Success)
15  0.055769   127.0.0.1 → 127.0.0.1   DICOM 76    A-RELEASE request
16  0.055819   127.0.0.1 → 127.0.0.1   DICOM 76    A-RELEASE response
```
**Was du daran abliest:** Wiresharks Dissector erkennt sogar den
Objekttyp direkt im Datenpaket („P-DATA, CT Image Storage") — das
eigentliche Objekt reist als eigenes `P-DATA`-Paket getrennt von der
`C-STORE-RQ`-Anfrage selbst.

## Selbst zum Empfänger werden

`storescp` ist ein eigenständiges Programm, kein „Empfangsmodus" von
`storescu` — beide Rollen laufen unabhängig voneinander:

```
$ mkdir eingang
$ storescp -v -aet MEIN-EMPFANG -od ./eingang 11112 > eingang.log 2>&1 &
$ storescu -v -aet MEINE-WS -aec MEIN-EMPFANG 127.0.0.1 11112 instance-0001.dcm
I: Requesting Association
I: Association Accepted
I: Sending file: instance-0001.dcm
I: Sending Store Request: MsgID 1, (CT)
I: Received Store Response (Status: 0x0000 - Success)
I: Releasing Association
```
**Was du daran abliest:** Aus Sicht des Senders ändert sich nichts —
derselbe Ablauf wie gegen Orthanc, nur ein anderer Called AE Title und
Port.

```
$ cat eingang.log
I: Accepting Association
I: Received Store Request
I: Storing DICOM file: CT.1.2.826.0.1.3680043.8.498.44031926897584655170357565425793597800
I: Association Released

$ dcmdump +P PatientName +P Modality eingang/CT.1.2.826.0.1.3680043.8.498.44031926897584655170357565425793597800
(0010,0010) PN [MUSTER^ERIKA]                           #  12, 1 PatientName
(0008,0060) CS [CT]                                     #   2, 1 Modality
```
**Was du daran abliest:** Der Empfänger benennt die Datei nach ihrer
SOP Instance UID, nicht nach dem Originaldateinamen — die Bestätigung
über den erfolgreichen Empfang steht im Log, aber erst `dcmdump` auf
der tatsächlich angekommenen Datei beweist, dass der Inhalt stimmt.

## Was der Status im Detail bedeutet

`0x0000` ist nur einer von drei möglichen Ergebnistypen. Ein Archiv
kann ein Objekt auch mit einem *Warning*-Status annehmen und dabei
etwas am Objekt ändern — etwa wenn Patientendaten aktiv gegen den
eigenen Bestand überschrieben werden ({{term:coercion}}, Status
`0xB000`, siehe Lektion 4.6). Ein *Failure*-Status lehnt das Objekt
dagegen vollständig ab, meist wegen einer nicht akzeptierten
Presentation Context (Lektion 4.2/4.3) — beides sind reale, im
Standard definierte Zustände, keine Fehlfunktion des Diensts.

## Im Alltag

| Frage | Womit prüfen |
|---|---|
| Kam ein einzelnes Objekt an? | `storescu -v`, Status in der Store Response |
| Kam die ganze Serie an? | Anzahl der `MsgID`s in einer Association, oder Bestand am Archiv prüfen |
| Sendet ein Gerät überhaupt etwas? | Eigener `storescp` als Gegenprobe |

## Stolperfallen

- **Eine Association pro Objekt annehmen.** Mehrere Dateien in einem
  `storescu`-Aufruf laufen über eine einzige Association mit mehreren
  `MsgID`s — nicht über mehrere Verbindungen.
- **Stille mit Fehlschlag verwechseln.** DCMTK-Werkzeuge schweigen bei
  Erfolg — der Exitcode, nicht die Ausgabe, ist die verlässliche
  Quelle.
- **Einen Warning-Status für einen Erfolg ohne Nebenwirkung halten.**
  `0xB000` heißt „angenommen, aber verändert" — nicht „ganz normal
  angekommen".

## Selbstcheck

1. Ein `storescu`-Aufruf mit drei Dateien zeigt nur ein
   `Requesting Association`. Wie viele `MsgID`s erwartest du trotzdem?
2. Warum benennt `storescp` empfangene Dateien nach der SOP Instance
   UID statt nach dem Originaldateinamen?
3. Ein C-STORE liefert Status `0xB000` statt `0x0000`. Ist das Objekt
   angekommen?
