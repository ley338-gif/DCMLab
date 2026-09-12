---
title: „Association rejected" — die Verbindung kommt gar nicht erst zustande
teaser: Die häufigste Fehlkonfiguration überhaupt, und die einzige, die sich zeichengenau prüfen lässt.
objectives:
  - Eine abgelehnte Association von einem Fehler im laufenden Transfer unterscheiden
  - Den Ablehnungsgrund im Log dem Called oder dem Calling AE Title zuordnen
  - Beide Konfigurationsseiten zeichengenau abgleichen, statt eine Seite zu raten
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
Aufhänger      Ticket aus dem Alltag: eine Modalität wird abgewiesen
Erklärung      Ablehnung beim Verbindungsaufbau vs. Fehler danach
               Reject-Meldungen und ihre zwei üblichen Ursachen:
                 - Called AE Title unbekannt  -> Ziel falsch eingetragen
                 - Calling AE Title unbekannt -> Absender nicht registriert
               Zeichengenauer Vergleich, Bindestrich vs. Unterstrich
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - echoscu mit falschem -aec   -> Ablehnung
                 - echoscu mit falschem -aet   -> Ablehnung
                 - echoscu korrekt             -> Erfolg zum Gegenvergleich
                 - dieselbe Ablehnung im Mitschnitt
Im Alltag      Abgleichtabelle: welcher Wert steht auf welcher Seite
Stolperfallen  Groß-/Kleinschreibung, Leerzeichen, AE Title ist kein Hostname
Lab            Node fehlt noch (Silent CT und Wrong Door decken das Thema
               bereits ab — Zuschnitt entscheiden)
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- Die vollständigen Reject-Ausgaben aus der Spielwiese (beide Fehlerfälle plus Erfolgsfall).
- Entscheidung: eigene Node, oder verweist 4.1 auf die bestehenden Nodes `silent-ct` und `wrong-door`? `silent-ct/node.yml` hatte bereits eine `related_lessons`-Referenz auf `"4.1"`, die entfernt wurde, weil die Lektion fehlte.
- Abgrenzung zu Lektion 1.5: dort werden die Rollen erklärt, 4.1 soll rein diagnostisch sein.

## Selbstcheck

*Folgt mit dem Fließtext.*
