---
title: „Nur manche Bilder kommen an"
teaser: 'Der Teiltransfer ist der unangenehmste Fall: nichts wirkt kaputt, aber die Studie ist unvollständig.'
objectives:
  - Einen Teiltransfer als eigenes Fehlerbild erkennen, statt ihn als Netzwerkproblem zu behandeln
  - Eine nicht akzeptierte SOP Class als Ursache nachweisen
  - Größen- und Mengenlimits als zweite Ursachenklasse prüfen
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
Aufhänger      Ticket: Studie im Archiv unvollständig
Erklärung      Warum eine Serie nur teilweise ankommt
                 - SOP Class des Objekttyps nicht freigeschaltet
                   (Secondary Capture, SR, Dose Report, Presentation State)
                 - Größenlimits, Objektanzahl je Association
               Was die Modalität davon mitbekommt — und was nicht
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - dcmdump: SOP Class UID der betroffenen Objekte
                 - storescu mit gemischtem Ordner, Statuscodes je Objekt
                 - Empfängerseite mit eingeschränkter Freischaltung
Im Alltag      Vollständigkeit prüfen, statt der Erfolgsmeldung zu glauben
Stolperfallen  Zählung Instances gegen Bilder, stille Ablehnung
Lab            Node fehlt noch
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- Ein Datensatz mit gemischten SOP Classes. `ct-thorax-60` enthält nur CT-Bilder — `datasets.yml` braucht dafür einen neuen Eintrag.
- Die echten Statuscodes aus `storescu` bei teilweiser Ablehnung.

## Selbstcheck

*Folgt mit dem Fließtext.*
