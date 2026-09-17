---
title: "Eine neue Modalität sauber ans PACS anbinden"
teaser: "IP, Port und AE Title reichen für einen Screenshot im Service-Menü. Für einen belastbaren Go-live brauchst du einen reproduzierbaren Abnahmetest."
objectives:
  - Eine Modalitätsanbindung als kontrollierten Ablauf statt als Einzeltest planen
  - Lokalen und entfernten AE Title, Host und Port eindeutig dokumentieren
  - Verification, Storage, Worklist und fachliche Zuordnung getrennt abnehmen
  - Negativtests und Rückfallplan in eine Inbetriebnahme einbauen
  - Ein minimales Abnahmeprotokoll für spätere Störungen erstellen
---

## „C-ECHO ist grün, dann können wir live gehen"

Ein neues CT steht im Raum, Herstellertechniker und Medizintechnik warten. C-ECHO zum PACS funktioniert. Alle schauen dich an: „Dann sind wir fertig, oder?"

Nein. C-ECHO ist der **erste** sinnvolle Test, nicht die Abnahme.

Eine Modalität kann das PACS per Verification erreichen und trotzdem keine CT Images speichern. Sie kann Bilder speichern und trotzdem eine leere Worklist haben. Sie kann beides und dennoch falsche Patientendaten erzeugen, weil die Worklist-Zuordnung nicht stimmt.

Ein sauberer Go-live prüft deshalb den **Workflow**, nicht nur den Port.

## Schritt 1: Konfiguration schriftlich festhalten

Bevor du testest, schreibst du die Sollwerte auf. Nicht als Screenshot in einem Ticket, sondern als strukturierte Liste.

| Parameter | Beispiel |
|---|---|
| Modalität local AE | `CT-NEU-01` |
| Modalität IP | `10.20.30.41` |
| PACS Called AE | `PACS-ARCHIV` |
| PACS Host | `10.20.10.20` |
| PACS Port | `104` |
| MWL Called AE | `RIS-BROKER` |
| MWL Host/Port | `10.20.10.30:104` |
| unterstützte SOP Classes | aus Conformance Statement |
| Transfer Syntaxes | aus Conformance Statement |

Diese Tabelle wird später zur Referenz. Ohne sie vergleichst du bei einer Störung zwei Service-Menüs aus dem Gedächtnis.

## Schritt 2: Verification testen

```text
$ echoscu -v -aet CT-NEU-01 -aec PACS-ARCHIV 10.20.10.20 104
I: Requesting Association
I: Association Accepted
I: Sending Echo Request: MsgID 1
I: Received Echo Response (Status: 0x0000 - Success)
I: Releasing Association
```

**Was du daran abliest:** Wenn die Verification Association akzeptiert wird, sind Netzweg, TCP-Port und die für Verification relevante DICOM-Konfiguration grundsätzlich erreichbar. Noch ist kein CT Image Storage getestet.

## Schritt 3: Storage mit einem kontrollierten Testobjekt

Nimm ein freigegebenes Testobjekt, dessen Patientendaten und UIDs du kennst. Keine spontane Produktivaufnahme.

```text
$ storescu -aet CT-NEU-01 -aec PACS-ARCHIV 10.20.10.20 104 test-ct.dcm
```

**Was du daran abliest:** Erst ein erfolgreicher C-STORE mit einer tatsächlich benötigten SOP Class zeigt, dass die Modalität diesen Objekttyp speichern könnte. Enhanced CT, SR oder RDSR sind gegebenenfalls zusätzliche, eigene Tests.

## Schritt 4: Archivbestand unabhängig nachprüfen

```text
$ findscu -S -aec PACS-ARCHIV \
  -k QueryRetrieveLevel=STUDY \
  -k PatientID=DCMLAB-ABNAHME \
  -k StudyInstanceUID= \
  10.20.10.20 104
```

**Was du daran abliest:** Die Abnahme endet nicht bei „Sender meldet Success“. Du prüfst aus Archivsicht, ob die Teststudie tatsächlich im erwarteten Bestand auftaucht.

## Schritt 5: Worklist separat testen

Bei einer geplanten Modalität gehört MWL zur fachlichen Abnahme. Dabei geht es nicht nur darum, *irgendeinen* Treffer zu sehen. Prüfe mindestens:

