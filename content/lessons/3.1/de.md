---
title: "IOD und Module: woraus ein CT-Bild besteht"
teaser: Ein DICOM-Objekt ist kein Bild mit ein paar Zusatzfeldern — es ist aus benannten Bausteinen zusammengesetzt, jeder mit eigenen Pflichten.
objectives:
  - Ein Information Object Definition (IOD) als Bausatz aus Modulen erklären
  - Pflicht- von optionalen Attributen unterscheiden
  - Aus einer echten Datei ableiten, welche Module tatsächlich vorhanden sind
---

## „Pflichtattribut fehlt" — bei einem völlig normalen Bild

Ein Sender schickt ein CT-Bild, das in jedem Viewer einwandfrei aussieht.
Das Archiv lehnt es trotzdem ab: fehlendes Pflichtattribut. Die Pixeldaten
sind unangetastet — offensichtlich fehlt etwas, das mit dem Bild selbst
gar nichts zu tun hat.

Um diesen Fehler zu verstehen, hilft ein anderes Bild: Ein DICOM-Objekt ist
kein Foto mit ein paar Notizzetteln dran. Es ist aus benannten Bausteinen
zusammengesetzt, jeder mit eigenen Pflichten — und genau diese Bausteine
heißen Module.

## Ein IOD ist ein Bausatz, kein Blob

Eine {{term:iod}} (Information Object Definition) legt fest, welche
Module ein bestimmter Objekttyp enthalten muss. Ein CT-Bild besteht zum
Beispiel unter anderem aus:

| Modul | Wofür es zuständig ist |
|---|---|
| Patient Module | Wer ist gemeint — `PatientName`, `PatientID` |
| General Study Module | Welche Untersuchung — `StudyInstanceUID`, `StudyDescription` |
| General Series Module | Welche Serie, welches Gerät — `Modality`, `SeriesInstanceUID` |
| Image Pixel Module | Wie die Bytes zu lesen sind — `Rows`, `Columns`, `BitsAllocated`, `PixelData` |
| SOP Common Module | Die Objektidentität selbst — `SOPClassUID`, `SOPInstanceUID` |

Das ist dieselbe Tag-Gruppierung, die schon in Lektion 1.3 auftauchte
(`0010` = Patient, `0020` = Beziehungen, `0028` = Bilddarstellung) — nur
jetzt mit Namen und mit einer Regel dahinter: **Jedes Modul ist Pflicht
oder optional, und jedes Attribut innerhalb eines Moduls hat wiederum
seinen eigenen Pflichtgrad.**

## Ein echtes Objekt modulweise gelesen

