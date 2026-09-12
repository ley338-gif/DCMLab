---
title: „Nach TLS-Aktivierung geht nichts mehr"
teaser: TLS verschiebt den Fehler von der DICOM-Ebene auf die Transportebene — und macht dabei jedes Legacy-Gerät sichtbar.
objectives:
  - Einen TLS-Fehler von einem DICOM-Fehler unterscheiden
  - Zertifikatskette, Gültigkeit und Namensprüfung als getrennte Ursachen prüfen
  - Legacy-Geräte als harte Grenze einer TLS-Einführung einschätzen
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
Aufhänger      Ticket nach einem Umstellungswochenende
Erklärung      Was TLS an der Association ändert und was nicht
               Drei Ursachenklassen
                 - Zertifikat: Kette, Gültigkeit, Name
                 - Cipher: keine gemeinsame Suite, veraltete Protokollversion
                 - Gerät kann kein TLS
               Warum der Mitschnitt ab hier weniger zeigt
Beispiele      in der Spielwiese erzeugen, wörtlich übernehmen:
                 - echoscu mit TLS gegen einen TLS-Endpunkt
                 - derselbe Aufruf ohne passendes Zertifikat
                 - Mitschnitt: Handshake statt DICOM-Aushandlung
Im Alltag      Vorgehen bei einer Umstellung, Rückfallebene
Stolperfallen  „TLS ist aktiv, also ist der Rest egal"
Lab            Node fehlt noch
Selbstcheck    3 Fragen
```

## Was zum Schreiben noch fehlt

- Die Spielwiese hat bisher keinen TLS-Endpunkt. Orthanc und die DCMTK-Werkzeuge können DICOM-TLS, das muss aber im Container eingerichtet und mit Testzertifikaten bestückt sein.
- Entscheidung, wie tief die Lektion in die Zertifikatsverwaltung geht — das grenzt an Track 5, Lektion 5.6.

## Selbstcheck

*Folgt mit dem Fließtext.*
