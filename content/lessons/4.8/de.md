---
title: „Bilder da, aber Befund geht nicht raus"
teaser: Der Transfer hat geklappt, die Statuskette nicht — und genau die entscheidet, ob der Workflow weiterläuft.
objectives:
  - Die Statuskette aus MPPS und Storage Commitment als eigenen Fehlerort begreifen
  - Erkennen, an welchem Glied die Kette reißt
  - Die Rückrichtung als eigene Verbindung prüfen, die eigene Konfiguration braucht
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
Aufhänger      Ticket: Untersuchung bleibt im RIS offen
Erklärung      Die Kette: Worklist -> MPPS In Progress -> Bilder ->
               MPPS Completed -> Storage Commitment
               Wo die Rückmeldung landet (RIS oder Broker, nicht Archiv)
               Storage Commitment: die Antwort kommt verzögert und
               unter Umständen über eine eigene Verbindung
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - Mitschnitt eines vollständigen Ablaufs
                 - Mitschnitt mit fehlender Commitment-Antwort
                 - pynetdicom-Skript als Gegenstelle
Im Alltag      Welches System man bei welchem Symptom zuerst fragt
Stolperfallen  MPPS im PACS-Log gesucht, Rückweg nicht konfiguriert
Lab            Node fehlt noch
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- MPPS und Storage Commitment gibt es in der Spielwiese bisher nicht. Ohne Gegenpart (Orthanc kann beides nicht ohne Weiteres) hat diese Lektion keine echten Ausgaben — entweder ein `pynetdicom`-Gegenstück in die Spielwiese aufnehmen oder die Lektion nach hinten schieben.
- Abgrenzung zu den Lektionen 2.6 und 2.7, die dieselben Dienste erklären.

## Selbstcheck

*Folgt mit dem Fließtext.*
