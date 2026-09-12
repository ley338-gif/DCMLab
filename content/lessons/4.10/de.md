---
title: 'Systematik: Logs, Wireshark-Filter, Reproduzieren'
teaser: 'Die Abschlusslektion des Tracks: ein Vorgehen, das auch bei Fehlerbildern trägt, die hier nicht vorkamen.'
objectives:
  - Einen Fehler zuverlässig reproduzieren, bevor man ihn erklärt
  - Die Schichten von außen nach innen eingrenzen, statt zu raten
  - Einen Mitschnitt gezielt filtern, statt ihn zu lesen
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
Aufhänger      Ein Ticket, das in keines der neun Fehlerbilder passt
Erklärung      Reproduzieren zuerst — was man dafür festhält
               Eingrenzen von außen nach innen
                 Netzweg -> Port -> Association -> Presentation Context ->
                 einzelnes Objekt
               Logs: welche Seite protokolliert was, und wo nicht
               Mitschnitt gezielt filtern statt vollständig lesen
               Was man dokumentiert, damit der Nächste nicht neu anfängt
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - tshark mit DICOM-Filter auf einen Sendevorgang
                 - derselbe Mitschnitt auf eine Association eingegrenzt
                 - Verbositätsstufen der DCMTK-Werkzeuge im Vergleich
Im Alltag      Befehlstabelle: ein Handgriff je Eingrenzungsschritt
Stolperfallen  Zwei Dinge gleichzeitig ändern, Mitschnitt ohne Filter
Lab            Node fehlt noch — bietet sich als Abschluss-Node des Tracks an
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- Die tshark-Filterbeispiele: Lektion 1.0 zeigt bereits zwei kurze Filter. Der Zuschnitt muss geklärt werden, damit 4.10 nicht wiederholt, was 1.0 schon sagt.
- Entscheidung, ob 4.10 eine eigene Abschluss-Node bekommt, die mehrere Fehlerbilder kombiniert.

## Selbstcheck

*Folgt mit dem Fließtext.*
