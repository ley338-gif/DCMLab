---
title: "Multiframe, Enhanced IODs"
teaser: Ein Objekt kann eine ganze Serie sein, nicht nur ein Bild — mit allem, was das für Zählen und Verarbeiten ändert.
objectives:
  - Klassische Single-Frame-Objekte von Multiframe-/Enhanced-Objekten unterscheiden
  - Erklären, wo bei einem Enhanced-Objekt Attribute stehen, die klassisch pro Datei einmalig sind
  - Eine Serie in klassischer und in Enhanced-Kodierung gegenüberstellen
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
Aufhänger      Eine Serie mit 300 Bildern kommt als eine einzige Datei an
               -- ein Werkzeug, das pro Serie eine Datei erwartet, zählt
               falsch
Erklärung      Klassisch: eine Instance = ein Bild. Multiframe/Enhanced
               IODs: eine Instance kann einen ganzen Stapel enthalten
               (NumberOfFrames, PerFrameFunctionalGroupsSequence,
               SharedFunctionalGroupsSequence) -- Attribute, die klassisch
               pro Datei stehen, stehen bei Enhanced teils einmalig,
               teils pro Frame
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - NumberOfFrames und Functional-Groups-Sequenzen eines
                   echten Enhanced-Objekts per dcmdump zeigen
                 - Vergleich: dieselbe Bildmenge einmal klassisch (viele
                   Dateien), einmal Enhanced (eine Datei) zählen
Im Alltag      Warum eine Dateizahl allein nichts über die Bildzahl
               aussagt, sobald Enhanced-Objekte im Spiel sind
Stolperfallen  "Eine Datei = ein Bild" als Grundannahme in eigenen
               Skripten, die bei Enhanced-Objekten bricht
Lab            Node fehlt noch -- "Klassisch vs. Enhanced vergleichen"
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- Alle Beispiele müssen in der Spielwiese erzeugt und wörtlich übernommen werden.
- **Echter, noch offener Infrastruktur-Punkt** (bereits in
  `docs/content-todo.md` unter „Multiframe-Generator" vermerkt): Der
  Datensatz-Generator (`datasets/build/generate.py`) erzeugt bisher nur
  klassische Single-Frame-CT-Objekte. Für diese Lektion wird
  wahrscheinlich ein echtes Multiframe-/Enhanced-CT-Testobjekt
  gebraucht — noch nicht verifiziert, ob sich das mit `pydicom` ohne
  Weiteres synthetisch erzeugen lässt oder ob dafür ein eigener
  Datensatz-Slug samt Generator-Subcommand nötig ist (wie seinerzeit
  bei `worklist`, siehe ADR 0021/0030).
- `sandbox.dataset: ct-thorax-60` ist deshalb nur ein Platzhalter, keine
  geprüfte Entscheidung.

## Selbstcheck

*Folgt mit dem Fließtext.*
