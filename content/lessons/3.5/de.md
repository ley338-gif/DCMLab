---
title: "Structured Reports, Presentation States, Key Objects"
teaser: Nicht jedes DICOM-Objekt ist ein Bild — manche enthalten nur einen Text oder einen Verweis, und beides zählt trotzdem als vollwertiges Objekt.
objectives:
  - Einen Structured Report von einem Bildobjekt unterscheiden, ohne den Inhalt zu kennen
  - Presentation States und Key Object Selections als Verweise statt als Bilddaten einordnen
  - Aus einem echten SR die enthaltenen Messwerte oder Befundtexte ablesen
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
Aufhänger      Ein Befund taucht im Archiv als eigenes "Bild" auf, obwohl
               niemand fotografiert hat -- Verwirrung, was das eigentlich
               ist
Erklärung      Structured Report (SR): strukturierter Text/Messwerte
               statt Pixel, eigene SOP Classes; Presentation State (GSPS):
               wie ein Bild dargestellt werden soll, ohne die Pixel
               selbst zu verändern; Key Object Selection (KOS): ein
               Verweis auf "diese Bilder sind relevant", ohne eigene
               Pixeldaten
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - dcmdump eines echten SR-Objekts, SOPClassUID und
                   Content-Sequence-Struktur zeigen
                 - Gegenüberstellung: SOPClassUID eines CT-Bilds vs.
                   eines SR vs. (falls erzeugbar) eines KOS
Im Alltag      Warum "die Study hat mehr Objekte als Bilder" kein Fehler
               ist
Stolperfallen  Ein SR/KOS/GSPS für ein fehlerhaftes oder doppeltes Bild
               halten, weil es in der Objektliste auftaucht
Lab            Node fehlt noch -- "Einen SR lesen"
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- Alle Beispiele müssen in der Spielwiese erzeugt und wörtlich übernommen werden.
- **Echter, noch offener Infrastruktur-Punkt:** Der Datensatz-Generator
  erzeugt bisher nur CT-Bildobjekte, kein Structured-Report-, GSPS- oder
  KOS-Objekt. Noch nicht verifiziert, ob sich mit `pydicom` ein
  minimales, gültiges SR-Testobjekt synthetisch bauen lässt (ähnlich dem
  Vorgehen beim Worklist-Subcommand, ADR 0021) oder ob dafür ein neuer
  Datensatz-Slug/Generator-Subcommand nötig wird.
- `sandbox.dataset: ct-thorax-60` ist deshalb nur ein Platzhalter.

## Selbstcheck

*Folgt mit dem Fließtext.*
