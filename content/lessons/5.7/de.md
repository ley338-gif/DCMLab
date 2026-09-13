---
title: "Monitoring und Betriebsführung: was man messen sollte"
teaser: "Ein PACS, das erst auffällt, wenn der Radiologe sich beschwert, wird schon zu spät überwacht."
objectives:
  - "Kannst mindestens drei betrieblich relevante Kennzahlen für ein PACS/Archiv-System benennen (Speicherauslastung, Queue-Tiefe, Association-Fehlerrate)"
  - "Kannst erklären, warum ein abgelehntes C-STORE (Statuscode ungleich Success) ein Monitoring-relevantes Ereignis ist, nicht nur ein Log-Eintrag"
  - "Kannst einordnen, welche der in dieser Lektion genannten Kennzahlen sich real aus Orthancs eigenen Schnittstellen (REST-API/Logs) gewinnen lassen"
---

## Status dieser Lektion

**Entwurf — Gerüst aus Track-5-Scaffolding (P10.46).** Diese Lektion hat noch keinen
Fließtext. Die Gliederung unten ist der geplante Aufbau; die eigentliche
Ausarbeitung erfolgt in einer eigenen PR pro Lektion (siehe `docs/content-todo.md`).

## Geplante Gliederung

<!-- kein-beispiel -->
```
1. Warum "läuft" nicht dasselbe ist wie "funktioniert richtig" — stille
   Fehler (abgelehnte Speicherungen, volle Queues) fallen ohne Monitoring
   erst spät auf
2. Kennzahlen-Kategorien: Kapazität (Speicherauslastung, Wachstumsrate),
   Durchsatz (Studien/Tag, offene Worklist-Einträge), Fehlerraten
   (abgelehnte Assoziationen, C-STORE-Failures), Latenz (Zeit bis Bild
   verfügbar)
3. Konkret an Orthanc zeigen: welche dieser Kennzahlen sind real über die
   REST-API (`/statistics`, `/changes`) oder Logs abrufbar — echte Beispiele,
   keine erfundenen Werte
4. Unterschied zwischen technischem Monitoring (Server lebt) und fachlichem
   Monitoring (Workflow funktioniert) — beides nötig, beides unterschiedlich
```

## Was zum Schreiben noch fehlt

- **Möglicher echter Hands-on-Baustein, muss beim Ausschreiben geprüft
  werden**: Orthancs REST-API (bereits durch Lektion 2.8 als DICOMweb/`curl`-
  Tool im Projekt vorhanden) bietet reale Endpunkte wie `/statistics` und
  `/changes`, die sich in der Sandbox real abfragen lassen. Falls sich das
  trägt, wird `sandbox.required` auf `true` korrigiert und `curl` erneut
  als Tool deklariert — für das Scaffolding bleibt es vorsichtig bei
  `false`, bis geprüft ist, ob die Ausgabe für Betriebs-Monitoring
  aussagekräftig genug ist (die Sandbox hat naturgemäß keine echte
  Produktionslast).
- Reale Quelle für "was in der Praxis üblicherweise gemessen wird" (z. B.
  aus Betriebshandbüchern oder Herstellerdokumentation) muss recherchiert
  werden, nicht aus Erfahrungswissen ohne Beleg behauptet werden.

## Selbstcheck

*(folgt mit dem Fließtext)*
