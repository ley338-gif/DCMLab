---
title: "Multiframe, Enhanced IODs"
teaser: Ein Objekt kann eine ganze Serie sein, nicht nur ein Bild — mit allem, was das für Zählen und Verarbeiten ändert.
objectives:
  - Klassische Single-Frame-Objekte von Multiframe-/Enhanced-Objekten unterscheiden
  - Erklären, wo bei einem Enhanced-Objekt Attribute stehen, die klassisch pro Datei einmalig sind
  - Eine Serie in klassischer und in Enhanced-Kodierung gegenüberstellen
---

## Eine Serie, eine Datei — und ein Skript, das falsch zählt

Ein Auswertungsskript geht durch ein Archivverzeichnis und zählt
Dateien, um die Bildzahl einer Untersuchung zu bestimmen. Bei einer
Serie liegt es plötzlich massiv daneben: Die Study hat laut Archiv 300
Bilder, das Skript zählt drei Dateien.

Das Skript hat nicht falsch gezählt. Es hat nur angenommen, was in
Track 1 noch stimmte: eine Instance ist eine Datei ist ein Bild. Diese
Annahme bricht, sobald ein Objekt mehrere Frames in sich trägt.

## Ein echtes Multiframe-Objekt

Die Testdaten der Spielwiese sind Single-Frame-CT-Bilder — ein echtes
multiframe-Objekt liegt aber bereits im Werkzeugkasten selbst: `pydicom`
bringt zu Testzwecken echte, offen lizenzierte DICOM-Beispielobjekte
mit, komprimiert mit RLE. Einmal kopiert und entpackt:

```
$ cp /usr/local/lib/python3.11/dist-packages/pydicom/data/test_files/SC_rgb_rle_2frame.dcm multiframe-rle.dcm
$ dcmdrle multiframe-rle.dcm multiframe.dcm
$ dcmdump +P NumberOfFrames +P SOPClassUID +P Modality +P Rows +P Columns \
          +P PatientName +P PatientID multiframe.dcm
(0028,0008) IS [2]                                      #   2, 1 NumberOfFrames
(0008,0016) UI =SecondaryCaptureImageStorage            #  26, 1 SOPClassUID
(0008,0060) CS [OT]                                     #   2, 1 Modality
(0028,0010) US 100                                      #   2, 1 Rows
(0028,0011) US 100                                      #   2, 1 Columns
(0010,0010) PN [Lestrade^G]                             #  10, 1 PatientName
(0010,0020) LO [ID1]                                    #   4, 1 PatientID
```
**Was du daran abliest:** `NumberOfFrames 2` heißt: Diese eine Datei
enthält zwei Bilder, nicht eins. `SOPClassUID` ist hier ganz normal
`SecondaryCaptureImageStorage` — schon klassische IODs wie Secondary
Capture erlauben mehrere Frames pro Instance, das ist keine
Enhanced-Besonderheit. Der Unterschied zwischen „klassisch multiframe"
und „Enhanced" liegt woanders, wie der nächste Abschnitt zeigt.

## Ins Archiv senden — und real nachzählen

```
$ storescu -v -aec ORTHANC 127.0.0.1 4242 multiframe.dcm
I: Requesting Association
I: Association Accepted
I: Sending file: multiframe.dcm
I: Sending Store Request (MsgID 1, SC)
I: Received Store Response (Success)
I: Releasing Association
```
**Was du daran abliest:** Das Archiv nimmt die Datei wie jedes andere
Objekt an — für den Übertragungsvorgang ist es eine einzige Instance.

```
$ findscu -v -S -k QueryRetrieveLevel=STUDY -k PatientID=ID1 \
          -k NumberOfStudyRelatedInstances -aec ORTHANC 127.0.0.1 4242
I: Find Response: 1 (Pending)
I: (0020,1208) IS [1]                                     #   2, 1 NumberOfStudyRelatedInstances
I: Received Final Find Response (Success)
```
**Was du daran abliest:** `NumberOfStudyRelatedInstances 1` — das
Archiv zählt genau eine Instance, obwohl real zwei Bilder darin
stecken. Ein Skript, das „Anzahl Instances" mit „Anzahl Bilder"
gleichsetzt, wäre hier bereits falsch — bei einem Enhanced-Objekt mit
300 Frames genauso.

