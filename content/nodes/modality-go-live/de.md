---
title: "Go-live ist mehr als C-ECHO"
scenario_title: "Ein neues CT soll heute in Betrieb gehen"
---

## Briefing

Der Netzweg steht und Verification ist grün. Du entscheidest, welche Tests
noch nötig sind, bevor die Modalität produktiv genutzt wird.

## Hints

### h1

Verification, Storage und Worklist sind verschiedene DICOM-Dienste.

### h2

Ein Senderstatus allein ist kein unabhängiger Nachweis des Archivbestands.

## Write-up

Die sichere Reihenfolge lautet: Sollkonfiguration dokumentieren, C-ECHO,
C-STORE mit benötigter SOP Class, unabhängige Archivsuche und anschließend
der echte geplante Workflow mit MWL/RIS/KIS.

**Was du mitnimmst:** Der erste echte Patient darf nicht der erste
End-to-End-Test sein.

<!-- kein-beispiel -->
```
Abnahmeprotokoll CT-Raum 3, Beispiel:
  [x] Verification (C-ECHO) gegen PACS-ARCHIV        -- erfolgreich
  [x] Storage (C-STORE) Testobjekt, SOP Class CT      -- erfolgreich
  [x] Unabhaengige Archivsuche nach Study UID          -- Study gefunden
  [x] MWL-Auftrag ausgewaehlt, End-to-End durchgespielt -- erfolgreich
```

Erst wenn alle vier Zeilen unabhängig voneinander abgehakt sind, gilt die
Modalität als betriebsbereit — jede einzelne prüft eine andere Fähigkeit.
