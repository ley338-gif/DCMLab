---
title: "Backup, Replikation, Archiv und Disaster Recovery auseinanderhalten"
teaser: "Vier Kopien derselben Studie können vier völlig verschiedene Zwecke haben. Erst im Ausfall zeigt sich, ob du wirklich eine Wiederherstellungsstrategie hattest."
objectives:
  - PACS-Archiv, Backup, Replikation und Disaster Recovery funktional unterscheiden
  - RPO und RTO auf einen PACS-Ausfall übertragen
  - Erkennen, warum ein DICOM-Retrieve kein Datenbank-Restore ersetzt
  - Wiederherstellung von DICOM-Objekten und Wiederherstellung des Anwendungskontexts trennen
  - Einen Restore-Test als Teil des Betriebs planen
---

## „Die Bilder liegen doch noch auf dem zweiten Storage"

Das Primärsystem fällt aus. Auf einem zweiten Storage existieren offenbar dieselben DICOM-Dateien. Trotzdem kann sich kein Anwender anmelden, Studienlisten fehlen und Routingjobs sind weg.

Das ist kein Widerspruch. **Bilddaten sind nur ein Teil eines PACS.**

Ein produktives System enthält zusätzlich Datenbankzustand, Benutzer-/Rechtekonfiguration, Routingregeln, AE-Registry, Jobzustände, Viewer-Konfiguration, Integrationsparameter und oft proprietäre Indizes.

Wer nur die Images kopiert, hat möglicherweise ein zweites Archiv — aber noch keinen vollständigen Disaster-Recovery-Plan.

## Vier Begriffe, vier Zwecke

### Archiv

Das Archiv ist der produktiv nutzbare, fachliche Bestand. Es beantwortet DICOM-Queries und liefert Bilder für klinische Workflows.

### Replikation

Replikation hält Daten auf einem zweiten System möglichst zeitnah synchron. Sie verbessert Verfügbarkeit, kann aber Fehler mit replizieren: versehentlich gelöschte oder korrumpierte Daten können genauso schnell auf dem Ziel landen.

### Backup

Ein Backup bewahrt einen wiederherstellbaren Zustand unabhängig vom laufenden Primärsystem. Entscheidend ist nicht, dass ein Backupjob „grün“ ist, sondern dass sich daraus **nachweislich** wiederherstellen lässt.

### Disaster Recovery

DR beschreibt den gesamten Weg zurück zu einem arbeitsfähigen Dienst: Infrastruktur, Anwendung, Datenbank, DICOM-Bestand, Konfiguration, Schnittstellen, DNS/IP, Zertifikate und fachliche Validierung.

## RPO und RTO ohne Managementsprache

**RPO** beantwortet: Wie viele Daten dürfen im schlimmsten Fall verloren gehen?

Wenn das RPO 15 Minuten beträgt, muss deine Schutzstrategie einen Ausfall so abfangen, dass höchstens ungefähr die letzten 15 Minuten fehlen.

**RTO** beantwortet: Wie lange darf der Dienst ausfallen?

Wenn Radiologie nach vier Stunden wieder arbeitsfähig sein muss, ist „wir bestellen erst Ersatzhardware“ kein ausreichender DR-Plan.

## Warum Retrieve kein Restore ist

Ein C-MOVE von einem Zweitarchiv kann DICOM-Objekte zurückbringen. Das kann bei einzelnen verlorenen Studien genau richtig sein.

Es stellt aber nicht automatisch wieder her:

- PACS-Datenbank und Indizes
- Benutzer und Berechtigungen
- RIS-/KIS-Verknüpfungen
- Routingregeln
- Jobhistorien
- Viewer-Konfiguration
- lokale Korrekturen oder Merge-Zustände

Darum musst du zuerst klären, **was** ausgefallen ist: einzelne DICOM-Objekte, ein Storage, die Anwendung oder der komplette Standort.

## Ein Restore-Test braucht eine fachliche Abnahme

Ein technischer Restore ist erst dann nützlich, wenn der klinische Workflow wieder funktioniert. Ein Test sollte daher nicht bei „VM bootet“ enden.

