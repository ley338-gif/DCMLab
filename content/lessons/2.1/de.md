---
title: "C-ECHO: der Ping, der keiner ist"
teaser: Ein Klick, eine grüne Meldung — und trotzdem beweist er mehr als jedes ICMP-Ping.
objectives:
  - Erklären, was ein C-ECHO tatsächlich prüft — und was nicht
  - Den Ablauf einer Verification-Association nachvollziehen
  - Ein erfolgreiches C-ECHO von den Grenzen dieses Erfolgs unterscheiden
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
Aufhänger      "Ist die Verbindung überhaupt da?" -- die Frage, die vor
               jeder anderen steht
Erklärung      Was ein C-ECHO tatsächlich ist: eine eigene DICOM-Association
               mit genau einer SOP Class (Verification, real
               1.2.840.10008.1.1), kein ICMP-Ping
               Was ein erfolgreiches C-ECHO beweist: TCP-Verbindung,
               Association-Aufbau, Called/Calling AE Title akzeptiert
               Was es NICHT beweist: dass ein bestimmter Objekttyp
               akzeptiert würde -- dafür ist ein eigener Presentation
               Context nötig (Lektion 1.8, vertieft in 4.2)
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - echoscu -v gegen die Spielwiese, Erfolg
                 - derselbe Aufruf mit falschem Called AE Title, Ablehnung
                 - ein Mitschnitt der Association
Im Alltag      Wann ein C-ECHO reicht, wann nicht
Stolperfallen  "C-ECHO grün, also geht alles" -- Grenzen des Diensts
Lab            Kein Lab laut Curriculum
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- Alle Beispiele müssen in der Spielwiese erzeugt und wörtlich übernommen werden.
- Abgrenzung zu Lektion 1.0 (dort wird `echoscu` bereits als Werkzeug
  vorgestellt) und zu Lektion 4.1 (dort wird eine C-ECHO-Ablehnung als
  Fehlerbild behandelt) — diese Lektion vertieft den Dienst selbst.

## Selbstcheck

*Folgt mit dem Fließtext.*