- richtige Patient ID und PatientName
- richtige Accession Number
- erwartete Modality
- Scheduled Station AE Title
- korrektes Datum/Zeitfenster
- Verhalten bei mehreren Treffern

Ein Tippfehler im Stationsfilter kann die Worklist komplett leeren, obwohl PACS-Storage perfekt funktioniert.

## Schritt 6: Fachlichen End-to-End-Test durchführen

Jetzt erst wird aus Technik ein klinischer Workflow:

1. Testauftrag im RIS/KIS anlegen.
2. Auftrag an der Modalität über MWL auswählen.
3. Testbilder erzeugen.
4. Senden.
5. Studie im PACS prüfen.
6. Wenn genutzt: MPPS, Storage Commitment, RDSR und Routing prüfen.
7. Viewer-Aufruf aus dem vorgesehenen Arbeitsplatzkontext testen.

Das Ziel ist nicht „DICOM geht“, sondern: **Der reale Arbeitsablauf erzeugt auf jedem System denselben Fall.**

## Ein Negativtest gehört dazu

Ändere in einer Testumgebung bewusst einen Wert — beispielsweise den Called AE Title — und prüfe, ob die resultierende Fehlermeldung zu deiner Dokumentation passt. Das wirkt zunächst unnötig, zahlt sich aber beim ersten echten Ausfall aus: Du kennst dann das Fehlerbild einer falschen Konfiguration bereits.

## Im Alltag heißt das

Ein brauchbares Abnahmeprotokoll enthält nicht nur „OK“, sondern Belege:

| Test | Ergebnis / Beleg |
|---|---|
| C-ECHO | Zeit, Quelle, Ziel, Resultat |
| C-STORE | SOP Class, SOP Instance UID, Resultat |
| Archivsuche | Study Instance UID im Ziel gefunden |
| MWL | erwarteter Auftrag mit korrekten Keys |
| Viewer | Studie für vorgesehene Benutzer sichtbar |
| Zusatzobjekte | SR/RDSR/PDF etc. falls relevant |
| Rückweg | Retrieve/Storage Commitment falls genutzt |

## Stolperfallen

- **Nur C-ECHO testen.** Verification ist nicht Storage.
- **Nur einen Bildtyp testen.** Zusätzliche SOP Classes können separat scheitern.
- **Mit echten Patientendaten improvisieren.** Abnahmedaten müssen kontrolliert sein.
- **Keinen Sollzustand dokumentieren.** Dann fehlt später der Vergleich.
- **Hersteller verlässt den Raum ohne End-to-End-Test.** Ein lokales Service-Menü ist kein klinischer Workflow.

## Lab-Einstieg

Du übernimmst eine Modalität kurz vor dem Go-live. Mehrere Tests sind möglich, aber nur eine Reihenfolge liefert schnell belastbare Aussagen. Baue daraus eine sichere Abnahme.

## Selbstcheck

1. Warum reicht C-ECHO für eine Abnahme nicht aus?
2. Welche vier Identitäten/Adressen solltest du vor dem Test mindestens dokumentieren?
3. Warum folgt nach C-STORE noch eine unabhängige Archivsuche?
4. Welche zusätzlichen Objekttypen würdest du bei einem modernen CT neben klassischen Images berücksichtigen?
5. Was ist der Zweck eines Negativtests?

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Welche Aussagen zu einer sauberen Modalitäts-Abnahme stimmen?** *(Mehrfachauswahl)*
1. C-ECHO allein beweist noch keinen erfolgreichen CT-Image-Storage
2. Storage und Worklist sollten getrennt getestet werden
3. Ein unabhängiger Archiv-Query nach dem Testobjekt gehört zur Abnahme
4. Sobald C-ECHO grün ist, kann die Modalität produktiv gehen
5. Ein Negativtest mit bewusst falscher Konfiguration gehört zu einer belastbaren Abnahme

**q2 — Was ist am Ende der wichtigste Test einer Modalitäts-Inbetriebnahme?**
1. Ein grünes C-ECHO im Service-Menü
2. Ein einzelner erfolgreicher C-STORE-Test
3. Der fachliche End-to-End-Workflow über MWL/RIS/KIS mit dem realen Ablauf
4. Ein Screenshot der Konfiguration
