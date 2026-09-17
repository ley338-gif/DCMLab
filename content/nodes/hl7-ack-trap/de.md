---
title: ACK heißt nicht automatisch Erfolg
scenario_title: Grün in der Engine, unverändert im RIS
---

## Briefing

Ein Administrator sieht in der Interface Engine nur „ACK received“ und erklärt
die Schnittstelle für gesund. Im RIS ist die Patientenänderung aber nicht
angekommen.

Deine Aufgabe: Entscheide, ob die Nachricht nur **transportiert** oder auch
**fachlich akzeptiert** wurde.

## Hints

### h1

Lies nicht nur die Statuszeile der Engine. Öffne den Inhalt der Antwort.

### h2

`MSA|AE|...` ist etwas anderes als `MSA|AA|...`.

## Write-up

Das ACK lautet:

```text
MSH|^~\&|RIS|RAD|KIS|HAUS|20260916090500||ACK^A08|ACK8821|P|2.5
MSA|AE|MSG8821|Patient identifier domain unknown
ERR|||PID^3^1|103^Table value not found
```

**Was du daran abliest:** Die Nachricht erreichte das Ziel und das Ziel konnte
antworten. `AE` meldet aber einen Application Error. Der Patient Identifier
wurde wegen einer unbekannten Identifier-Domäne nicht verarbeitet.

Die richtige Schlussfolgerung ist deshalb nicht „Netzwerkproblem“, sondern:
Transport okay, fachliche Verarbeitung fehlerhaft. Als Nächstes prüfst du die
Identifier-Domäne / das Mapping und verarbeitest die Nachricht nach der
Korrektur gezielt erneut.
