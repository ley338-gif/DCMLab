---
title: "Structured Reports, Presentation States, Key Objects"
teaser: Nicht jedes DICOM-Objekt ist ein Bild — manche enthalten nur einen Text oder einen Verweis, und beides zählt trotzdem als vollwertiges Objekt.
objectives:
  - Einen Structured Report von einem Bildobjekt unterscheiden, ohne den Inhalt zu kennen
  - Presentation States und Key Object Selections als Verweise statt als Bilddaten einordnen
  - Aus einem echten SR die enthaltenen Messwerte oder Befundtexte ablesen
---

## Ein „Bild", das niemand fotografiert hat

Eine Study hat laut Archiv drei Objekte, aber nur ein CT-Bild wurde
tatsächlich aufgenommen. Die anderen beiden sehen in der Objektliste
aus wie weitere Bilder — sind aber keine. Verwirrung entsteht, weil
„DICOM-Objekt" nicht dasselbe ist wie „Bild": Ein Objekt kann auch nur
Text, Messwerte oder einen Verweis auf andere Objekte enthalten.

## Ein echter Structured Report

Ein {{term:structured-report}} enthält strukturierten Inhalt statt
Pixeldaten — Text, Messwerte, Codes, jeweils mit eigener Bedeutung. Ein
minimaler, aber vollständig gültiger Befundtext, real erzeugt und ins
Archiv gesendet:

