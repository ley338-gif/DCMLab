---
title: "DICOMweb: WADO-RS, QIDO-RS, STOW-RS"
teaser: Dieselben drei Dienste wie bei DIMSE — nur über HTTP statt über eine Association.
objectives:
  - QIDO-RS, WADO-RS und STOW-RS den DIMSE-Diensten C-FIND, C-GET und C-STORE zuordnen
  - Eine Abfrage sowohl per DIMSE als auch per REST stellen
  - Einordnen, wann DICOMweb eine sinnvolle Alternative zu DIMSE ist
---

## Ein Web-Client, keine DIMSE-Bibliothek

Ein Browser-Dashboard soll anzeigen, welche Studien im Archiv liegen —
ganz ohne eine DIMSE-Bibliothek einzubinden, nur mit HTTP-Aufrufen, wie
jeder andere REST-Client auch. Genau dafür gibt es {{term:dicomweb}}:
dieselben drei Grunddienste wie bei DIMSE, aber als HTTP-API. Orthanc
bringt sie über dasselbe Plugin mit, das auch für Storage Commitment
(Lektion 2.7) zuständig ist — hier reicht eine einzige Konfigurationszeile,
um sie zu aktivieren.

## QIDO-RS: dieselbe Suche per HTTP

Ein Objekt liegt bereits im Archiv (per `storescu` gesendet, wie in
Lektion 2.2). Dieselbe Studie lässt sich jetzt auf zwei Wegen abfragen —
einmal per DIMSE:

```
$ findscu -v -S -k QueryRetrieveLevel=STUDY -k StudyInstanceUID \
          -k PatientName -k PatientID -aec ORTHANC 127.0.0.1 4242
I: Requesting Association
I: Association Accepted (Max Send PDV: 16372)
I: Sending Find Request (MsgID 1)
I: Request Identifiers:
I: 
I: # Dicom-Data-Set
I: # Used TransferSyntax: Little Endian Explicit
I: (0008,0052) CS [STUDY]                                  #   6, 1 QueryRetrieveLevel
I: (0010,0010) PN (no value available)                     #   0, 0 PatientName
I: (0010,0020) LO (no value available)                     #   0, 0 PatientID
I: (0020,000d) UI (no value available)                     #   0, 0 StudyInstanceUID
I: 
I: ---------------------------
I: Find Response: 1 (Pending)
I: 
I: # Dicom-Data-Set
I: # Used TransferSyntax: Little Endian Explicit
I: (0008,0005) CS [ISO_IR 100]                             #  10, 1 SpecificCharacterSet
I: (0008,0052) CS [STUDY ]                                 #   6, 1 QueryRetrieveLevel
I: (0008,0054) AE [ORTHANC ]                               #   8, 1 RetrieveAETitle
I: (0010,0010) PN [MUSTER^ERIKA]                           #  12, 1 PatientName
I: (0010,0020) LO [4711]                                   #   4, 1 PatientID
I: (0020,000d) UI [1.2.826.0.1.3680043.8.498.30275588237090600729160344049361121081] #  64, 1 StudyInstanceUID
I: 
I: Received Final Find Response (Success)
I: Releasing Association
```
**Was du daran abliest:** Der klassische Weg per {{term:c-find}} —
eine Association, ein Dataset mit den angefragten Tags als Antwort.

Und einmal per QIDO-RS, dem HTTP-Gegenstück:

```
$ curl -s http://127.0.0.1:8042/dicom-web/studies
[{
	"00080061" : { "Value" : ["CT"], "vr" : "CS" },
	"00081190" : { "Value" : ["http://127.0.0.1:8042/dicom-web/studies/1.2.826.0.1.3680043.8.498.30275588237090600729160344049361121081"], "vr" : "UR" },
	"00100010" : { "Value" : [{"Alphabetic" : "MUSTER^ERIKA"}], "vr" : "PN" },
	"00100020" : { "Value" : ["4711"], "vr" : "LO" },
	"0020000D" : { "Value" : ["1.2.826.0.1.3680043.8.498.30275588237090600729160344049361121081"], "vr" : "UI" },
	"00201206" : { "Value" : [1], "vr" : "IS" },
	"00201208" : { "Value" : [1], "vr" : "IS" }
}]
```
**Was du daran abliest:** Dieselben Werte, dasselbe Tag-System —
`PatientName`, `PatientID` und `StudyInstanceUID` erscheinen in beiden
Antworten identisch, nur die Verpackung unterscheidet sich: DIMSE
schickt ein Dataset über eine Association, QIDO-RS schickt ein
DICOM-JSON-Array über eine gewöhnliche `GET`-Anfrage. Tags bleiben als
achtstellige Hex-Schlüssel (`0020000D` = StudyInstanceUID) erkennbar —
das Tag-System wechselt nicht, nur die Transportform.

