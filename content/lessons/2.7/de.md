---
title: "Storage Commitment — hast du's wirklich?"
teaser: Der Dienst, der aus einem „angekommen" ein „gesichert, du darfst löschen" macht.
objectives:
  - Storage Commitment von einer erfolgreichen C-STORE-Response abgrenzen
  - N-ACTION und N-EVENT-REPORT den beiden Seiten des Diensts zuordnen
  - Eine echte Ablehnung von einem echten Erfolg unterscheiden
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
Aufhänger      Ein Sender möchte seine lokale Kopie löschen -- aber
               woher weiß er, dass das Archiv das Objekt wirklich
               dauerhaft hat?
Erklärung      Ein erfolgreiches C-STORE beweist nur den Moment der
               Übertragung, nichts über dauerhafte Speicherung
               Storage Commitment als eigener Dienst: N-ACTION stößt
               die Prüfung an, N-EVENT-REPORT liefert das Ergebnis --
               typischerweise verzögert, oft über eine eigene
               Verbindung
               Was eine Ablehnung bedeutet: ein konkreter, realer
               Ablehnungsgrund (PS3.4 Annex J), keine vage "Störung"
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - ein Objekt senden, dann Storage Commitment anfragen,
                   Erfolg
                 - dieselbe Anfrage für ein nie gespeichertes Objekt,
                   echte Ablehnung mit Ablehnungsgrund
Im Alltag      Wann Storage Commitment tatsächlich geprüft wird
                 (Löschregeln, Migrationen)
Stolperfallen  Ein erfolgreiches C-STORE mit einer Speichergarantie
               verwechseln
Lab            Node fehlt noch -- "Commitment-Zyklus durchspielen"
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- Alle Beispiele müssen in der Spielwiese erzeugt und wörtlich übernommen werden.
- Orthanc beherrscht Storage Commitment seit P10.21 (ADR 0031) nativ
  über die REST-API — dieselbe Infrastruktur, die Lektion 4.8 nutzt.
  Diese Lektion braucht kein neues Infrastrukturthema, nur eigenen
  Fließtext mit anderem Schwerpunkt (der Dienst selbst, nicht die
  Statuskette aus 4.8).
- Abgrenzung zu Lektion 4.8 ("Bilder da, aber Befund geht nicht raus")
  — dort steht dieselbe Infrastruktur bereits im
  Troubleshooting-Kontext, gemeinsam mit MPPS.

## Selbstcheck

*Folgt mit dem Fließtext.*
