---
title: "Move Destination unbekannt"
scenario_title: "Die Study ist da, aber der Retrieve kommt nie am Viewer an"
---

## Briefing

Die Study lässt sich im PACS finden. Der C-MOVE auf `VIEWER-07` scheitert.
Du sollst den Fehler eingrenzen, ohne die Originalstudie erneut von der
Modalität zu senden.

## Hints

### h1

C-FIND und C-MOVE prüfen nicht denselben vollständigen Weg. Zeichne die
zweite Association vom PACS zum Ziel ein.

### h2

Vergleiche den Port, den das PACS für `VIEWER-07` kennt, mit dem Port,
auf dem der Storage SCP des Viewers tatsächlich lauscht.

## Write-up

Die Study war nie das Problem. C-FIND belegte bereits, dass sie im Archiv
existiert. Beim C-MOVE musste das PACS anschließend eine neue C-STORE-
Association zu `VIEWER-07` aufbauen. Die Destination war zwar registriert,
zeigte aber auf Port `11113` statt `11112`.

**Was du mitnimmst:** Bei C-MOVE immer den Dreieckspfad betrachten:
Requester → PACS und danach PACS → Move Destination.

<!-- kein-beispiel -->
```
Move-Destination-Registry im PACS, Auszug:
  AE Title    Host          Port     Status
  VIEWER-07   10.40.0.22    11113    vorher (falsch)
  VIEWER-07   10.40.0.22    11112    korrigiert
```

Der AE Title war korrekt registriert — nur der hinterlegte Port zeigte
nicht auf den Storage SCP, der auf dem Viewer tatsächlich lauscht.
