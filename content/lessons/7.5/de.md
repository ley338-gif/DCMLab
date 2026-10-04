---
title: ACK, NACK, MLLP, Queues und Retries
teaser: „TCP verbunden“ und „ACK erhalten“ sind noch keine Aussage darüber, ob der Auftrag im Zielsystem verarbeitet wurde.
objectives:
  - Transporterfolg und Applikationserfolg voneinander trennen
  - Acknowledgement Codes und Error Queues in die Fehlersuche einbeziehen
  - Wiederholungen so beurteilen, dass keine Duplikatflut entsteht
---

## Das gefährlichste grüne Häkchen

Die Interface Engine zeigt: Verbindung erfolgreich. Nachricht gesendet. ACK empfangen.

Trotzdem fehlt der Auftrag.

Warum? Weil mindestens drei Ebenen auseinanderzuhalten sind:

<!-- kein-beispiel -->
```text
1. TCP / Transport
2. HL7-Nachricht wurde entgegengenommen
3. Fachliche Verarbeitung im Zielsystem
```

Ein Erfolg auf Ebene 1 beweist Ebene 3 nicht.

## MLLP: der Umschlag, nicht der Inhalt

HL7-v2-Nachrichten werden häufig über TCP mit MLLP-Rahmung transportiert. MLLP beantwortet nicht, ob `PID`, `ORC` oder `OBR` fachlich korrekt sind. Es sorgt dafür, dass Sender und Empfänger erkennen, wo eine Nachricht beginnt und endet.

**Was du daran abliest:** Ein offener Port und eine vollständige TCP-Übertragung bedeuten nur, dass der Umschlag angekommen ist.

## Das ACK lesen

Im Original Acknowledgement Mode, dem Normalfall bei IHE, steht in MSA-1 einer von drei Codes (IHE ITI TF-2, Tabelle C.2.3.1-2):

- `AA` — **Application Accept**: die antwortende Anwendung hat die Nachricht gemäß dem vereinbarten Interface-/ACK-Verhalten erfolgreich verarbeitet
- `AE` — **Application Error**: die Nachricht enthält Fehler; sie darf nicht unverändert erneut gesendet werden
- `AR` — **Application Reject**: die Nachricht wurde abgelehnt; liegt das nicht an einem ungültigen Wert im MSH (etwa einem unbekannten Nachrichtentyp), darf der Sender sie später erneut schicken — zum Beispiel, wenn der Empfänger gerade nicht verarbeiten konnte

> Im Enhanced-Mode-Acknowledgement gibt es zusätzlich Commit-ACKs wie `CA`/`CE`/`CR`, die die Übernahme in eine Warteschlange bestätigen, bevor die eigentliche fachliche Verarbeitung überhaupt läuft. Für den Einstieg reicht: Sie sind eine weitere, vorgelagerte Ebene — nicht dein Hauptlernziel hier.

Ein vereinfachtes ACK:

```text
MSH|^~\&|RIS|RAD|KIS|HAUS|20260916081600||ACK^O19^ACK|ACK7711|P|2.5.1
MSA|AA|MSG4711
```

**Was du daran abliest:** Der Message Type `ACK^O19^ACK` nennt als Trigger den der beantworteten Nachricht (`O19` aus `OMG^O19`), die Struktur einer allgemeinen Quittung heißt immer `ACK` (HL7 v2.5.1, Abschnitt 2.14.1). `MSA-2` wiederholt die Message Control ID der Originalnachricht. `AA` bestätigt, dass die antwortende Anwendung — hier das RIS — diese eine Nachricht gemäß dem vereinbarten Interface-/ACK-Verhalten erfolgreich verarbeitet hat. Das ist mehr als eine bloße Empfangsbestätigung. Es beweist aber noch nicht automatisch, dass nachgelagerte Systeme oder der gesamte klinische End-to-End-Workflow bereits den erwarteten Zustand erreicht haben.

