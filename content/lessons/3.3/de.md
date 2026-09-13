---
title: "Window Center/Width, Rescale Slope/Intercept, LUTs"
teaser: Ein CT-Bild ist "schwarz" — nicht weil die Aufnahme fehlgeschlagen ist, sondern weil niemand dem Viewer gesagt hat, wohin er schauen soll.
objectives:
  - Rescale Slope/Intercept als Umrechnung von Rohwert zu Hounsfield Units erklären
  - Window Center/Width als Ausschnitt aus dem Wertebereich verstehen
  - Ein "schwarzes" Bild auf eine falsche oder fehlende Fensterung zurückführen
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
Aufhänger      "Das CT-Bild ist komplett schwarz" -- die Datei ist valide,
               die Pixeldaten sind da, trotzdem sieht man nichts
Erklärung      Zwei getrennte Umrechnungsschritte: RescaleSlope/Intercept
               (Rohwert -> Hounsfield Units, geräteunabhängig) und
               WindowCenter/WindowWidth (HU-Bereich -> das, was der
               Viewer tatsächlich zeigt); VOI LUT als Alternative zu
               Center/Width
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - dcmdump der realen Rescale- und Window-Attribute eines
                   CT-Bilds aus dem Testdatensatz
                 - Rechnung von Rohwert zu HU zu Fensterausschnitt an
                   einem echten Pixelwert nachvollziehen
Im Alltag      Warum derselbe Datensatz in zwei Viewern unterschiedlich
               aussieht, ohne dass eine der beiden Darstellungen falsch ist
Stolperfallen  Eine fehlende/falsche Fensterung für einen Bildfehler
               halten, statt für eine Darstellungsfrage
Lab            Node fehlt noch -- "Warum das Bild schwarz ist"
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- Alle Beispiele müssen in der Spielwiese erzeugt und wörtlich übernommen werden.
- Zu prüfen: Trägt `ct-thorax-60` reale, sinnvolle
  RescaleSlope/RescaleIntercept- und WindowCenter/WindowWidth-Werte, oder
  müssen diese für ein aussagekräftiges Beispiel im Datensatz-Generator
  (`datasets/build/generate.py`) erst ergänzt werden?
- Ohne Viewer im aktuellen Werkzeugkasten (siehe `content/tools/de.yml`)
  bleibt offen, wie der "schwarz"-Effekt greifbar gemacht wird — rein
  über die Rechnung an den Tag-Werten, oder wird dafür ein Viewer nötig?

## Selbstcheck

*Folgt mit dem Fließtext.*