## WADO-RS: Bilder per HTTP abrufen

Der Abruf der eigentlichen Bilddaten läuft über einen `GET` mit dem
passenden `Accept`-Header:

```
$ curl -s -D - -o wado-out.bin \
    -H "Accept: multipart/related; type=application/dicom" \
    http://127.0.0.1:8042/dicom-web/studies/1.2.826.0.1.3680043.8.498.30275588237090600729160344049361121081
HTTP/1.1 200 OK
Content-Type: multipart/related; type="application/dicom"; boundary=d94b1b01-fc39-40ee-b3e6-aae0c748a21f-916bb9ef-362f-41f2-93ed-05b4eb5fb
Content-Length: 1073
```
**Was du daran abliest:** Ein `200 OK` mit `Content-Type:
multipart/related` — die Antwort ist kein einzelnes DICOM-Objekt,
sondern ein Multipart-Body, in dem jedes Bild als eigener Teil mit
eigenem `Content-Type: application/dicom` steckt (sichtbar am
`DICM`-Magic-Byte weiter im Body). Das entspricht {{term:c-get}}:
Anfrage und Auslieferung laufen über dieselbe Verbindung, kein
drittes Ziel wie bei C-MOVE (Lektion 2.4) ist beteiligt.

## STOW-RS: Bilder per HTTP hochladen — und eine echte Stolperfalle

Ein naiver Upload-Versuch mit einem einfachen Textkörper scheitert
sofort, mit einer klaren Fehlermeldung:

```
$ curl -s -X POST http://127.0.0.1:8042/dicom-web/studies \
    -H 'Content-Type: multipart/related; type="application/dicom"' \
    --data-binary "kein-echtes-dicom"
{
	"HttpError" : "Unsupported Media Type",
	"HttpStatus" : 415,
	"Message" : "Unsupported media type",
	"Method" : "POST",
	"OrthancError" : "Unsupported media type",
	"OrthancStatus" : 3000,
	"Uri" : "/dicom-web/studies"
}
```
**Was du daran abliest:** `415 Unsupported Media Type` — Orthanc lehnt
den Body ab, weil dem `Content-Type`-Header die `boundary=`-Angabe
fehlt und der Body selbst kein gültiger Multipart-Teil mit echten
DICOM-Bytes ist. STOW-RS verlangt eine präzise
`multipart/related`-Kodierung, kein bloßes „irgendwas hochladen".

Mit einer korrekt aufgebauten Multipart-Anfrage (Boundary im Header
*und* im Body, echte DICOM-Bytes je Teil) gelingt der Upload:

```python
import urllib.request, uuid

boundary = uuid.uuid4().hex
with open("instance-0002.dcm", "rb") as f:
    dicom_bytes = f.read()

body = (
    ("--" + boundary + "\r\n").encode()
    + b"Content-Type: application/dicom\r\n\r\n"
    + dicom_bytes
    + ("\r\n--" + boundary + "--\r\n").encode()
)

req = urllib.request.Request(
    "http://127.0.0.1:8042/dicom-web/studies",
    data=body,
    method="POST",
    headers={"Content-Type": f'multipart/related; type="application/dicom"; boundary={boundary}'},
)
with urllib.request.urlopen(req) as resp:
    print(resp.status)
    print(resp.read().decode())
```
**Was du daran abliest:** Der Body ist von Hand zusammengesetzt —
Boundary-Markierung, ein `Content-Type: application/dicom` je Teil und
die echten Bytes der Datei dazwischen. `curl --data-binary` allein
reicht dafür nicht; deshalb das kurze Skript statt eines einzeiligen
`curl`-Aufrufs.

