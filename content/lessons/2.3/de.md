---
title: "C-FIND: Query-Level, Matching-Keys, Wildcards"
teaser: Suchen ohne zu übertragen — und die Kunst, mit Bruchstücken statt mit exakten Werten zu fragen.
objectives:
  - Query-Retrieve-Level (PATIENT/STUDY/SERIES/IMAGE) unterscheiden
  - Matching-Keys von Rückgabefeldern trennen
  - Eine Studie mit Wildcards statt exakten Werten finden
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
Aufhänger      Man kennt nur den Nachnamen und ungefähr das Datum --
               nicht die genaue StudyInstanceUID
Erklärung      Query-Retrieve-Level: PATIENT, STUDY, SERIES, IMAGE --
               eine Ebene pro Abfrage, nicht alle gleichzeitig
               Matching-Key vs. Rückgabefeld: -k mit Wert filtert,
               -k ohne Wert fragt nur den Wert ab (bereits in 1.0
               angerissen, hier vertieft)
               Wildcards: * und ? in DICOM-Query-Keys, was sie
               tatsächlich abdecken
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - eine Studie mit exaktem PatientName finden
                 - dieselbe Suche mit Wildcard statt exaktem Namen
                 - eine Suche, die durch falschen Query-Level leer bleibt
Im Alltag      Vom groben zum feinen Query-Level vorgehen
Stolperfallen  Query-Level und Rückgabefelder verwechseln, zu enge
               Wildcards
Lab            Node fehlt noch -- "Studie anhand von Bruchstücken finden"
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- Alle Beispiele müssen in der Spielwiese erzeugt und wörtlich übernommen werden.
- Abgrenzung zu Lektion 1.0 (dort wird `findscu` bereits als Werkzeug
  vorgestellt) und zu Lektion 2.5 (Modality Worklist ist ein eigenes,
  verwandtes, aber getrenntes Informationsmodell).

## Selbstcheck

*Folgt mit dem Fließtext.*
