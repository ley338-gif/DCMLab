---
title: „Worklist ist leer"
teaser: Der meistunterschätzte Dienst — und der, bei dem Filter und Zeitfenster fast immer die Ursache sind.
objectives:
  - Eine leere Worklist von einer fehlgeschlagenen Worklist-Abfrage unterscheiden
  - Die Query-Keys nachvollziehen, die die Modalität tatsächlich schickt
  - Modality-Filter und Zeitfenster als häufigste Ursache prüfen
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
Aufhänger      Ticket aus der Anmeldung: Gerät zeigt keine Patienten
Erklärung      Wer die Worklist beantwortet (RIS oder Broker, nicht das Archiv)
               Was die Modalität als Query-Keys schickt
                 - Modality, Scheduled Station AE Title, Datum/Zeitfenster
               Eine leere Antwort ist eine gültige Antwort, kein Fehler
               Wo die Kette zum HIS/RIS reißt
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - findscu -W mit vollständigem Query-Key-Satz
                 - dieselbe Abfrage mit zu engem Zeitfenster
                 - Mitschnitt: die tatsächlich gesendeten Keys
Im Alltag      Reihenfolge: Gerät, Broker, RIS
Stolperfallen  Worklist im Archiv gesucht, Zeitzone, Station AE Title
Lab            Node fehlt noch
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- Die Spielwiese hat bisher keinen Worklist-Dienst. `wlmscpfs` steht in der Werkzeug-Registry, muss aber im Spielwiesen-Container samt Worklist-Dateien bereitstehen, bevor diese Lektion Beispiele haben kann.
- Ein Worklist-Testdatensatz in `datasets.yml`.

## Selbstcheck

*Folgt mit dem Fließtext.*
