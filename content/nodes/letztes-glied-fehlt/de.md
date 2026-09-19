---
title: Das letzte Glied fehlt
scenario_title: Bilder da, Untersuchung trotzdem nicht abgeschlossen
---

## Briefing

Ticket aus dem RIS: Patient 51902, Auftrag A88370 (CT Wirbelsäule). Die
Bilder sind im PACS sichtbar, ein Radiologe kann die Study öffnen.
Trotzdem markiert das RIS den Vorgang weiterhin als nicht
abgeschlossen.

Drei unabhängige Meldewege können für denselben Vorgang stehen: C-STORE
(Bildtransfer), MPPS (Verfahrensstatus der Modalität) und Storage
Commitment (dauerhafte Übernahme durch das Archiv). Deine Aufgabe: aus
der vorhandenen Evidenz aller drei Ebenen rekonstruieren, an welcher
Stelle die Kette tatsächlich reißt — nicht raten, welcher Dienst
"vermutlich" schuld ist.

Vorkenntnisse: Lektion 4.8, idealerweise auch 2.6 und 2.7. Rechne mit
22 Minuten.

## Hints

### h1

Ein Symptom wie "Vorgang nicht abgeschlossen" kann an mehreren,
unabhängigen Stellen entstehen. Grenze zuerst ein, welche der drei
Ebenen (Bildtransfer, Verfahrensstatus, Speicherbestätigung) überhaupt
betroffen sein könnte, bevor du eine davon vertieft untersuchst.

### h2

MPPS, C-STORE und Storage Commitment sind drei unabhängige
Zustandsmeldungen für denselben Vorgang — ein Erfolg bei einer sagt
nichts über die anderen beiden aus.

### h3

Sieh dir bei der Storage-Commitment-Evidenz genau an: Wurde überhaupt
eine Anfrage gestellt (Transaction UID)? Kam eine Rückmeldung
(N-EVENT-REPORT)? Und falls nicht — stimmt die Registrierung der
Rückruf-Gegenstelle mit ihrem tatsächlichen Zustand überein?

## Write-up

### Symptom

Das RIS zeigt die Untersuchung weiterhin als offen, obwohl die Bilder
im PACS sichtbar und für den Radiologen abrufbar sind. Das äußere
Bild — "Bilder da, Vorgang trotzdem nicht fertig" — sagt für sich
genommen nicht, welche der drei beteiligten Ebenen betroffen ist.

### Zustandskette

<!-- kein-beispiel -->
```text
Procedure State (MPPS)
     │
     ▼
Image Transfer (C-STORE)
     │
     ▼
Object Persistence (PACS-Bestand)
     │
     ▼
Storage Commitment (N-ACTION / N-EVENT-REPORT)
```

Diese vier Zustände hängen zusammen, sind aber vier unabhängige
Aussagen — keine ist automatisch aus einer anderen ableitbar.

### Evidenz

- **C-STORE**: 60 von 60 Objekten gesendet, alle mit Status Success;
  PACS-Bestand zeigt 60 von 60 erwarteten Instanzen für die Study.
- **MPPS**: `N-CREATE` (IN PROGRESS) und `N-SET` (COMPLETED) auf
  derselben Assoziation gegen die Gegenstelle MPPS-BROKER, beide mit
  DIMSE-Status `0x0` beantwortet.
- **Storage Commitment**: `N-ACTION` für alle 60 SOP Instance UIDs
  gestellt und vom Archiv angenommen (Transaction UID
  `2.25.309481726354019283746510293847561029384`). Seit 40 Minuten kein
  `N-EVENT-REPORT` beim Commitment-Listener (Gegenstelle RAD-CALLBACK)
  eingegangen — deutlich länger als die in Lektion 2.7 gezeigte
  Größenordnung von Sekunden. Die Registrierung im Archiv nennt für
  RAD-CALLBACK Port `8104`; laut Betriebsnotiz läuft der tatsächliche
  Listener seit einer Systemmigration auf Port `8114`.

### Hypothesen

1. **C-STORE ist fehlgeschlagen.** Widerlegt: alle 60 SOP Instance UIDs
   mit Success bestätigt, vollständig im PACS-Bestand.
2. **MPPS wurde nie ordnungsgemäß abgeschlossen.** Widerlegt: `N-CREATE`
   und `N-SET` beide mit Status `0x0`, `N-SET` meldet `COMPLETED`.
3. **Storage Commitment wurde nie angefordert.** Widerlegt: die
   `N-ACTION`-Anfrage mit ihrer Transaction UID ist nachweislich
   gestellt und vom Archiv angenommen worden.
4. **Storage Commitment wurde angefordert, aber die Bestätigung kam
   wegen einer fehlerhaften Rückruf-Registrierung nie an.** Bestätigt:
   Transaction UID vorhanden, keine Rückmeldung nach dem Vielfachen der
   üblichen Wartezeit, und die Registrierung verweist nachweislich auf
   einen seit der Migration falschen Port.

### Ausschluss

C-STORE und MPPS sind durch positive, standardkonforme Evidenz
bestätigt (Success-Status, korrekte Endzustände) — beide scheiden als
Ursache aus, nicht weil sie "wahrscheinlich in Ordnung" sind, sondern
weil ihre jeweilige Evidenz sie explizit bestätigt. Storage Commitment
wurde nachweislich angefordert (Transaction UID vorhanden) — "nie
angefordert" ist damit ebenfalls ausgeschlossen. Übrig bleibt die
Rückmeldung selbst.

### Erste fehlerhafte Stelle

Die Storage-Commitment-Prüfung wurde gestellt und vermutlich auch
durchgeführt — aber die Registrierung der Rückruf-Gegenstelle
RAD-CALLBACK verweist auf einen falschen Port. Das Archiv kann das
`N-EVENT-REPORT` deshalb nie zustellen. Für den Requestor sieht das
identisch aus wie "nie bestätigt", obwohl weder C-STORE noch MPPS noch
die eigentliche Commitment-Prüfung selbst das Problem sind — die erste
fehlerhafte Stelle liegt im Rückweg, nicht im Vorgang.

### Betriebliche Maßnahme

Die Registrierung der Gegenstelle RAD-CALLBACK im Archiv auf den
korrekten Port (`8114`) aktualisieren, danach die ausstehende
Bestätigung erneut anfordern oder abwarten. Kein erneutes Senden der
Bilder, kein PACS-Neustart, keine Änderung an Patientendaten — keiner
dieser Eingriffe betrifft die tatsächlich gestörte Stelle.

### Was du mitnimmst

„Bilder im PACS", „MPPS abgeschlossen" und „Storage Commitment
bestätigt" sind drei getrennte Aussagen über denselben Workflow, nicht
Stufen, die automatisch aufeinander folgen. Ein `N-ACTION`-Erfolg
bestätigt nur die Annahme der Prüfanfrage — nicht, dass bereits ein
positives `N-EVENT-REPORT` vorliegt. Wer nur prüft, ob überhaupt eine
Anfrage gestellt wurde, übersieht genau diesen Fall: Der Vorgang wirkt
vollständig, das letzte Glied der Kette fehlt trotzdem.

### Verwandte Inhalte

Lektion 4.8 — Bilder da, aber Befund geht nicht raus
Lektion 2.6 — MPPS: Status-Rückmeldung der Modalität
Lektion 2.7 — Storage Commitment — hast du's wirklich?
