---
title: "Pixeldaten, Photometric Interpretation, Bits Allocated"
teaser: Dieselben Bytes werden zu einem völlig anderen Bild, je nachdem, wie man sie liest.
objectives:
  - Bits Allocated/Stored/High und Pixel Representation auseinanderhalten
  - Photometric Interpretation als Anleitung zum Interpretieren der Bytes verstehen
  - Ein falsch dargestelltes Bild auf eine falsche Pixel-Interpretation zurückführen
---

## Status dieser Lektion

Gerüst (`status: draft`). Metadaten, Lernziele und die geplante Gliederung
stehen — Fließtext, Beispiele und Lab fehlen noch.

Der Fachtext wird bewusst nicht vorab erfunden: Abschnitt 13 des Auftrags
verbietet erfundene Prosa und erfundene Werkzeugausgaben. Jede Ausgabe in
dieser Lektion muss in der Spielwiese erzeugt und wörtlich übernommen werden.

## Geplante Gliederung

<!-- kein-beispiel -->
```
Aufhänger      Ein Bild sieht bei einem Viewer invertiert oder komplett
               falsch eingefärbt aus, obwohl die Datei valide ist
Erklärung      Die Pixel-Attribute als Bedienungsanleitung fürs Rohbyte:
               BitsAllocated/BitsStored/HighBit, PixelRepresentation
               (signed/unsigned), PhotometricInterpretation
               (MONOCHROME1/2, RGB, ...), SamplesPerPixel
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - dcmdump der Pixel-Attribute eines echten CT-Bilds
                 - MONOCHROME1 vs. MONOCHROME2 an einem echten Objekt
                   zeigen (dcmodify + Kontrolle der Darstellung, falls in
                   der Spielwiese möglich)
Im Alltag      Woran man ein Photometric-Interpretation-Problem von einem
               echten Bilddefekt unterscheidet
Stolperfallen  MONOCHROME1 für einen Fehler halten, statt für eine
               gültige, nur seltenere Variante
Lab            Node fehlt noch -- "Falsch dargestelltes Bild reparieren"
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- Alle Beispiele müssen in der Spielwiese erzeugt und wörtlich übernommen werden.
- Zu prüfen: Lässt sich MONOCHROME1/2 mit `dcmodify` an einem echten
  Testobjekt umschreiben, und lässt sich der Effekt in der Spielwiese
  überhaupt sichtbar machen (kein Viewer im aktuellen Werkzeugkasten,
  siehe `content/tools/de.yml`) — oder bleibt es bei der reinen
  Tag-Ebene ohne visuelle Bestätigung?
- `img2dcm` ist bereits als Werkzeug registriert (Lektion 3.2 laut
  `content/tools/de.yml`) — zu prüfen, ob es für diese Lektion gebraucht
  wird oder erst für einen späteren Abschnitt.

## Selbstcheck

*Folgt mit dem Fließtext.*