```
$ dcmdump daten/ct-thorax-60/instance-0001.dcm
# Dicom-File-Format

# Dicom-Meta-Information-Header
# Used TransferSyntax: Little Endian Explicit
(0002,0000) UL 206                                      #   4, 1 FileMetaInformationGroupLength
(0002,0001) OB 00\01                                    #   2, 1 FileMetaInformationVersion
(0002,0002) UI =CTImageStorage                          #  26, 1 MediaStorageSOPClassUID
(0002,0003) UI [1.2.826.0.1.3680043.8.498.69221557727082983486576164022413902255] #  64, 1 MediaStorageSOPInstanceUID
(0002,0010) UI =LittleEndianExplicit                    #  20, 1 TransferSyntaxUID
(0002,0012) UI [1.2.826.0.1.3680043.8.498.1]            #  28, 1 ImplementationClassUID
(0002,0013) SH [PYDICOM 3.0.2]                          #  14, 1 ImplementationVersionName

# Dicom-Data-Set
# Used TransferSyntax: Little Endian Explicit
(0008,0016) UI =CTImageStorage                          #  26, 1 SOPClassUID
(0008,0018) UI [1.2.826.0.1.3680043.8.498.69221557727082983486576164022413902255] #  64, 1 SOPInstanceUID
(0008,0060) CS [CT]                                     #   2, 1 Modality
(0008,1030) LO [CT Thorax nativ]                        #  16, 1 StudyDescription
(0008,103e) LO [Thorax 1.0 B70f]                        #  16, 1 SeriesDescription
(0010,0010) PN [MUSTER^ERIKA]                           #  12, 1 PatientName
(0010,0020) LO [4711]                                   #   4, 1 PatientID
(0020,000d) UI [1.2.826.0.1.3680043.8.498.83041545019240232146209099453012057536] #  64, 1 StudyInstanceUID
(0020,000e) UI [1.2.826.0.1.3680043.8.498.4375202799912041203610881433074106603] #  64, 1 SeriesInstanceUID
(0020,0011) IS [1]                                      #   2, 1 SeriesNumber
(0020,0013) IS [1]                                      #   2, 1 InstanceNumber
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
**Was du daran abliest:** Jede Zeile lässt sich einem Modul aus der
Tabelle oben zuordnen — `(0010,...)` ist Patient Module,
`(0020,000d)` General Study, `(0028,...)` und `(7fe0,0010)` zusammen
Image Pixel. Der Datensatz ist bewusst minimal (die Testdaten der
Spielwiese sind synthetisch, 4×4 Pixel) — trotzdem stehen alle
Pflichtmodule vollständig da.

## Type 1, Type 2, Type 3 — drei verschiedene Pflichten

Der Standard (PS3.3) unterscheidet für jedes Attribut in einem Modul,
wie verpflichtend es ist:

| Type | Bedeutung |
|---|---|
| **1** | Pflicht, und zwar mit einem echten Wert — leer ist nicht erlaubt |
| **2** | Pflicht, dass das Tag *vorhanden* ist — der Wert darf leer bleiben |
| **3** | Optional — Tag darf komplett fehlen |

`StudyInstanceUID` und `SOPInstanceUID` sind Type 1: Ohne sie weiß kein
Archiv, zu welcher Untersuchung oder zu welchem Objekt die Daten
gehören. `PatientID` dagegen ist Type 2 — ein Archiv erwartet zwar,
dass Patientendaten irgendwie vorgesehen sind, ein leerer oder
fehlender Wert ist aber kein Grund, das Bild abzulehnen.

Das lässt sich real durchspielen — einmal mit einem Type-1-Attribut,
einmal mit einem Type-2-Attribut:

```
$ cp daten/ct-thorax-60/instance-0002.dcm /tmp/no-study-uid.dcm
$ dcmodify -e '(0020,000d)' /tmp/no-study-uid.dcm
$ storescu -v -aec ORTHANC 127.0.0.1 4242 /tmp/no-study-uid.dcm
I: Requesting Association
I: Association Accepted
I: Sending file: /tmp/no-study-uid.dcm
I: Sending Store Request: MsgID 1, (CT)
I: Received Store Response (Status: 0xA700 - Failure)
I: Releasing Association
```
**Was du daran abliest:** `-e '(0020,000d)'` entfernt die
`StudyInstanceUID` komplett aus der Datei. Das Archiv lehnt das Objekt
mit `0xA700 (Failure)` ab — im Orthanc-eigenen Log steht der Grund im
Klartext: „Store has failed because required tags (StudyInstanceUID)
are missing". Genau das ist die Fehlermeldung vom Anfang dieser
Lektion.

```
$ cp daten/ct-thorax-60/instance-0003.dcm /tmp/no-patientid.dcm
$ dcmodify -e '(0010,0020)' /tmp/no-patientid.dcm
$ storescu -v -aec ORTHANC 127.0.0.1 4242 /tmp/no-patientid.dcm
I: Requesting Association
I: Association Accepted
I: Sending file: /tmp/no-patientid.dcm
I: Sending Store Request: MsgID 1, (CT)
I: Received Store Response (Status: 0x0000 - Success)
I: Releasing Association
```
**Was du daran abliest:** Dieselbe Aktion, aber an `PatientID` (Type 2)
statt an `StudyInstanceUID` (Type 1) — und das Archiv nimmt das Objekt
anstandslos an (`0x0000 - Success`). Derselbe Vorgang (Tag komplett
entfernt), zwei völlig unterschiedliche Ergebnisse, je nachdem, welchen
Type das betroffene Attribut hat.

## Im Alltag heißt das

| Beobachtung | Wahrscheinliche Ursache |
|---|---|
| Archiv lehnt ab: „required tags … missing" | Ein Type-1-Attribut fehlt — meistens eine der Hierarchie-UIDs |
| Feld ist im Viewer leer, Objekt wird trotzdem angenommen | Vermutlich ein Type-2- oder Type-3-Attribut ohne Wert |
| Zwei Systeme zeigen unterschiedlich viele Felder zu demselben Bild | Ein System zeigt auch leere Type-2-Felder an, das andere blendet sie aus |

> ### Stolperfallen
>
> **„Fehlt ein Feld, ist die Datei kaputt."**
> Nicht zwangsläufig. Type-2- und Type-3-Attribute dürfen leer sein
> oder ganz fehlen — das ist vom Standard so vorgesehen, kein Defekt.
>
> **„Ein Ablehnungsgrund ist immer eine Format-Ausrede."**
> `0xA700` mit einer echten, benannten Ursache im Log ist eine
> präzise, nachvollziehbare Aussage — kein vages „Fehler beim
> Speichern".

## Selbstcheck

1. Was unterscheidet ein Type-1- von einem Type-2-Attribut?
2. `StudyInstanceUID` fehlt in einem Objekt, `PatientID` ist leer. Bei
   welchem der beiden lehnt ein Archiv typischerweise ab — und warum?
3. Ein Attribut ist im Dump nicht zu sehen. Bedeutet das automatisch,
   dass die Datei fehlerhaft ist?
