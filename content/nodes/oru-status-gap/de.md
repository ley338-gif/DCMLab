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

Ein ACK bezieht sich immer nur auf die Nachricht, zu der es gehört — die
Annahme einer früheren Nachricht sagt nichts über eine spätere aus. Prüfe
für die fehlende Nachricht getrennt: Wurde sie vom RIS erzeugt? Kam sie in
der Interface Engine an? Wurde sie von dort auch Richtung KIS
weitergeschickt?

### h3

Vergleiche die Routing-Konfiguration (`allowed_status`) der Route
Richtung KIS mit dem tatsächlichen Ergebnisstatus der fehlenden Nachricht.

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

### Dann die Evidenz getrennt sammeln — nicht raten

```text
RIS:                RES60119 / ORD-40118 / Status C erzeugt, 11:47
IE inbound:          RES60119 vorhanden, 11:47
Route RAD_RESULTS_TO_KIS: allowed_status = P,F
KIS outbound:        RES60110 vorhanden (11:20), RES60119 fehlt

RES60110 (Final)     → Outbound zu KIS: SENT, ACK: AA
RES60119 (Correction) → kein Outbound-/ACK-Eintrag
```

**Was du daran abliest:** Drei naheliegende, aber falsche Schlüsse lassen
sich mit dieser Evidenz sofort widerlegen: Das KIS kann die Correction
nicht „abgelehnt" haben — sie fehlt im KIS-Outbound-Log vollständig, sie
kam dort nie an. Das RIS hat auch nicht nur einen internen Status
geändert — die Interface Engine bestätigt den Eingang einer echten
Result-Nachricht. Und das `AA` für RES60110 sagt nichts über RES60119 aus,
denn ein ACK bezieht sich immer nur auf die Nachricht, zu der es gehört.

Übrig bleibt die Routing-Konfiguration: `allowed_status` der Route
`RAD_RESULTS_TO_KIS` enthält nur `P` und `F`, nicht `C`. Der finale Befund
(`F`) kam beim KIS an — das ist der Zustand, den der behandelnde Arzt noch
sieht. Die Correction (`C`) wurde vom RIS erzeugt und von der Interface
Engine angenommen, blieb aber beim ausgehenden Transfer zum KIS hängen,
weil die Routing-Regel ihren Ergebnisstatus nicht durchlässt. Die erste
fehlerhafte Systemgrenze liegt damit zwischen Interface Engine und KIS, im
Outbound-/Routing-Schritt.

### Was du mitnimmst

Weder die Bilder neu zu senden noch den Patienten manuell zu ändern noch
den Befundtext direkt im KIS zu überschreiben behebt die Ursache — keines
davon betrifft die eigentlich gestörte Stelle: die Routing-Konfiguration
zwischen Interface Engine und KIS für den Ergebnisstatus `C`. Erst wenn
`allowed_status` korrigiert ist und die Correction gezielt erneut
übertragen wird, zeigt das KIS denselben Befundstand wie das RIS. Und ein
positives ACK für eine ältere Nachricht ersetzt nie die Prüfung der
konkreten Nachricht, um die es gerade geht.
