---
title: Der Befund kommt nicht an
scenario_title: Korrigiert im RIS, veraltet im KIS
---

## Briefing

Die Study zu Patient 6620 (Study Instance UID
`1.2.276.0.7230010.3.1.4.556291043`) ist vollständig im PACS vorhanden.
Das Befundsystem/RIS zeigt zum Auftrag ORD-40118 einen **korrigierten**
Befund. Im KIS erscheint für denselben Auftrag weiterhin der **vorherige
finale** Befund.

Im selben Log-Ausschnitt findest du außerdem eine Ergebnisnachricht zu
einem anderen Patienten. Deine Aufgabe: die richtigen Nachrichten
korrelieren und die erste Systemgrenze finden, an der die Correction
hängen geblieben ist.

Notiere währenddessen:

<!-- kein-beispiel -->
```text
Patient ID:    6620
Order:         ORD-40118
Accession:     A40118
Study UID:     1.2.276.0.7230010.3.1.4.556291043
```

## Hints

### h1

Ordne zuerst die vorhandenen Ergebnisnachrichten den richtigen
Aufträgen/Patienten zu. Nicht jede Nachricht im selben Log-Ausschnitt
gehört zum selben Fall — zeitliche Nähe ist kein Korrelationskriterium.

### h2

Vergleiche für die Nachricht, die im KIS fehlt, gezielt **Eingang** und
**Outbound-Transfer** im Interface-Engine-Log — genau wie bei einer
fehlenden Worklist, nur diesmal auf dem Rückweg.

### h3

Ein Outbound-Transfer mit `State: ERROR` und fehlendem
Routing-/Status-Mapping für den Ergebnisstatus zeigt auf die
Statusverarbeitung zwischen Interface Engine und KIS — nicht auf PACS
oder RIS.

## Write-up

### Erst korrelieren

```text
RES60110  OBR ORD-40118  OBX-11: F  11:20
RES60119  OBR ORD-40118  OBX-11: C  11:47
RES60122  OBR ORD-40125  OBX-11: F  11:31
```

**Was du daran abliest:** `RES60110` und `RES60119` gehören zusammen —
beide zu Auftrag ORD-40118 und Patient 6620. `RES60122` gehört zu einem
anderen Auftrag (ORD-40125) und ist für diesen Fall irrelevant, obwohl
sie zeitlich dazwischenliegt.

### Dann die Kette prüfen

```text
RES60110 → Outbound zu KIS: SENT
RES60119 → Outbound zu KIS: ERROR
  Grund: kein Routing-/Status-Mapping für Ergebnisstatus C
```

**Was du daran abliest:** Der finale Befund (`F`) kam beim KIS an — das
ist der Zustand, den der behandelnde Arzt noch sieht. Die Correction
(`C`) wurde vom RIS erzeugt und von der Interface Engine angenommen, blieb
aber beim ausgehenden Transfer zum KIS hängen, weil der Ergebnisstatus `C`
dort kein Routing-Ziel hatte.

### Was du mitnimmst

Weder die Bilder neu zu senden noch den Patienten manuell zu ändern noch
den Befundtext direkt im KIS zu überschreiben behebt die Ursache — keines
davon betrifft die eigentlich gestörte Stelle: das Status-/Routing-Mapping
zwischen Interface Engine und KIS für den Ergebnisstatus `C`. Erst wenn
dieses Mapping korrigiert ist und die Correction gezielt erneut übertragen
wird, zeigt das KIS denselben Befundstand wie das RIS.
