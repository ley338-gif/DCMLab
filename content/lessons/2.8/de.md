---
title: "DICOMweb: WADO-RS, QIDO-RS, STOW-RS"
teaser: Dieselben drei Dienste wie bei DIMSE — nur über HTTP statt über eine Association.
objectives:
  - QIDO-RS, WADO-RS und STOW-RS den DIMSE-Diensten C-FIND, C-GET und C-STORE zuordnen
  - Eine Abfrage sowohl per DIMSE als auch per REST stellen
  - Einordnen, wann DICOMweb eine sinnvolle Alternative zu DIMSE ist
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
Aufhänger      Ein Web-Client soll dieselben Bilder abfragen wie eine
               Modalität -- ohne DIMSE-Bibliothek
Erklärung      DICOMweb als HTTP-Abbildung derselben drei Grunddienste:
               QIDO-RS = C-FIND, WADO-RS = C-GET/Abruf, STOW-RS =
               C-STORE
               Was gleich bleibt (dieselben Tags, dieselbe Hierarchie)
               und was sich ändert (JSON/Multipart statt DIMSE-PDUs,
               HTTP-Statuscodes statt DIMSE-Status)
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - dieselbe Studiensuche einmal per findscu, einmal per
                   QIDO-RS (curl gegen Orthancs REST-API)
                 - ein Objekt per STOW-RS hochladen, zum Vergleich mit
                   storescu
Im Alltag      Wann ein Web-Client statt eines DIMSE-Clients Sinn ergibt
Stolperfallen  DICOMweb für einen Ersatz von DIMSE halten statt für
               eine zweite Zugangsart zu denselben Daten
Lab            Node fehlt noch -- "Dieselbe Abfrage per DIMSE und per
               REST"
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- Alle Beispiele müssen in der Spielwiese erzeugt und wörtlich übernommen werden.
- `curl` ist noch kein registriertes Werkzeug in `content/tools/de.yml`
  — muss beim Schreiben des Fließtexts ergänzt werden (Name, Zweck,
  Beispielaufruf, erste Lektion/Anker), analog zu den bestehenden
  Registry-Einträgen.
- Orthanc beherrscht QIDO-RS/WADO-RS/STOW-RS bereits über dieselbe
  REST-API, die für Storage Commitment (Lektion 2.7, ADR 0031) genutzt
  wird — noch nicht verifiziert, ob alle drei Dienste ohne weitere
  Konfiguration laufen.

## Selbstcheck

*Folgt mit dem Fließtext.*
