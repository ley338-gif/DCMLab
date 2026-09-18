---
title: Der Befund zurück — ORU/OBX und die Verbindung zur Studie
teaser: Bilder sind im PACS, der Befund ist freigegeben — aber im KIS steht nichts. Jetzt läuft die Fehlersuche in die andere Richtung.
objectives:
  - Befundtransport und Bildtransport als getrennte Pfade verstehen
  - OBR/OBX in einer Ergebnisnachricht fachlich einordnen
  - Einen fehlenden Befund mit Auftrag und Bildstudie korrelieren
---

## „Die Bilder sind da, aber der Befund fehlt“

Das PACS zeigt die Studie. Im Befundsystem ist der Bericht final. Im KIS sieht der behandelnde Arzt trotzdem keinen Befund.

DICOM C-STORE hilft dir hier nicht weiter. Der Bildweg war erfolgreich. Jetzt geht es um den Ergebnisweg.

## Ein vereinfachter Rückweg

```text
PACS / Viewer
      │
      ▼
Befundsystem / RIS
      │
      │ HL7 v2 Result
      ▼
Interface Engine
      │
      ▼
KIS / EHR
```

**Was du daran abliest:** Bildverfügbarkeit und Befundverfügbarkeit sind zwei getrennte Betriebszustände. Ein System kann vollständig funktionieren, während der andere Pfad gestört ist.

## OBR und OBX im Ergebnis

Vereinfacht:

```text
MSH|^~\&|RIS|RAD|KIS|HAUS|20260916121500||ORU^R01|RES88721|P|2.5
PID|1||4711^^^KLINIK^MR||MUSTER^ERIKA
OBR|1|ORD93821^KIS||CTTHORAX^CT Thorax nativ|||20260916103000
OBX|1|TX|RADREPORT^Radiologischer Befund||Kein Nachweis eines fokalen Infiltrats.|||N|||F
```

**Was du daran abliest:** Der Bericht wird nicht dadurch mit der Bildstudie verbunden, dass „irgendwo derselbe Name“ steht. Auftrag, Patient und lokale Befundkennungen müssen konsistent korrelierbar sein.

## Status ist Teil des Workflows

Ein Befund durchläuft typischerweise mehrere Ergebnisstatus (OBX-11 in einer ORU-Nachricht, vereinfacht):

- `P` — **Preliminary**: vorläufiger Befund, noch nicht abschließend freigegeben
- `F` — **Final**: abschließend freigegebener Befund
- `C` — **Correction**: ersetzt einen zuvor finalen Befund

Hier betrachten wir vereinfacht `OBX-11` auf Observation-Ebene. In realen ORU-Profilen kann zusätzlich ein Result Status auf OBR-Ebene, etwa `OBR-25`, relevant sein. Maßgeblich ist wie immer das konkrete Interface-Profil.

Ein typischer zeitlicher Verlauf:

```text
10:42  P  Preliminary
11:03  F  Final
11:18  C  Corrected
```

**Was du daran abliest:** Der Status ist kein Nebendetail, sondern Teil des fachlichen Zustands. Drei Nachrichten zu demselben Auftrag können nacheinander gültig sein, ohne sich zu widersprechen — solange das Zielsystem jede davon verarbeitet und den jeweils aktuellen Status übernimmt.

Genau hier entsteht ein typisches Fehlerbild:

```text
RIS: zeigt den korrigierten Befund (C, 11:18)
KIS: zeigt weiterhin den vorherigen finalen Befund (F, 11:03)
```

**Was du daran abliest:** Die Correction-Nachricht ist im RIS sichtbar, aber offenbar nicht im KIS angekommen oder dort nicht übernommen worden. Das ist kein DICOM-/PACS-Problem — die Bilder sind unverändert korrekt im PACS. Zu untersuchen ist die Ergebnisstrecke: Wurde die Correction-Nachricht überhaupt erzeugt und geroutet, und wie hat das KIS sie verarbeitet?

## Der systemübergreifende Trace

Für einen fehlenden Befund sammelst du:

<!-- kein-beispiel -->
```text
Patient ID:       4711 / KLINIK
Order:            ORD93821
Accession:        A93821
Study UID:        1.2.276.0.7230010...
Result Message:   RES88721
Report status:    final
```

Dann prüfst du:

1. Ist der Bericht im RIS/Befundsystem final?
2. Wurde eine Ergebnisnachricht erzeugt?
3. Hat die Interface Engine sie geroutet?
4. Welches ACK kam?
5. Hat das KIS sie dem richtigen Patienten/Auftrag zugeordnet?
6. Ist der erwartete Befundstatus sichtbar?

## Im Alltag heißt das

Die Frage „Sind die Bilder da?“ beantwortet nur den DICOM-Pfad.

Die Frage „Ist der Befund da?“ benötigt einen zweiten Trace. Ein guter PACS-/RIS-Administrator kann beide Pfade an derselben Accession/Order-Kette zusammenführen.

## Stolperfallen

- **Befund und Bild als ein Objekt denken.** Sie können in unterschiedlichen Systemen, Standards und Lebenszyklen liegen.
- **Nur finalen Text vergleichen.** Status, Version und Korrekturen gehören zum Befundworkflow.
- **Patient Name als Korrelation verwenden.** Order- und Patient-Identifier sind belastbarer.
- **„Im RIS final“ als Ende betrachten.** Für den klinischen Nutzer ist der Workflow erst beendet, wenn der Befund im Zielsystem verfügbar und korrekt zugeordnet ist.

## Selbstcheck

1. Warum beweist eine sichtbare Studie im PACS nicht, dass der Befundweg funktioniert?
2. Welche IDs würdest du für den Trace eines fehlenden Befunds verwenden?
3. Wo prüfst du nach, wenn das RIS „gesendet“ meldet, das KIS aber nichts anzeigt?
4. Das RIS zeigt einen korrigierten Befund, das KIS noch den vorherigen finalen Befund. Warum ist das kein PACS-Problem, und wo beginnst du die Suche?

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Die Studie ist im PACS sichtbar, der Befund fehlt im KIS. Was folgt daraus?**
1. Der DICOM-Bildtransport ist fehlgeschlagen
2. Bild- und Befundweg sind getrennte Pfade — der Fehler liegt vermutlich im Ergebnisweg
3. Das PACS muss neu gestartet werden
4. Die Accession Number ist ungültig

**q2 — Welche Aussagen stimmen?** *(Mehrfachauswahl)*
1. Ein Befund kann vorläufig, korrigiert oder final sein
2. „Nachricht zugestellt" und „richtiger Befundstatus im KIS" sind dieselbe Prüfung
3. Order- und Patient-Identifier sind für die Korrelation belastbarer als der Patientenname
4. Der Workflow ist für den klinischen Nutzer erst beendet, wenn der Befund im Zielsystem korrekt sichtbar ist
