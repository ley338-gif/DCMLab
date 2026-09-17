---
title: ACK heißt nicht automatisch Erfolg
scenario_title: Grün in der Engine, unverändert im RIS
---

## Briefing

Ein Administrator sieht in der Interface Engine nur „ACK received“ und erklärt
die Schnittstelle für gesund. Im RIS ist die Patientenänderung aber nicht
angekommen.

Deine Aufgabe: Entscheide, ob die Nachricht nur **transportiert** oder auch
**fachlich akzeptiert** wurde — und nenne als Lösung den MSA-1-Code, der das
belegt (Format `MSA-<Code>`, z. B. `MSA-AA`).

Auf der Workstation steht dir `mllpq <id>` zur Verfügung, um eine Nachricht
samt ihrem aktuellen ACK anzuzeigen.

## Hints

### h1

Lies nicht nur die Statuszeile der Engine. Rufe `mllpq MSG8821` auf und öffne
den Inhalt der Antwort.

### h2

`MSA|AE|...` ist etwas anderes als `MSA|AA|...`.

## Write-up

`mllpq MSG8821` zeigt die Nachricht und ihr aktuelles ACK:

```text
$ mllpq MSG8821
MSH|^~\&|KIS|HAUS|RIS|RAD|20260916090500||ADT^A08|MSG8821|P|2.5
PID|1||4711^^^KLINIK^MR||MUSTER^ERIKA-SOPHIE||19750314|F
PV1|1|O|RAD^ANMELDUNG

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
