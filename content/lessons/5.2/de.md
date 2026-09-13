---
title: "Conformance Statements lesen und daraus Aussagen ableiten"
teaser: "Das PDF, das jeder Hersteller mitliefert und kaum jemand wirklich liest — dabei steht dort, was ein Gerät wirklich kann."
objectives:
  - "Kannst die Pflichtabschnitte eines DICOM Conformance Statement benennen (unterstützte SOP-Klassen, Transfer-Syntaxen, Rollen)"
  - "Kannst aus einem konkreten Conformance Statement ableiten, ob zwei Geräte miteinander kommunizieren können"
  - "Kannst benennen, welche Aussagen ein Conformance Statement NICHT trifft (z. B. Performance, tatsächliches Verhalten bei Kantenfällen)"
---

## Status dieser Lektion

**Entwurf — Gerüst aus Track-5-Scaffolding (P10.46).** Diese Lektion hat noch keinen
Fließtext. Die Gliederung unten ist der geplante Aufbau; die eigentliche
Ausarbeitung erfolgt in einer eigenen PR pro Lektion (siehe `docs/content-todo.md`).

## Geplante Gliederung

<!-- kein-beispiel -->
```
1. Warum Conformance Statements existieren (DICOM ist ein Baukasten, kein
   Plug-and-play-Standard — "unterstützt DICOM" heißt fast nichts)
2. Pflichtstruktur nach DICOM PS3.2: unterstützte SOP-Klassen (SCU/SCP-Rolle),
   Transfer-Syntaxen, Netzwerk-Parameter (AE-Title, Port), Zeichensätze
3. Ein reales (oder realistisch nachgebautes) Beispiel Zeile für Zeile lesen
4. Typischer Kompatibilitäts-Check: Modalität A (SCU, nur Implicit VR Little
   Endian) gegen Archiv B (SCP, erwartet Explicit VR) — wo genau bricht das
5. Grenzen: Conformance Statement sagt nichts über Performance, Robustheit
   bei kaputten Daten oder tatsächliches Feldverhalten
```

## Was zum Schreiben noch fehlt

- **Kein Hands-on-Beispiel mit der Toolbox geplant** — Conformance Statements
  sind Herstellerdokumente (PDF/Text), kein ausführbares Artefakt. Diese
  Lektion bleibt Text-/Analysearbeit: ein reales oder nachgebautes Dokument
  lesen und Schlüsse ziehen, nicht in der Spielwiese ausführen.
  `sandbox.required: false`, kein `lab.node`.
- Zu klären beim Ausschreiben: ob ein echtes, frei verfügbares Conformance
  Statement (z. B. von Orthaus selbst, falls vorhanden, oder ein
  Open-Source-PACS) als Referenzbeispiel zitiert werden darf/kann, oder ob
  ein anonymisiertes, aber strukturell realistisches Beispiel nötig ist —
  echte Herstellerdokumente sind oft urheberrechtlich geschützt und dürfen
  nicht einfach im Content abgedruckt werden.
- Prüfen, ob Orthanc selbst ein Conformance Statement veröffentlicht
  (naheliegend, da im Projekt bereits verwendet) — falls ja, als reales
  Beispiel bevorzugen statt eines erfundenen.

## Selbstcheck

*(folgt mit dem Fließtext)*
