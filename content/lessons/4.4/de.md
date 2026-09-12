---
title: „Timeout"
teaser: Timeout ist keine Diagnose, sondern eine Sammelmeldung — sie lässt sich in vier unterscheidbare Ursachen zerlegen.
objectives:
  - Timeout als Symptom von der eigentlichen Ursache trennen
  - Die Schicht eingrenzen: Netzweg, Port, DICOM-Aufbau oder laufender Transfer
  - Idle Timeouts und MTU-Probleme als eigene Ursachenklassen erkennen
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
Aufhänger      Ticket: „Timeout beim Senden"
Erklärung      Vier Stellen, an denen es hängen kann
                 - Netzweg/Firewall: Verbindung kommt nie zustande
                 - falscher Port: 104 gegen 11112
                 - DICOM-Aufbau: Gegenstelle antwortet nicht
                 - laufender Transfer: Idle Timeout, MTU, Pfad-MTU
               Kleine Übertragungen klappen, große nicht — typisches Bild
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - echoscu gegen geschlossenen Port
                 - echoscu gegen offenen Port ohne DICOM-Dienst
                 - Mitschnitt: Retransmits gegen sauberen Abbau
Im Alltag      Eingrenzungsreihenfolge von außen nach innen
Stolperfallen  „Timeout heißt Netzwerk", Ping als Beweis
Lab            Node fehlt noch
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- Entscheidung, ob MTU- und Pfad-MTU-Effekte in der Spielwiese überhaupt erzeugbar sind. Wenn nicht: entweder aus der Lektion nehmen oder als Erfahrungsregel ohne Beispiel kennzeichnen — Letzteres verletzt die Beispielregel und braucht eine bewusste Entscheidung.
- Mitschnitte aus der Spielwiese für die drei erzeugbaren Fälle.

## Selbstcheck

*Folgt mit dem Fließtext.*