```
200
{
	"00081190" : { "Value" : ["http://127.0.0.1:8042/dicom-web/studies/1.2.826.0.1.3680043.8.498.30275588237090600729160344049361121081"], "vr" : "UR" },
	"00081199" : { "Value" : [{
		"00081150" : { "Value" : ["1.2.840.10008.5.1.4.1.1.2"], "vr" : "UI" },
		"00081155" : { "Value" : ["1.2.826.0.1.3680043.8.498.89869035316851748654169927487872335616"], "vr" : "UI" },
		"00081190" : { "Value" : ["http://127.0.0.1:8042/dicom-web/studies/1.2.826.0.1.3680043.8.498.30275588237090600729160344049361121081/series/1.2.826.0.1.3680043.8.498.87911439483274612248789645915564619593/instances/1.2.826.0.1.3680043.8.498.89869035316851748654169927487872335616"], "vr" : "UR" }
	}], "vr" : "SQ" }
}
```
**Was du daran abliest:** `ReferencedSOPSequence` (`00081199`) mit
einer echten, neu vergebenen `SOPInstanceUID` — das entspricht dem
gleichnamigen Feld in einer erfolgreichen C-STORE-Bestätigung. STOW-RS
ist damit HTTP-seitig genau das, was C-STORE (Lektion 2.2) über eine
Association ist.

## Dieselbe Ablage, zwei Zugangswege

Das per STOW-RS hochgeladene Objekt liegt in derselben Studie wie das
zuerst per DIMSE gesendete — überprüfbar mit demselben `findscu` von
oben, diesmal auf Bildebene:

```
$ findscu -v -S -k QueryRetrieveLevel=IMAGE \
          -k StudyInstanceUID=1.2.826.0.1.3680043.8.498.30275588237090600729160344049361121081 \
          -k SOPInstanceUID -aec ORTHANC 127.0.0.1 4242
I: Find Response: 1 (Pending)
I: (0008,0018) UI [1.2.826.0.1.3680043.8.498.95131606042269032597537331318307904692] #  64, 1 SOPInstanceUID
I: Find Response: 2 (Pending)
I: (0008,0018) UI [1.2.826.0.1.3680043.8.498.89869035316851748654169927487872335616] #  64, 1 SOPInstanceUID
I: Received Final Find Response (Success)
```
**Was du daran abliest:** Zwei `SOPInstanceUID`s — eine vom
ursprünglichen `storescu`, eine vom STOW-RS-Upload — beide in
derselben C-FIND-Antwort. Orthanc führt intern nur einen einzigen
Index; DIMSE und DICOMweb sind zwei Zugangswege zu genau derselben
Ablage, keine getrennten Systeme.

## Im Alltag

| Frage | Womit prüfen |
|---|---|
| Web-Client ohne DIMSE-Bibliothek anbinden? | QIDO-RS/WADO-RS/STOW-RS statt eigener DIMSE-Implementierung |
| STOW-RS-Upload scheitert ohne erkennbaren Grund? | `Content-Type`-Header auf korrekte `boundary=`-Angabe prüfen |
| Ist ein Objekt wirklich im Archiv? | Per DIMSE (`findscu`) *und* per DICOMweb (`curl`) — beide zeigen denselben Index |

## Stolperfallen

- **DICOMweb für einen Ersatz von DIMSE halten.** Es ist eine zweite
  Zugangsart zu denselben Daten, kein Nachfolgeprotokoll — Modalitäten
  sprechen weiterhin DIMSE.
- **Multipart-Encoding für optional halten.** Ein Body ohne korrekte
  `boundary=`-Angabe scheitert nicht leise, sondern mit `415
  Unsupported Media Type` — das ist eine hilfreiche, keine
  verwirrende Fehlermeldung.
- **Tags für DICOMweb-spezifisch halten.** Die achtstelligen
  Hex-Schlüssel in DICOM-JSON sind dieselben Tags wie in jedem DIMSE-
  oder Datei-Dump — nur anders notiert.

## Selbstcheck

1. Welcher DIMSE-Dienst entspricht QIDO-RS, welcher WADO-RS, welcher
   STOW-RS?
2. Ein STOW-RS-Upload liefert `415 Unsupported Media Type`. Woran
   liegt das typischerweise?
3. Ein Objekt wurde per STOW-RS hochgeladen. Mit welchem DIMSE-Befehl
   lässt sich prüfen, ob es im selben Index gelandet ist wie ein per
   `storescu` gesendetes Objekt?
