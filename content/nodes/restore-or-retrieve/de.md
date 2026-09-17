---
title: "Restore oder Retrieve?"
scenario_title: "Nicht jeder Datenverlust braucht denselben Wiederherstellungsweg"
---

## Briefing

Du bekommst zwei Ausfallszenarien. Wähle jeweils die kleinste Maßnahme, die
den verlorenen Zustand wirklich wiederherstellt.

## Hints

### h1

Frage zuerst: Fehlt eine Study, ein Storage, die Datenbank oder der komplette
Dienst?

### h2

Ein DICOM-Retrieve stellt DICOM-Objekte wieder her, nicht automatisch Benutzer,
Routingregeln oder Anwendungskonfiguration.

## Write-up

Bei einer einzelnen verlorenen Study ist ein gezielter Retrieve sinnvoll. Bei
einem verlorenen PACS reicht ein Ordner voller DICOM-Dateien nicht: Datenbank,
Anwendung, Rechte, Routing und Integrationen gehören zum wiederherzustellenden
Systemzustand.

**Was du mitnimmst:** Recovery-Maßnahmen nach Ausfallumfang wählen, nicht nach
dem Werkzeug, das gerade verfügbar ist.

<!-- kein-beispiel -->
```
Incident-Log, Beispiel:
  21.09.2026 09:02  Study 1.2.840...4711 fehlt im Primaerarchiv.
                     PACS/DB/Viewer sonst unauffaellig.
  21.09.2026 09:15  Study im Zweitarchiv bestaetigt vorhanden.
  21.09.2026 09:20  Gezielter Retrieve Primaerarchiv <- Zweitarchiv.
                     Kein Voll-Restore, kein DR-Wechsel ausgeloest.
```

Der Umfang der Maßnahme steht im Log, bevor sie ausgeführt wird — das
macht später nachvollziehbar, warum kein größerer Eingriff nötig war.
