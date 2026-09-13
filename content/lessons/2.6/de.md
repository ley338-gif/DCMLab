---
title: "MPPS: Status-Rückmeldung der Modalität"
teaser: Die Meldung, die sagt, dass eine Untersuchung stattgefunden hat — unabhängig davon, ob auch ein Bild ankam.
objectives:
  - N-CREATE und N-SET als die beiden MPPS-Nachrichten unterscheiden
  - Den Zustand "IN PROGRESS" von "COMPLETED" abgrenzen
  - Erklären, warum MPPS unabhängig vom Bildtransfer scheitern kann
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
Aufhänger      Bilder sind da, das RIS zeigt die Untersuchung trotzdem
               als "geplant"
Erklärung      MPPS als eigener DIMSE-N-Dienst: N-CREATE meldet "Beginn"
               (Status IN PROGRESS), N-SET meldet "Ende" (Status
               COMPLETED) -- beide auf derselben Assoziation
               Was MPPS NICHT ist: kein Bildtransfer, keine
               Bestätigung, dass ein Objekt ankam (das ist Storage
               Commitment, Lektion 2.7)
               Wer MPPS in der Praxis entgegennimmt: meist RIS/Broker,
               nicht das Archiv (dieselbe Rollenverteilung wie bei der
               Worklist, Lektion 2.5)
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - ein vollständiger N-CREATE/N-SET-Austausch gegen
                   einen echten MPPS-SCP
                 - ein Mitschnitt der Assoziation
Im Alltag      Woran man erkennt, dass MPPS und nicht der Bildtransfer
               das Problem ist
Stolperfallen  MPPS im PACS-Log suchen, wo es nicht beteiligt ist
Lab            Node fehlt noch -- "MPPS-Ablauf mitlesen"
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- Alle Beispiele müssen in der Spielwiese erzeugt und wörtlich übernommen werden.
- Die Spielwiese hat seit P10.21 (ADR 0031) bereits einen echten
  MPPS-SCP (`containers/toolbox/scripts/mppsscp.py`) — dieselbe
  Infrastruktur, die Lektion 4.8 nutzt. Diese Lektion braucht kein
  neues Infrastrukturthema, nur eigenen Fließtext mit anderem
  Schwerpunkt (der Dienst selbst, nicht die Statuskette aus 4.8).
- Abgrenzung zu Lektion 4.8 ("Bilder da, aber Befund geht nicht raus")
  — dort steht dieselbe Infrastruktur bereits im
  Troubleshooting-Kontext, gemeinsam mit Storage Commitment.

## Selbstcheck

*Folgt mit dem Fließtext.*
