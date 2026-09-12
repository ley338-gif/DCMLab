---
title: „Studie ist gesplittet / doppelt"
teaser: Eine Untersuchung, zwei Einträge — hier entscheidet eine einzige UID darüber, was der Befunder sieht.
objectives:
  - Split und Dublette als zwei verschiedene Fehlerbilder auseinanderhalten
  - Die Study Instance UID als entscheidendes Merkmal prüfen
  - Typisches Fehlverhalten von Modalitäten benennen, das zum Split führt
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
Aufhänger      Ticket: Untersuchung erscheint zweimal in der Liste
Erklärung      Split: gleiche Untersuchung, verschiedene Study Instance UIDs
               Dublette: gleiche UID, zweimal eingespielt
               Woher eine zweite UID kommt
                 - Untersuchung am Gerät neu begonnen
                 - Nachsendung ohne Worklist-Bezug
                 - manuelle Eingabe statt Worklist
               Warum Namensgleichheit nichts beweist
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - findscu auf Study-Ebene, beide Einträge nebeneinander
                 - dcmdump: die UIDs im Vergleich
                 - dcm2json für den maschinellen Vergleich
Im Alltag      Was man selbst reparieren darf und was das Archiv entscheidet
Stolperfallen  UID-Präfix als Herstellermerkmal fehlgedeutet
Lab            Node fehlt noch
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- Ein Testdatensatz mit zwei Studies, die identisch aussehen und verschiedene Study Instance UIDs tragen. `datasets.yml` braucht dafür einen neuen Eintrag; Lektion 1.4 nennt dieses Lab bereits („Zwei Studies unterscheiden, die gleich aussehen").
- Klärung, wie weit die Lektion Richtung Korrektur geht — Merge und Move gehören zu 4.6.

## Selbstcheck

*Folgt mit dem Fließtext.*
