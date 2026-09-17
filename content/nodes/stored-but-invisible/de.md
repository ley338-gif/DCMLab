---
title: "Gespeichert, aber unsichtbar"
scenario_title: "C-STORE Success, Archivtreffer – trotzdem kein neues Bild"
---

## Briefing

Der Sender meldet Erfolg, und das Archiv kann die Study finden. Im Viewer
erscheint trotzdem kein zusätzliches Bild. Entscheide, welcher Beleg als
Nächstes die Fehlerklasse trennt.

## Hints

### h1

Wenn die Study im Archiv auffindbar ist, suche nicht mehr zuerst im TCP-Weg.

### h2

Nicht jede DICOM Instance enthält Pixel. Prüfe die SOP Class.

## Write-up

Die richtige Reihenfolge trennt Storage, Index und Darstellung. Nachdem der
Archivbestand belegt war, zeigte die Objektart `SR`, dass ein normaler
Bildstapel gar nicht zu erwarten war.

**Was du mitnimmst:** „Nicht sichtbar“ ist kein eindeutiger Beweis für
„nicht gespeichert“.

<!-- kein-beispiel -->
```
Interne Archivsuche, Beispiel:
  StudyInstanceUID  1.2.840...9931
  Instances im Archiv: 61
    60x SOPClassUID CTImageStorage
     1x SOPClassUID XRayRadiationDoseSRStorage   Modality SR
```

Die Study zählt 61 Instances — 60 Bilder und ein SR. Der Viewer bekommt
genau ein Objekt zu wenig zu sehen, das er als Bild darstellen könnte,
weil es keins ist.
