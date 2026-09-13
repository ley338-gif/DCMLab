---
title: "Pixeldaten, Photometric Interpretation, Bits Allocated"
teaser: Dieselben Bytes werden zu einem völlig anderen Bild, je nachdem, wie man sie liest.
objectives:
  - Bits Allocated/Stored/High und Pixel Representation auseinanderhalten
  - Photometric Interpretation als Anleitung zum Interpretieren der Bytes verstehen
  - Ein falsch dargestelltes Bild auf eine falsche Pixel-Interpretation zurückführen
---

## Ein Foto, das aussieht wie ein Röntgenbild

Jemand wandelt ein gewöhnliches Foto in ein DICOM-Objekt um, um es in
ein System einzuspeisen, das nur DICOM annimmt. Das Ergebnis lässt
sich öffnen — aber die Farben stimmen nicht, oder ein Viewer zeigt nur
Grauwerte, wo Farbe sein sollte.

Der Grund liegt nie in den Bytes selbst. Er liegt darin, dass ein paar
kleine Tags im Image Pixel Module genau festlegen, *wie* diese Bytes
zu lesen sind — allen voran die {{term:photometric-interpretation}} —
und niemand hat sie beim Umwandeln richtig gesetzt.

## Ein CT-Bild und ein umgewandeltes Foto, nebeneinander

Aus Lektion 3.1 kennst du das Image Pixel Module eines echten CT-Bilds:

```
$ dcmdump +P SamplesPerPixel +P PhotometricInterpretation +P Rows +P Columns \
          +P BitsAllocated +P BitsStored +P HighBit +P PixelRepresentation +P PixelData \
          daten/ct-thorax-60/instance-0001.dcm
(0028,0002) US 1                                        #   2, 1 SamplesPerPixel
(0028,0004) CS [MONOCHROME2]                            #  12, 1 PhotometricInterpretation
(0028,0010) US 4                                        #   2, 1 Rows
(0028,0011) US 4                                        #   2, 1 Columns
(0028,0100) US 16                                       #   2, 1 BitsAllocated
(0028,0101) US 16                                       #   2, 1 BitsStored
(0028,0102) US 15                                       #   2, 1 HighBit
(0028,0103) US 0                                        #   2, 1 PixelRepresentation
(7fe0,0010) OW 0000\0000\0000\0000\0000\0000\0000\0000\0000\0000\0000\0000\0000... #  32, 1 PixelData
```
**Was du daran abliest:** Ein Grauwertbild — `SamplesPerPixel 1`,
`MONOCHROME2`. `BitsAllocated 16` heißt, jeder Pixel belegt zwei Byte;
`BitsStored 16` und `HighBit 15` sagen, dass wirklich alle 16 Bit
genutzt werden (bei manchen Modalitäten ist `BitsStored` kleiner als
`BitsAllocated`, weil z. B. nur 12 Bit echte Messwerte tragen).
`PixelRepresentation 0` heißt unsigned — negative Werte sind hier
nicht vorgesehen. Die Testdaten der Spielwiese sind bewusst minimal
(4×4 Pixel, alle Werte 0) — die Struktur ist trotzdem real.

Jetzt derselbe Blick auf ein Objekt, das aus einem gewöhnlichen Bild
entstanden ist:

```
$ img2dcm -i BMP test.bmp test.dcm
$ dcmdump +P SamplesPerPixel +P PhotometricInterpretation +P PlanarConfiguration \
          +P BitsAllocated +P BitsStored +P HighBit +P PixelRepresentation +P PixelData \
          test.dcm
(0028,0002) US 3                                        #   2, 1 SamplesPerPixel
(0028,0004) CS [RGB]                                    #   4, 1 PhotometricInterpretation
(0028,0006) US 0                                        #   2, 1 PlanarConfiguration
(0028,0100) US 8                                        #   2, 1 BitsAllocated
(0028,0101) US 8                                        #   2, 1 BitsStored
(0028,0102) US 7                                        #   2, 1 HighBit
(0028,0103) US 0                                        #   2, 1 PixelRepresentation
(7fe0,0010) OW 0a0a\500a\5050\9696\dc96\dcdc\5050\9650\9696\dcdc\0adc\0a0a\9696... #  48, 1 PixelData
```
**Was du daran abliest:** `SamplesPerPixel 3` und `PhotometricInterpretation
RGB` heißen: drei Werte pro Pixel, kein Grauwert. `PlanarConfiguration 0`
sagt zusätzlich, in welcher Reihenfolge die drei Werte im Byte-Strom
stehen — interleaved (`R,G,B,R,G,B,…`) statt in drei getrennten Ebenen.
Und `BitsAllocated 8` statt `16` heißt: ein Byte pro Kanal, nicht zwei.
Die ersten Bytes der Pixeldaten (`0a`, `0a`, `50`, …) sind dabei keine
zufälligen Werte — `0x0a` = 10, `0x50` = 80: genau die Graustufen, mit
denen das zugrundeliegende Testbild erzeugt wurde. Der Rohbyte-Strom
lässt sich also wörtlich zurückverfolgen, wenn man weiß, wie er
kodiert ist — und genau das sagen einem diese Tags.

