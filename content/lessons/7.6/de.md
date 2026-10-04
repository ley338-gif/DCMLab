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
MSH|^~\&|RIS|RAD|KIS|HAUS|20260916121500||ORU^R01^ORU_R01|RES88721|P|2.5.1
PID|1||4711^^^KLINIK^MR||MUSTER^ERIKA
OBR|1|ORD93821^KIS||CTTHORAX^CT Thorax nativ|||20260916103000||||||||||||||||||F
OBX|1|TX|RADREPORT^Radiologischer Befund||Kein Nachweis eines fokalen Infiltrats.|||N|||F
```

**Was du daran abliest:** `OBR-2` nennt denselben Auftrag wie die Auftragsnachricht (`ORD93821`), `OBR-7` den Untersuchungszeitpunkt. Die vielen `|` sind die leeren Felder 8 bis 24; dahinter steht in `OBR-25` der Status des Auftrags (`F`). `OBX-2` = `TX` sagt, dass `OBX-5` Text enthält, `OBX-11` = `F` den Status dieses einzelnen Ergebnisses. Der Bericht wird nicht dadurch mit der Bildstudie verbunden, dass „irgendwo derselbe Name“ steht. Auftrag, Patient und lokale Befundkennungen müssen konsistent korrelierbar sein.

## Status ist Teil des Workflows

Ein Befund durchläuft typischerweise mehrere Ergebnisstatus (OBX-11 in einer ORU-Nachricht, vereinfacht):

- `P` — **Preliminary**: vorläufiger Befund, noch nicht abschließend freigegeben
- `F` — **Final**: abschließend freigegebener Befund
- `C` — **Correction**: ersetzt einen zuvor finalen Befund

HL7-Tabelle 0085 kennt weitere Werte. Zwei davon betreffen die Radiologie direkt: `U` setzt einen vorläufigen Befund auf final, ohne ihn erneut zu senden — HL7 nennt die Radiologie ausdrücklich als Beispiel —, und `W` markiert ein Ergebnis als falsch, etwa weil es zum falschen Patienten ging.

Den Status gibt es auf zwei Ebenen: `OBX-11` für das einzelne Ergebnis (Pflichtfeld, HL7-Tabelle 0085) und `OBR-25` für den Auftrag als Ganzes (in einer Ergebnisnachricht Pflicht, HL7-Tabelle 0123). Die Werte ähneln sich, sind aber nicht dieselbe Liste. Maßgeblich ist wie immer das konkrete Interface-Profil.

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

**q3 — `OBX|1|TX|RADREPORT^Radiologischer Befund||Kein Nachweis eines fokalen Infiltrats.|||N|||F` — welchen Status hat dieses Ergebnis?**
1. Preliminary
2. Final
3. Correction
4. Keinen, das Statusfeld ist leer

**q4 — Welcher Wert steht in OBX-11, wenn ein Ergebnis einen zuvor finalen Befund ersetzt? Nur der Buchstabe.** *(Freitext)*

**q5 — Ein Befund ging an das KIS, gehört aber zu einem anderen Patienten. Mit welchem Ergebnisstatus nach HL7-Tabelle 0085 wird er als falsch markiert?**
1. `C`
2. `W`
3. `U`
4. `P`

---

Normstellen geprüft am 04.10.2026: HL7 v2.5.1 Kapitel 7 (ORU^R01, OBX-11) und Kapitel 4 (OBR-7, OBR-25); HL7-Tabellen 0085 (Observation Result Status) und 0123 (Result Status).
