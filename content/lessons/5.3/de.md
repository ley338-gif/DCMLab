---
title: "Migration und Archivwechsel: Fallstricke"
teaser: "Wenn das alte PACS abgeschaltet wird, entscheidet sich, ob zehn Jahre Bilddaten wirklich mitkommen — oder nur ihre Metadaten."
objectives:
  - "Kannst die typischen Datenverlust-Risiken bei einer PACS-Migration benennen (UID-Kollisionen, verlorene Private Tags, Transfer-Syntax-Konvertierung)"
  - "Kannst erklären, warum eine 1:1-Migration der StudyInstanceUID/SeriesInstanceUID entscheidend für die Konsistenz mit externen Referenzen (Befunde, HL7-Nachrichten) ist"
  - "Kannst eine Migrationsstrategie (Big-Bang vs. schrittweise/parallel) hinsichtlich Risiko einordnen"
---

## Status dieser Lektion

**Entwurf — Gerüst aus Track-5-Scaffolding (P10.46).** Diese Lektion hat noch keinen
Fließtext. Die Gliederung unten ist der geplante Aufbau; die eigentliche
Ausarbeitung erfolgt in einer eigenen PR pro Lektion (siehe `docs/content-todo.md`).

## Geplante Gliederung

<!-- kein-beispiel -->
```
1. Warum Migration riskant ist: gewachsene Datenbestände, uneinheitliche
   Altdaten, externe Systeme referenzieren UIDs des alten Archivs
2. Typische Fallstricke:
   - UID-Neuvergabe durch das neue System (bricht Referenzen aus RIS/Befund)
   - verlustbehaftete Transfer-Syntax-Konvertierung beim Import
   - private/herstellerspezifische Tags, die das neue System nicht kennt
     und stillschweigend verwirft
   - Datumsformate/Zeichensätze bei sehr alten Studien (siehe Lektion 3.6)
3. Strategien: Big-Bang-Cutover vs. paralleler Betrieb/Dual-Feed vs.
   schrittweise Migration nach Fachbereich
4. Was man vor der Migration prüfen sollte (Stichprobe ziehen, echte
   dcmdump-Diffs alt vs. neu, nicht nur Bildanzahl vergleichen)
```

## Was zum Schreiben noch fehlt

- **Kein Hands-on-Beispiel mit der aktuellen Toolbox realistisch machbar** —
  eine echte Migration bräuchte zwei vollständige PACS-Instanzen mit
  unterschiedlichem UID-Vergabeverhalten; das simuliert die Sandbox nicht.
  Denkbar wäre höchstens ein kleiner, ehrlich als exemplarisch markierter
  Baustein (z. B. zwei Orthanc-Instanzen, echter Export/Import via
  `movescu`/`storescu`, echte UID-Diffs via `dcmdump`) — das müsste beim
  Ausschreiben geprüft werden, ist aber kein Ersatz für eine reale
  Migration und entsprechend zu kennzeichnen.
  `sandbox.required: false` vorerst, ggf. beim Ausschreiben revidieren, falls
  sich ein ehrlicher, kleiner Baustein findet.
- Reale Quelle für "was Hersteller/Berater typischerweise als
  Migrationsrisiken nennen" muss recherchiert und zitiert werden, nicht aus
  dem Gedächtnis rekonstruiert.

## Selbstcheck

*(folgt mit dem Fließtext)*