Ein Fehlerfall:

```text
MSH|^~\&|RIS|RAD|KIS|HAUS|20260916081602||ACK^O19^ACK|ACK7712|P|2.5.1
MSA|AE|MSG4712
ERR||OBR^1^4^1^1|103^Table value not found^HL70357|E|||Unknown procedure code CTTHX2
```

**Was du daran abliest:** In beiden Fällen kam ein ACK zurück. Erst `MSA` und gegebenenfalls `ERR` sagen dir, ob die Nachricht akzeptiert wurde. Das `ERR`-Segment zeigt hier die Stelle (`ERR-2`: Segment `OBR`, erstes Vorkommen, Feld 4 — der Untersuchungscode), den Fehlercode aus HL7-Tabelle 0357 (`ERR-3`: `103` = Table value not found), die Schwere (`ERR-4`: `E` = Error) und einen Klartext für die Fehlersuche (`ERR-7`). Ein Fehlertext direkt in `MSA-3` ist seit v2.4 veraltet (HL7 v2.5.1, Abschnitt 2.15.8.3); IHE sieht ihn nicht vor (IHE RAD TF-2, Abschnitte 2.4.4.3 und 2.4.4.4).

## Vier Ebenen, die du nicht verwechseln darfst

```text
1. Transport / MLLP funktioniert
2. Empfänger antwortet
3. Application ACK beschreibt die Verarbeitung der Nachricht durch die antwortende Anwendung
4. Nachgelagerte Systeme bzw. der klinische End-to-End-Zustand können zusätzlich geprüft werden
```

**Was du daran abliest:** Ein `AA` auf Ebene 3 ist eine echte, positive Aussage — die antwortende Anwendung hat diese eine Nachricht erfolgreich verarbeitet. Das beantwortet aber nicht automatisch Ebene 4: Ob der Auftrag auch in allen nachgelagerten Systemen mit den richtigen Werten angekommen ist, bleibt bei kritischen Vorgängen eine eigene, zusätzliche Prüfung.

## Warum Message Control ID so wertvoll ist

Im Fehler-ACK steht:

<!-- kein-beispiel -->
```text
MSA|AE|MSG4712
```

`MSG4712` verweist auf die Control ID der ursprünglichen Nachricht.

Damit wird die Suche deterministisch:

```text
KIS Outbound:      MSG4712
Interface Engine:  MSG4712
RIS Inbound:       MSG4712
ACK:               reference MSG4712
```

**Was du daran abliest:** Du korrelierst Transportereignisse über die Nachricht, nicht über den Patientennamen.

## Queue ≠ Papierkorb

Eine robuste Schnittstelle hält fehlgeschlagene Nachrichten nachvollziehbar zurück.

Typischer Status:

```text
Queue: RAD_ORDERS_TO_RIS
Message: MSG4712
State: ERROR
Attempts: 3
Last error: OBR-4 code CTTHX2 not mapped
```

**Was du daran abliest:** Wiederholen ohne Ursachenbehebung produziert nur denselben Fehler erneut.

## Retries und Duplikate

Automatische Wiederholungen sind notwendig, wenn ein Ziel vorübergehend nicht erreichbar ist. Sie werden gefährlich, wenn der Sender nicht weiß, ob die vorherige Nachricht verarbeitet wurde.

Beispiel:

```text
08:16:00 send MSG4713
08:16:30 timeout waiting for ACK
08:16:35 retry MSG4713
08:16:36 ACK AA
```

**Was du daran abliest:** Der Timeout beweist nicht, dass die erste Nachricht nicht verarbeitet wurde. Ein System, das Wiederholungen nicht idempotent/duplikatsicher behandelt, kann denselben fachlichen Vorgang zweimal anlegen.