## Was ein {{term:enhanced-iod}} tatsächlich anders macht

Der eigentliche Unterschied zwischen klassisch multiframe und Enhanced
IODs liegt nicht in `NumberOfFrames`, sondern darin, *wo* die
Bildparameter stehen:

| | Klassisch multiframe | Enhanced IOD |
|---|---|---|
| Attribute wie `WindowCenter`, `PixelSpacing` | Einmal für die ganze Datei, gelten für alle Frames gleich | In der **Shared Functional Groups Sequence** (für alle Frames gleich) oder in der **Per-Frame Functional Groups Sequence** (pro Frame einzeln) |
| Geeignet für | Frames, die sich in ihren Parametern nicht unterscheiden | Serien, in denen sich z. B. die Schichtposition von Frame zu Frame ändert |
| Beispiel-SOP-Classes | Secondary Capture, Ultrasound Multi-frame, RT Dose | Enhanced CT/MR/PET Image Storage |

Unser reales Testobjekt bestätigt das:

```
$ dcmdump +P PerFrameFunctionalGroupsSequence +P SharedFunctionalGroupsSequence \
          multiframe.dcm
```
**Was du daran abliest:** Keine Ausgabe — beide Sequenzen fehlen. Das
ist korrekt so: Ein klassisches multiframe-Objekt braucht sie nicht,
weil es ohnehin voraussetzt, dass alle Frames dieselben Parameter
teilen. Ein Enhanced-CT- oder Enhanced-MR-Objekt hätte hier echten
Inhalt — mindestens eine Shared-Gruppe für das, was sich nie ändert
(z. B. `PixelSpacing`), und eine Per-Frame-Gruppe pro Frame für das,
was sich ändert (z. B. die Schichtposition). Ein echtes Enhanced-Objekt
mit gefüllten Functional-Groups-Sequenzen lässt sich mangels
Netzwerkzugriffs in dieser Spielwiese nicht zusätzlich beschaffen — die
realen SOP-Class-UIDs und die Struktur selbst sind trotzdem verbindlich
im Standard definiert und lassen sich am eigenen, bewusst einfacheren
Testobjekt gegenprüfen.

## Im Alltag heißt das

| Beobachtung | Wahrscheinliche Ursache |
|---|---|
| Study hat mehr Bilder als Dateien | Mindestens ein Objekt ist multiframe — `NumberOfFrames` prüfen, nicht nur Dateien zählen |
| Ein Skript zählt „Instances" statt „Frames" | Bei Multiframe-/Enhanced-Objekten ist das nicht dasselbe |
| Eine Serie hat sich verändernde Parameter pro Bild | Wahrscheinlich Enhanced IOD mit Per-Frame Functional Groups |

> ### Stolperfallen
>
> **„Eine Datei ist ein Bild."**
> Nur bei klassischen Single-Frame-Objekten. `NumberOfFrames` prüfen,
> bevor eigene Zähl- oder Exportlogik darauf aufbaut.
>
> **„NumberOfFrames > 1 heißt Enhanced."**
> Nicht zwangsläufig — wie das Testobjekt oben zeigt, ist Multiframe
> schon bei klassischen IODs wie Secondary Capture möglich. Enhanced
> ist eine zusätzliche, eigene Strukturebene (Functional Groups), keine
> reine Frame-Zahl-Frage.

## Selbstcheck

1. Ein Archiv zeigt für eine Study `NumberOfStudyRelatedInstances: 1`.
   Kann trotzdem mehr als ein Bild darin stecken?
2. Was unterscheidet die Shared von der Per-Frame Functional Groups
   Sequence?
3. `NumberOfFrames` eines Objekts ist `50`. Ist das automatisch ein
   Enhanced-IOD-Objekt?
