---
title: "C-STORE: Bilder senden und empfangen"
teaser: Der Dienst, der DICOM überhaupt zu einem Netzwerkprotokoll macht — einmal ganz von vorne, mit beiden Rollen.
objectives:
  - Den Ablauf eines C-STORE als Absender und als Empfänger beschreiben
  - Den DIMSE-Status einer Store Response lesen und einordnen
  - Eine komplette Serie statt einer einzelnen Datei übertragen
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
Aufhänger      Eine ganze Serie soll rüber, nicht nur eine Testdatei
Erklärung      C-STORE als eigener DIMSE-Dienst: ein Objekt, eine
               Bestätigung, pro Objekt einzeln -- keine "Sitzung" über
               mehrere Objekte hinweg außer der einen Association
               Der Store-Status im Detail: Success, Warning (Coercion,
               Lektion 4.6), Failure -- reale Statuscodes aus PS3.7
               storescp als eigene Rolle: Absender und Empfänger sind
               zwei unabhängige Programme, kein "Modus" desselben
               Werkzeugs
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - eine einzelne Datei senden, Erfolg
                 - eine ganze Serie (Ordner) senden
                 - selbst als storescp empfangen, mit dcmdump prüfen
Im Alltag      Was ein Store-Log tatsächlich pro Objekt zeigt
Stolperfallen  Eine Association pro Objekt vs. eine Association fürs
               ganze Paket -- was die Werkzeuge tatsächlich tun
Lab            Node fehlt noch -- "Eine Serie von A nach B schicken"
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- Alle Beispiele müssen in der Spielwiese erzeugt und wörtlich übernommen werden.
- Abgrenzung zu Lektion 1.0 (dort werden `storescu`/`storescp` bereits als
  Werkzeuge vorgestellt) — diese Lektion vertieft den Dienst und seinen
  Statuscode-Raum.

## Selbstcheck

*Folgt mit dem Fließtext.*
