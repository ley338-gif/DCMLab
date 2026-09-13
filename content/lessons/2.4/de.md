---
title: "C-MOVE vs. C-GET: warum C-MOVE drei Beteiligte hat"
teaser: Zwei Wege, an dieselben Bilder zu kommen — einer davon zieht einen Dritten mit hinein, der andere nicht.
objectives:
  - Die Rollenverteilung bei C-MOVE (SCU, Ziel, Quelle) beschreiben
  - Erklären, warum C-GET ohne eine dritte Association auskommt
  - Begründen, wann welcher der beiden Dienste in der Praxis verwendet wird
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
Aufhänger      Ein Retrieve-Auftrag kommt nie an -- an der Quelle sieht
               alles normal aus
Erklärung      C-MOVE: der SCU fordert an, die Quelle liefert an ein
               DRITTES System (das Move-Ziel) -- eine neue, eigene
               Association, die ihrerseits stehen muss
               C-GET: derselbe Zweck, aber die Objekte kommen über die
               bereits bestehende Association zurück -- kein Dritter
               nötig, dafür SCU und Ziel zwingend dieselbe Instanz
               Warum das für Firewalls/NAT den Unterschied macht
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - ein C-MOVE mit sich selbst als Move-Ziel
                 - ein C-GET zum Vergleich, dieselbe Abfrage
                 - ein C-MOVE, dessen Ziel nicht erreichbar ist
Im Alltag      Wann C-MOVE, wann C-GET, wann eher WADO-RS (2.8)
Stolperfallen  Move-Ziel mit dem anfragenden SCU verwechseln
Lab            Node fehlt noch -- "Retrieve an eine dritte Node"
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- Alle Beispiele müssen in der Spielwiese erzeugt und wörtlich übernommen werden.
- Ein echtes C-MOVE mit einem tatsächlich dritten Ziel (nicht nur „sich
  selbst als Ziel") würde einen zweiten Storage-Endpunkt in der
  Spielwiese brauchen — noch nicht vorhanden, siehe `docs/content-todo.md`.

## Selbstcheck

*Folgt mit dem Fließtext.*
