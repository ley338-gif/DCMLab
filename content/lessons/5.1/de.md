---
title: "IHE-Profile: SWF, PIR, XDS-I — was der Name verspricht"
teaser: "Drei Buchstabenkürzel, die in jeder RIS/PACS-Ausschreibung auftauchen — und was sie tatsächlich regeln."
objectives:
  - "Kannst benennen, welches Problem Scheduled Workflow (SWF) löst und welche Akteure beteiligt sind"
  - "Kannst Patient Information Reconciliation (PIR) von SWF abgrenzen"
  - "Kannst einordnen, wofür XDS-I steht und warum es ein anderes Einsatzgebiet als SWF/PIR hat"
---

## Status dieser Lektion

**Entwurf — Gerüst aus Track-5-Scaffolding (P10.46).** Diese Lektion hat noch keinen
Fließtext. Die Gliederung unten ist der geplante Aufbau; die eigentliche
Ausarbeitung erfolgt in einer eigenen PR pro Lektion (siehe `docs/content-todo.md`).

## Geplante Gliederung

<!-- kein-beispiel -->
```
1. Warum IHE? (Standards allein reichen nicht — Interoperabilität ist mehr
   als "kann DICOM sprechen")
2. Scheduled Workflow (SWF): Order → Worklist → Modality → Storage → Reporting
   - welche Akteure (Order Placer, Order Filler, ADT, Image Manager/Archive,
     Acquisition Modality, ...)
   - welche Transaktionen (grob, ohne RAD-TF-Tiefe)
3. Patient Information Reconciliation (PIR): was passiert bei Notfall-
   Patienten ohne vorherige Registrierung, und wie wird das nachträglich
   korrigiert
4. Cross-Enterprise Document Sharing for Imaging (XDS-I): Registry/Repository-
   Modell, Einsatz einrichtungsübergreifend statt innerhalb einer Klinik
5. Abgrenzung: SWF/PIR sind "innerhalb einer Klinik", XDS-I ist "zwischen
   Einrichtungen" — beide lösen unterschiedliche Probleme
```

## Was zum Schreiben noch fehlt

- **Kein reales Hands-on-Beispiel geplant.** IHE-Profile beschreiben
  Akteure/Transaktionen auf Integrationsebene (RIS↔PACS↔Modalität↔Archiv);
  das ist mit der vorhandenen Toolbox (Orthanc + DCMTK-Kommandozeilentools)
  nicht sinnvoll nachstellbar, ohne ein RIS/ADT-System zu simulieren, das es
  im Projekt nicht gibt. Diese Lektion bleibt daher bewusst
  Konzept/Einordnung, `sandbox.required: false`, kein `lab.node`.
- Zu prüfen beim Ausschreiben: ob sich wenigstens *ein* Aspekt (z. B. dass ein
  MWL-Query laut DICOM-Standard bereits Teil von SWF ist, siehe Lektion 4.7)
  über einen Verweis auf bereits vorhandene, echte Lektionen/Nodes verknüpfen
  lässt, statt alles rein abstrakt zu behandeln.
- Reale Quelle für die Transaktionsnamen/-nummern: IHE Radiology Technical
  Framework (RAD-TF), Volume 1 — muss beim Ausschreiben zitiert/verlinkt
  werden, nicht aus dem Gedächtnis rekonstruiert.

## Selbstcheck

*(folgt mit dem Fließtext)*
