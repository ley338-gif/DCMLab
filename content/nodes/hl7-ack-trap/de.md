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

### h3

Ein `AA` bestätigt die Annahme nach ACK-Semantik. Das ist nicht automatisch
dasselbe wie ein bestätigter fachlicher Endzustand im Zielsystem.

## Write-up

Das erste ACK lautet:

```text
MSH|^~\&|RIS|RAD|KIS|HAUS|20260916090500||ACK^A08|ACK8821|P|2.5
MSA|AE|MSG8821|Patient identifier domain unknown
ERR|||PID^3^1|103^Table value not found
```

**Was du daran abliest:** Die Nachricht erreichte das Ziel und das Ziel konnte
antworten. `AE` meldet aber einen Application Error. Der Patient Identifier
wurde wegen einer unbekannten Identifier-Domäne nicht verarbeitet.

Die richtige Schlussfolgerung ist deshalb nicht „Netzwerkproblem“, sondern:
Transport okay, fachliche Verarbeitung fehlerhaft. Die Identifier-Domäne wird
korrigiert und **dieselbe Nachricht** MSG8821 gezielt erneut verarbeitet:

```text
MSH|^~\&|RIS|RAD|KIS|HAUS|20260916091100||ACK^A08|ACK8834|P|2.5
MSA|AA|MSG8821
```

**Was du daran abliest:** `AA` heißt: Die Anwendung hat die Nachricht laut
ACK-Semantik akzeptiert. Das beweist aber noch nicht, dass die Stammdaten
im RIS tatsächlich mit den erwarteten Werten angelegt wurden — das ist eine
eigene, zusätzliche Prüfung im Zielsystem, keine Formalität. „Der komplette
Workflow ist damit sicher erfolgreich abgeschlossen" wäre ein Fehlschluss:
`AA` bestätigt die Annahme dieser einen Nachricht, nicht den Endzustand des
gesamten Vorgangs.
