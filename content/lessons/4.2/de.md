---
title: „Verbindung steht, aber nichts kommt an"
teaser: Das C-ECHO ist grün, die Bilder bleiben trotzdem liegen — die Entscheidung fällt im Presentation Context.
objectives:
  - Ein Association-Log bis auf die Ebene der Presentation Contexts lesen
  - Eine abgelehnte Association von einem abgelehnten Presentation Context unterscheiden
  - Erkennen, wann schlicht keine gemeinsame Transfer Syntax vorliegt
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
Aufhänger      Ticket: „C-ECHO grün, Bilder kommen nicht an"
Erklärung      Was beim Verbindungsaufbau je Objekttyp ausgehandelt wird
               Abstract Syntax und die angebotenen Transfer Syntaxes
               Result-Codes eines Presentation Context
               Warum ein abgelehnter Context nicht die Association kippt
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - storescu -d: Proposed und Accepted nebeneinander
                 - Empfänger, der die Transfer Syntax nicht kann
                 - dcmdump: in welcher Transfer Syntax die Datei vorliegt
                 - Mitschnitt derselben Aushandlung
Im Alltag      Reihenfolge der Prüfschritte nach grünem C-ECHO
Stolperfallen  „C-ECHO grün heißt sendefähig", Kompression beim Empfänger
Lab            Node fehlt noch
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- `storescu -d`-Ausgaben aus der Spielwiese für Erfolgs- und Fehlerfall.
- Ein Empfänger, der eine Transfer Syntax gezielt ablehnt — dafür muss die Spielwiese das konfigurierbar machen.
- Abgrenzung zu Lektion 1.8: dort wird die Aushandlung erklärt, hier wird sie diagnostisch gelesen.

## Selbstcheck

*Folgt mit dem Fließtext.*