## MONOCHROME1 gegen MONOCHROME2

Bei Grauwertbildern gibt es zwei gültige Konventionen, keine davon ist
ein Fehler:

| Wert | Bedeutung |
|---|---|
| `MONOCHROME1` | Der kleinste Pixelwert wird hell/weiß dargestellt, der größte dunkel/schwarz |
| `MONOCHROME2` | Der kleinste Pixelwert wird dunkel/schwarz dargestellt, der größte hell/weiß — der Normalfall bei CT |

```
$ dcmodify -m "PhotometricInterpretation=MONOCHROME1" instance-0002.dcm
$ dcmdump +P PhotometricInterpretation instance-0002.dcm
(0028,0004) CS [MONOCHROME1]                            #  12, 1 PhotometricInterpretation
```
**Was du daran abliest:** Der Tag lässt sich real umschreiben, und
`dcmdump` bestätigt die Änderung — die Datei behauptet jetzt, invertiert
gelesen zu werden. Ob sich das auch sichtbar auswirkt, kann diese
Lektion nicht zeigen: Der Werkzeugkasten der Spielwiese enthält keinen
Bild-Viewer (siehe `content/tools/de.yml`), nur Kommandozeilenwerkzeuge.
Was hier real geprüft werden kann, ist ausschließlich die Tag-Ebene —
ein Viewer, der `PhotometricInterpretation` korrekt beachtet, muss bei
`MONOCHROME1` die Helligkeit umkehren; ob er das tatsächlich tut, ist
eine Eigenschaft des Viewers, nicht der Datei.

## Ein Nebenfund: Type 1 heißt nicht „wird immer geprüft"

Lektion 3.1 hat gezeigt, dass ein fehlendes `StudyInstanceUID` (Type 1)
zur echten Ablehnung führt. `PhotometricInterpretation` ist ebenfalls
Type 1 im Image Pixel Module — trotzdem:

```
$ dcmodify -e '(0028,0004)' instance-0003.dcm
$ storescu -v -aec ORTHANC 127.0.0.1 4242 instance-0003.dcm
I: Requesting Association
I: Association Accepted
I: Sending file: instance-0003.dcm
I: Sending Store Request: MsgID 1, (CT)
I: Received Store Response (Status: 0x0000 - Success)
I: Releasing Association
```
**Was du daran abliest:** Trotz Type 1 wird das Objekt angenommen.
Orthanc prüft beim Speichern offenbar nicht jedes Type-1-Attribut aus
jedem Modul, sondern gezielt die Attribute, die es für seinen eigenen
Index braucht — vor allem die Hierarchie-UIDs aus Lektion 3.1. Die
Type-Angabe aus dem Standard bleibt trotzdem real und verbindlich;
sie beschreibt, was ein *konformes* Objekt enthalten muss, nicht, was
jedes Archiv beim Empfang tatsächlich nachprüft.

## Im Alltag heißt das

| Beobachtung | Wahrscheinliche Ursache |
|---|---|
| Bild wirkt komplett invertiert | `PhotometricInterpretation` MONOCHROME1 statt MONOCHROME2 (oder umgekehrt) |
| Farbbild erscheint als Graustufen oder verzerrt | `SamplesPerPixel`/`PhotometricInterpretation` falsch gesetzt, z. B. RGB als MONOCHROME interpretiert |
| Bild aus Fremdformat sieht komplett falsch aus | Konvertierungswerkzeug hat Pixel-Attribute nicht korrekt aus der Quelle übernommen |

> ### Stolperfallen
>
> **„MONOCHROME1 ist ein Fehler."**
> Ist es nicht — eine gültige, nur seltenere Variante. Der Fehler
> entsteht erst, wenn ein Viewer sie ignoriert oder ein Konvertierungstool
> sie falsch setzt.
>
> **„Type 1 heißt, das Archiv prüft es."**
> Nicht zwangsläufig, wie der Nebenfund oben zeigt. Ein Archiv prüft
> das, was es für seinen eigenen Betrieb braucht — nicht automatisch
> die volle Konformität jedes Moduls.

## Selbstcheck

1. Was bedeutet `BitsAllocated 16` bei `BitsStored 12`?
2. Ein Bild wirkt komplett invertiert, sonst aber unauffällig. Welches
   Attribut prüfst du zuerst?
3. Ein Type-1-Attribut fehlt, das Archiv nimmt das Objekt trotzdem an.
   Widerspricht das dem Standard?