Prüfe beispielsweise:

1. PACS-Dienste starten.
2. Archivbestand ist konsistent.
3. Eine bekannte Study UID ist suchbar.
4. Images lassen sich anzeigen.
5. C-STORE von Testmodalität funktioniert.
6. Q/R funktioniert.
7. RIS/KIS-Verknüpfung funktioniert.
8. Routing/Weiterleitung funktioniert.
9. Monitoring schlägt wieder an.

<!-- kein-beispiel -->
```text
Restore-Test-Log, Beispiel:
  Schritt 1  PACS-Dienste gestartet             OK
  Schritt 2  Archivbestand konsistent           OK
  Schritt 3  Bekannte Study UID auffindbar      OK
  Schritt 4  Images lassen sich anzeigen        OK
  Schritt 5  C-STORE von Testmodalitaet         OK
  Schritt 6  Q/R funktioniert                   OK
  Schritt 9  Monitoring schlaegt wieder an      OK
```

Erst wenn ein Log wie dieses vollständig grün ist, gilt der Restore als
fachlich abgeschlossen — nicht, wenn die virtuelle Maschine wieder bootet.

## Im Alltag heißt das

| Störung | Typisch passende Maßnahme |
|---|---|
| einzelne Studie versehentlich entfernt | Objekt-Restore oder Retrieve aus Zweitarchiv |
| Storage defekt, Anwendung intakt | Storage-/Daten-Recovery |
| Datenbank beschädigt | DB-Restore plus Konsistenzprüfung zum DICOM-Bestand |
| gesamtes PACS verloren | DR-Verfahren |
| Standortausfall | Failover/DR an anderem Standort |

Die richtige Maßnahme hängt vom Ausfallobjekt ab, nicht davon, welche Kopie gerade bequem erreichbar ist.

## Stolperfallen

- **Replikation als Backup bezeichnen.** Sie schützt nicht automatisch vor logischen Fehlern.
- **Backup-Erfolg ohne Restore-Test akzeptieren.** Ein nicht getestetes Backup ist eine Annahme.
- **Nur Images betrachten.** PACS-Betrieb hängt an deutlich mehr Zustand.
- **RPO/RTO erst im Ausfall diskutieren.** Dann sind technische Entscheidungen bereits gefallen.
- **Restore ohne klinischen End-to-End-Test abschließen.** „Server läuft“ ist nicht „Radiologie arbeitet“.

## Lab-Einstieg

Du bekommst vier Ausfallszenarien. Deine Aufgabe ist nicht, immer „Restore“ zu wählen, sondern zu entscheiden, ob Retrieve, Backup-Restore, Replikations-Failover oder ein vollständiges DR-Verfahren passt.

## Selbstcheck

1. Warum ist eine Replikation nicht automatisch ein Backup?
2. Was unterscheidet RPO von RTO?
3. Welche PACS-Bestandteile fehlen dir möglicherweise trotz vollständiger DICOM-Dateikopie?
4. Wann kann ein DICOM-Retrieve sinnvoller sein als ein vollständiger Restore?
5. Wann ist ein Restore fachlich wirklich abgeschlossen?

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Das Primärsystem fällt aus, DICOM-Dateien liegen aber vollständig auf einem zweiten Storage. Anwender können sich trotzdem nicht anmelden. Warum?**
1. Die DICOM-Dateien sind beschädigt
2. Ein PACS besteht ausschließlich aus DICOM-Dateien
3. Datenbank, Rechte, Routing und weitere Anwendungszustände fehlen trotz vorhandener Bilddateien
4. Der zweite Storage ist grundsätzlich nicht erreichbar

**q2 — Welche Aussagen stimmen?** *(Mehrfachauswahl)*
1. Ein DICOM-Retrieve kann einzelne verlorene Studien gezielt wiederherstellen
2. Replikation ersetzt automatisch ein getestetes Backup
3. RPO beschreibt den maximal tolerierbaren Datenverlust
4. Ein Restore ist erst abgeschlossen, wenn der klinische Workflow wieder funktioniert
