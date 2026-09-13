---
title: "Modality Worklist (MWL): das meistunterschätzte Thema"
teaser: Der Dienst, der jeder Untersuchung ihre Patientendaten mitgibt — bevor irgendein Bild existiert.
objectives:
  - Modality Worklist von einer Study/Series-Abfrage abgrenzen
  - Die typischen Query-Keys einer Modalität benennen
  - Eine leere Worklist-Antwort als gültiges Ergebnis einordnen
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
Aufhänger      Eine Modalität soll den Patientennamen nicht mehr von
               Hand eintippen -- woher kommt er stattdessen?
Erklärung      Modality Worklist ist ein eigenes Informationsmodell
               (PS3.4 Annex K), kein Query-Retrieve-Level wie
               PATIENT/STUDY/SERIES -- eine flache Liste geplanter
               Scheduled Procedure Steps
               Typische Query-Keys, die eine Modalität tatsächlich
               schickt: Modality, Scheduled Station AE Title, Datum
               Wer die Worklist in der Praxis beantwortet: meist ein
               RIS oder ein eigener Broker, nicht das Archiv
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - findscu -W mit vollständigem Query-Key-Satz
                 - dieselbe Abfrage mit zu engem Filter, leeres Ergebnis
                 - ein Auftrag mit vollständigen Scheduled-Procedure-
                   Step-Feldern im Dump
Im Alltag      Welche Query-Keys eine Modalität normalerweise mitschickt
Stolperfallen  Eine leere Worklist für einen Fehler halten
Lab            Node fehlt noch -- "Worklist-Query aus Modalitätssicht
               bauen"
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- Alle Beispiele müssen in der Spielwiese erzeugt und wörtlich übernommen werden.
- Die Spielwiese hat seit P10.20 (ADR 0030) bereits einen echten
  Worklist-Dienst (Orthancs Worklists-Plugin, ein pro Sitzung frisch
  generierter Auftrag aus `content/worklists.yml`) — dieselbe
  Infrastruktur, die Lektion 4.7 nutzt. Diese Lektion braucht kein
  neues Infrastrukturthema, nur eigenen Fließtext mit anderem
  Schwerpunkt (der Dienst selbst, nicht das Troubleshooting-Fehlerbild
  aus 4.7).
- Abgrenzung zu Lektion 4.7 ("Worklist ist leer") — dort steht dieselbe
  Infrastruktur bereits im Troubleshooting-Kontext.

## Selbstcheck

*Folgt mit dem Fließtext.*
