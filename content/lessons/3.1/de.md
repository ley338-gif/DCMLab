---
title: "IOD und Module: woraus ein CT-Bild besteht"
teaser: Ein DICOM-Objekt ist kein Bild mit ein paar Zusatzfeldern — es ist aus benannten Bausteinen zusammengesetzt, jeder mit eigenen Pflichten.
objectives:
  - Ein Information Object Definition (IOD) als Bausatz aus Modulen erklären
  - Pflicht- von optionalen Attributen unterscheiden
  - Aus einer echten Datei ableiten, welche Module tatsächlich vorhanden sind
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
Aufhänger      Ein Archiv lehnt ein Bild ab -- "Pflichtattribut fehlt" --
               obwohl die Pixeldaten völlig in Ordnung sind
Erklärung      IOD (Information Object Definition) als Bausatz aus Modulen
               (Patient, General Study, General Series, General Equipment,
               CT Image, Image Pixel, ...); Type 1/2/3 (Pflicht mit Wert /
               Pflicht ohne Wert erlaubt / optional) aus PS3.3
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - ein reales Objekt modulweise mit dcmdump durchgehen
                 - ein Pflichtattribut (Type 1) mit dcmodify entfernen und
                   die reale Reaktion eines Archivs beim Senden zeigen
Im Alltag      Welche Fehlermeldung auf welches fehlende Modul/Attribut
               hindeutet
Stolperfallen  "Fehlt ein Feld, ist die Datei kaputt" -- Type 2/3 sind
               absichtlich nicht immer gefüllt
Lab            Node fehlt noch -- "Pflichtattribute prüfen"
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- Alle Beispiele müssen in der Spielwiese erzeugt und wörtlich übernommen werden.
- Zu prüfen: Nimmt die Spielwiese (Orthanc) ein Objekt mit fehlendem
  Type-1-Pflichtattribut tatsächlich ab, oder lehnt sie es ab — und mit
  welcher realen Fehlermeldung? Bisher nicht verifiziert.
- `sandbox.dataset` ist vorläufig `ct-thorax-60` — zu prüfen, ob das für
  ein modulweises Durchgehen ausreicht oder ein eigenes, absichtlich
  unvollständiges Testobjekt gebraucht wird.

## Selbstcheck

*Folgt mit dem Fließtext.*