```
$ dcmftest sr-test.dcm
yes: sr-test.dcm
$ dcmdump +P SOPClassUID +P Modality +P ValueType +P ConceptNameCodeSequence \
          +P ContentSequence sr-test.dcm
(0008,0016) UI =BasicTextSRStorage                      #  30, 1 SOPClassUID
(0008,0060) CS [SR]                                     #   2, 1 Modality
(0040,a040) CS [CONTAINER]                              #  10, 1 ValueType
(0040,a040) CS [TEXT]                                   #   4, 1 ValueType
(0040,a043) SQ (Sequence with explicit length #=1)      #  68, 1 ConceptNameCodeSequence
  (fffe,e000) na (Item with explicit length #=3)          #  60, 1 Item
    (0008,0100) SH [18748-4]                                #   8, 1 CodeValue
    (0008,0102) SH [LN]                                     #   2, 1 CodingSchemeDesignator
    (0008,0104) LO [Diagnostic Imaging Report]              #  26, 1 CodeMeaning
  (fffe,e00d) na (ItemDelimitationItem for re-encoding)   #   0, 0 ItemDelimitationItem
(fffe,e0dd) na (SequenceDelimitationItem for re-encod.) #   0, 0 SequenceDelimitationItem
(0040,a043) SQ (Sequence with explicit length #=1)      #  50, 1 ConceptNameCodeSequence
  (fffe,e000) na (Item with explicit length #=3)          #  42, 1 Item
    (0008,0100) SH [121106]                                 #   6, 1 CodeValue
    (0008,0102) SH [DCM]                                    #   4, 1 CodingSchemeDesignator
    (0008,0104) LO [Comment]                                #   8, 1 CodeMeaning
  (fffe,e00d) na (ItemDelimitationItem for re-encoding)   #   0, 0 ItemDelimitationItem
(fffe,e0dd) na (SequenceDelimitationItem for re-encod.) #   0, 0 SequenceDelimitationItem
(0040,a730) SQ (Sequence with explicit length #=1)      # 136, 1 ContentSequence
  (fffe,e000) na (Item with explicit length #=4)          # 128, 1 Item
    (0040,a010) CS [CONTAINS]                               #   8, 1 RelationshipType
    (0040,a040) CS [TEXT]                                   #   4, 1 ValueType
    (0040,a043) SQ (Sequence with explicit length #=1)      #  50, 1 ConceptNameCodeSequence
      (fffe,e000) na (Item with explicit length #=3)          #  42, 1 Item
        (0008,0100) SH [121106]                                 #   6, 1 CodeValue
        (0008,0102) SH [DCM]                                    #   4, 1 CodingSchemeDesignator
        (0008,0104) LO [Comment]                                #   8, 1 CodeMeaning
      (fffe,e00d) na (ItemDelimitationItem for re-encoding)   #   0, 0 ItemDelimitationItem
    (fffe,e0dd) na (SequenceDelimitationItem for re-encod.) #   0, 0 SequenceDelimitationItem
    (0040,a160) UT [Testbefund: unauffaellig.]              #  26, 1 TextValue
  (fffe,e00d) na (ItemDelimitationItem for re-encoding)   #   0, 0 ItemDelimitationItem
(fffe,e0dd) na (SequenceDelimitationItem for re-encod.) #   0, 0 SequenceDelimitationItem
```
**Was du daran abliest:** `SOPClassUID` ist `BasicTextSRStorage` — ein
eigener Objekttyp, kein CT-Bild. `ValueType CONTAINER` auf oberster
Ebene sagt: Dieses Objekt bündelt strukturierten Inhalt. Die erste
`ConceptNameCodeSequence` benennt das ganze Dokument mit einem echten
LOINC-Code (`18748-4` = „Diagnostic Imaging Report"), keine freie
Beschriftung. Die eigentliche Aussage steckt in der `ContentSequence`
— ein einzelnes `TEXT`-Element mit einer eigenen, zweiten
`ConceptNameCodeSequence` (Code `121106` = „Comment", aus dem
DCM-eigenen Codeschema) und dem Befundtext selbst
(`TextValue`). Jede Aussage in einem SR trägt so ihren eigenen Code —
nicht nur das Dokument als Ganzes. Kein `PixelData`-Tag kommt in der
ganzen Datei vor.

## Ein Key Object Selection — ein Verweis, kein Bild

Eine {{term:key-object-selection}} markiert vorhandene Objekte als
relevant, ohne eigene Bilddaten mitzubringen:

```
$ dcmdump +P SOPClassUID +P Modality +P ContentSequence \
          +P CurrentRequestedProcedureEvidenceSequence kos-test.dcm
(0008,0016) UI =KeyObjectSelectionDocumentStorage       #  30, 1 SOPClassUID
(0008,0060) CS [KO]                                     #   2, 1 Modality
(0040,a730) SQ (Sequence with explicit length #=1)      # 164, 1 ContentSequence
  (fffe,e000) na (Item with explicit length #=3)          # 156, 1 Item
    (0008,1199) SQ (Sequence with explicit length #=1)      # 114, 1 ReferencedSOPSequence
      (fffe,e000) na (Item with explicit length #=2)          # 106, 1 Item
        (0008,1150) UI =CTImageStorage                          #  26, 1 ReferencedSOPClassUID
        (0008,1155) UI [1.2.826.0.1.3680043.8.498.36913633280068846071133553065506086347] #  64, 1 ReferencedSOPInstanceUID
      (fffe,e00d) na (ItemDelimitationItem for re-encoding)   #   0, 0 ItemDelimitationItem
    (fffe,e0dd) na (SequenceDelimitationItem for re-encod.) #   0, 0 SequenceDelimitationItem
    (0040,a010) CS [CONTAINS]                               #   8, 1 RelationshipType
    (0040,a040) CS [IMAGE]                                  #   6, 1 ValueType
  (fffe,e00d) na (ItemDelimitationItem for re-encoding)   #   0, 0 ItemDelimitationItem
(fffe,e0dd) na (SequenceDelimitationItem for re-encod.) #   0, 0 SequenceDelimitationItem
(0040,a375) SQ (Sequence with explicit length #=1)      # 298, 1 CurrentRequestedProcedureEvidenceSequence
  (fffe,e000) na (Item with explicit length #=2)          # 290, 1 Item
    (0008,1115) SQ (Sequence with explicit length #=1)      # 206, 1 ReferencedSeriesSequence
      (fffe,e000) na (Item with explicit length #=2)          # 198, 1 Item
        (0008,1199) SQ (Sequence with explicit length #=1)      # 114, 1 ReferencedSOPSequence
          (fffe,e000) na (Item with explicit length #=2)          # 106, 1 Item
            (0008,1150) UI =CTImageStorage                          #  26, 1 ReferencedSOPClassUID
            (0008,1155) UI [1.2.826.0.1.3680043.8.498.36913633280068846071133553065506086347] #  64, 1 ReferencedSOPInstanceUID
          (fffe,e00d) na (ItemDelimitationItem for re-encoding)   #   0, 0 ItemDelimitationItem
        (fffe,e0dd) na (SequenceDelimitationItem for re-encod.) #   0, 0 SequenceDelimitationItem
        (0020,000e) UI [1.2.826.0.1.3680043.8.498.60349370794634398720353941073641276771] #  64, 1 SeriesInstanceUID
      (fffe,e00d) na (ItemDelimitationItem for re-encoding)   #   0, 0 ItemDelimitationItem
    (fffe,e0dd) na (SequenceDelimitationItem for re-encod.) #   0, 0 SequenceDelimitationItem
    (0020,000d) UI [1.2.826.0.1.3680043.8.498.33356080481204505138900682861234621246] #  64, 1 StudyInstanceUID
  (fffe,e00d) na (ItemDelimitationItem for re-encoding)   #   0, 0 ItemDelimitationItem
(fffe,e0dd) na (SequenceDelimitationItem for re-encod.) #   0, 0 SequenceDelimitationItem
```
**Was du daran abliest:** `SOPClassUID` ist wieder ein eigener Typ
(`KeyObjectSelectionDocumentStorage`), kein Bild. Der entscheidende
Unterschied zum SR: Statt eines `TEXT`-Elements enthält die
`ContentSequence` ein `IMAGE`-Element mit einer
`ReferencedSOPSequence` — einem echten Verweis auf die
`SOPInstanceUID` eines bereits im Archiv liegenden CT-Bilds, per
`CurrentRequestedProcedureEvidenceSequence` zusätzlich der ganzen
Study zugeordnet. Das KOS bringt selbst keine Pixel mit; es sagt nur
„dieses vorhandene Bild ist relevant".

## Dieselbe Study, drei völlig verschiedene Objekttypen

```
$ storescu -v -aec ORTHANC 127.0.0.1 4242 instance-0001.dcm
$ storescu -v -aec ORTHANC 127.0.0.1 4242 sr-test.dcm
$ storescu -v -aec ORTHANC 127.0.0.1 4242 kos-test.dcm
$ findscu -v -S -k QueryRetrieveLevel=STUDY -k PatientID=4711 \
          -k NumberOfStudyRelatedInstances -k ModalitiesInStudy \
          -aec ORTHANC 127.0.0.1 4242
I: Find Response: 1 (Pending)
I: (0008,0061) CS [CT\KO\SR]                               #   8, 3 ModalitiesInStudy
I: (0020,1208) IS [3 ]                                      #   2, 1 NumberOfStudyRelatedInstances
I: Received Final Find Response (Success)
```
**Was du daran abliest:** `ModalitiesInStudy CT\KO\SR` — dieselbe
Study enthält jetzt real ein CT-Bild, ein Key Object Selection und
einen Structured Report, `NumberOfStudyRelatedInstances 3` zählt alle
drei gleichberechtigt mit. Genau das erklärt die Verwirrung vom Anfang:
Die Study hat wirklich drei Objekte — nur eines davon ist ein Bild.

## Und Presentation States?

Ein {{term:presentation-state}} (GSPS, Grayscale Softcopy Presentation
State) gehört zur selben Familie: eigener Objekttyp
(`GrayscaleSoftcopyPresentationStateStorage`), eigene `SOPInstanceUID`,
kein `PixelData`. Er referenziert ein vorhandenes Bild wie ein KOS,
speichert aber zusätzlich, *wie* es dargestellt werden soll — Fensterung,
Zoom, eingezeichnete Anmerkungen — ohne die referenzierten Pixel selbst
zu verändern. Ein vollständiges GSPS-Objekt bräuchte weitere Pflichtmodule
(Displayed Area, Graphic Annotation), die für diese Lektion nicht extra
aufgebaut wurden — der reale SOP-Class-Name und die Einordnung als
Verweis-Objekt neben SR und KOS sind trotzdem verbindlich und
verifizierbar.

## Im Alltag heißt das

| Beobachtung | Wahrscheinliche Ursache |
|---|---|
| Study hat mehr Objekte als Bilder | Mindestens ein SR/KOS/GSPS liegt zusätzlich in der Study |
| „Bild" hat keine Pixeldaten | Es ist gar kein Bild — `SOPClassUID` prüfen |
| Ein Objekt „zeigt" ein anderes an | Vermutlich ein KOS (Verweis) oder GSPS (Darstellungsvorschrift) |

> ### Stolperfallen
>
> **„Jedes Objekt in der Study ist ein Bild."**
> Nein — `ModalitiesInStudy` zeigt es: `SR`, `KO` und `PR`/`GSPS` sind
> reale, eigenständige Objekttypen ohne Pixeldaten.
>
> **„Ein KOS/GSPS dupliziert das Bild."**
> Es verweist nur darauf (`ReferencedSOPInstanceUID`) — die Pixeldaten
> existieren weiterhin genau einmal, im ursprünglichen Bildobjekt.

## Selbstcheck

1. Woran erkennst du in einem `dcmdump`, dass ein Objekt kein Bild ist,
   ohne die `Modality` anzusehen?
2. Was steht in der `ContentSequence` eines KOS anstelle eines
   `TEXT`-Elements, und was bewirkt das?
3. Eine Study hat laut Archiv drei Objekte, aber nur eine echte
   Aufnahme wurde gemacht. Ist das ein Fehler?