Ob sich ein Wiederholen überhaupt lohnt, sagt der ACK-Code: Nach `AE` kommt ohne Korrektur derselbe Fehler zurück. Nach `AR` kann ein späterer Versuch gelingen — außer der Grund liegt im MSH, etwa ein Nachrichtentyp, den der Empfänger nicht kennt (IHE ITI TF-2, Abschnitt C.2.3).

## Im Alltag heißt das

Bei jedem HL7-Fehler beantwortest du vier Fragen:

1. Wurde eine TCP-Verbindung hergestellt?
2. Wurde die Nachricht vollständig übertragen?
3. Welcher ACK-Code kam zurück?
4. Hat das Zielsystem den fachlichen Vorgang tatsächlich angelegt/geändert?

## Stolperfallen

- **„ACK erhalten = alles gut.“** Falsch. ACK-Inhalt lesen.
- **Error Queue blind reprocessen.** Erst Ursache beheben, dann gezielt wiederholen — nach `AE` verlangt IHE das ausdrücklich.
- **Timeout = Ziel hat nichts verarbeitet.** Nicht zwingend.
- **Nur Senderlogs lesen.** Der Empfänger kann eine Nachricht annehmen und anschließend intern verwerfen.

## Lab

Im Lab bewertest du eine Nachricht anhand ihres ACKs — und danach, was ein zweites ACK nach einer Korrektur tatsächlich beweist und was nicht.

## Selbstcheck

1. Welche Information im ACK ist wichtiger als die Tatsache, dass überhaupt ein ACK kam?
2. Warum kann ein Retry Duplikate erzeugen?
3. Welche ID verbindet Originalnachricht und ACK?

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Die Interface Engine zeigt „ACK empfangen". Was beweist das allein?**
1. Dass die Nachricht fachlich akzeptiert wurde
2. Dass Transport und Antwort stattfanden — nichts über die fachliche Verarbeitung
3. Dass kein Retry nötig ist
4. Dass der Empfänger den Auftrag bereits angelegt hat

**q2 — Welche Aussagen stimmen?** *(Mehrfachauswahl)*
1. MLLP sorgt für die Rahmung der Nachricht, nicht für ihre fachliche Prüfung
2. MSA-1 = AA bedeutet einen Application Error
3. Ein Timeout beweist nicht zwingend, dass die erste Nachricht nicht verarbeitet wurde
4. Unkontrollierte Retries können denselben fachlichen Vorgang doppelt anlegen

**q3 — Ein ACK enthält `MSA|AE|MSG4712` und `ERR||OBR^1^4^1^1|103^Table value not found^HL70357|E`. Was ist das Problem?**
1. Die TCP-Verbindung ist abgebrochen
2. Der Wert in OBR-4, der Untersuchungscode, ist dem Empfänger nicht bekannt
3. Das PID-Segment fehlt
4. Die Nachricht kam doppelt an

**q4 — Welcher MSA-1-Code sagt im Original Mode: abgelehnt, ein späterer erneuter Versuch kann aber gelingen, wenn der Grund nicht im MSH liegt? Nur der Code.** *(Freitext)*

**q5 — Nach `MSA|AE|…` wiederholt die Engine die unveränderte Nachricht dreimal automatisch. Was ist zu erwarten?**
1. Spätestens der dritte Versuch geht durch
2. Dreimal derselbe Fehler — nach `AE` hilft nur eine Korrektur
3. Der Empfänger übernimmt die Nachricht beim zweiten Mal ohne Prüfung
4. Die Wiederholungen bleiben folgenlos, weil der Empfänger sie verwirft

---

Normstellen geprüft am 04.10.2026: HL7 v2.5.1 Abschnitt 2.14.1 (ACK), HL7-Tabellen 0008 (Acknowledgment Code), 0357 (Message Error Condition) und 0516 (Error Severity); IHE RAD TF-2 Rev. 23.0, Abschnitte 2.4.4.3 (MSA) und 2.4.4.4 (ERR); IHE ITI TF-2, Abschnitt C.2.3 (Acknowledgement Modes).
