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

Drei Application-Acknowledgement-Codes stehen in MSA-1:

- `AA` — **Application Accept**: die antwortende Anwendung hat die Nachricht gemäß dem vereinbarten Interface-/ACK-Verhalten erfolgreich verarbeitet
- `AE` — **Application Error**: bei der Verarbeitung trat ein Fehler auf
- `AR` — **Application Reject**: die Nachricht wurde grundsätzlich abgelehnt

> Im Enhanced-Mode-Acknowledgement gibt es zusätzlich Commit-ACKs wie `CA`/`CE`/`CR`, die die Übernahme in eine Warteschlange bestätigen, bevor die eigentliche fachliche Verarbeitung überhaupt läuft. Für den Einstieg reicht: Sie sind eine weitere, vorgelagerte Ebene — nicht dein Hauptlernziel hier.

Ein vereinfachtes ACK:

```text
MSH|^~\&|RIS|RAD|KIS|HAUS|20260916081600||ACK^O01|ACK7711|P|2.5
MSA|AA|MSG4711
```

**Was du daran abliest:** `AA` bestätigt, dass die antwortende Anwendung — hier das RIS — diese eine Nachricht gemäß dem vereinbarten Interface-/ACK-Verhalten erfolgreich verarbeitet hat. Das ist mehr als eine bloße Empfangsbestätigung. Es beweist aber noch nicht automatisch, dass nachgelagerte Systeme oder der gesamte klinische End-to-End-Workflow bereits den erwarteten Zustand erreicht haben.

Ein Fehlerfall:

```text
MSH|^~\&|RIS|RAD|KIS|HAUS|20260916081602||ACK^O01|ACK7712|P|2.5
MSA|AE|MSG4712|Unknown procedure code CTTHX2
ERR|||OBR^4^1|103^Table value not found
```

**Was du daran abliest:** In beiden Fällen kam ein ACK zurück. Erst `MSA` und gegebenenfalls `ERR` sagen dir, ob die Nachricht akzeptiert wurde.

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
MSA|AE|MSG4712|...
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

## Im Alltag heißt das

Bei jedem HL7-Fehler beantwortest du vier Fragen:

1. Wurde eine TCP-Verbindung hergestellt?
2. Wurde die Nachricht vollständig übertragen?
3. Welcher ACK-Code kam zurück?
4. Hat das Zielsystem den fachlichen Vorgang tatsächlich angelegt/geändert?

## Stolperfallen

- **„ACK erhalten = alles gut.“** Falsch. ACK-Inhalt lesen.
- **Error Queue blind reprocessen.** Erst Ursache beheben, dann gezielt wiederholen.
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
