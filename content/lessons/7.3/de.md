---
title: ADT — Patientenidentität und Bewegungen
teaser: Wenn Name, Patient ID oder Fallkontext auseinanderlaufen, wirkt der Fehler später wie ein PACS-Problem — entstanden ist er oft viel früher.
objectives:
  - ADT-Ereignisse als Änderungen am Patienten-/Fallkontext verstehen
  - Identität, Aufenthalt und Auftragskontext voneinander trennen
  - Einen Merge-/Stammdatenfehler systemübergreifend eingrenzen
---

## „Im PACS gibt es den Patienten zweimal“

Das ist eine typische PACS-Meldung — aber noch keine PACS-Ursache.

Ein Patient kann im KIS korrigiert, zusammengeführt oder unter einer anderen Identifier-Domäne geführt worden sein. Wenn diese Änderung nicht alle nachgelagerten Systeme erreicht, entstehen unterschiedliche Wahrheiten.

## ADT ist Ereignisverkehr

ADT steht für Admit, Discharge, Transfer. In der Praxis umfasst die Nachrichtenfamilie viele Ereignisse rund um Patientenstammdaten und Aufenthaltskontext.

Für dich ist wichtiger als das Auswendiglernen von Triggernummern:

> Eine ADT-Nachricht sagt einem anderen System, dass sich der administrative Zustand eines Patienten oder Falls geändert hat.

**Beispiel: ADT^A08 — Update Patient Information.** Eine Stammdatenkorrektur, keine Zusammenführung:

```text
MSH|^~\&|KIS|HAUS|RIS|RAD|20260916090000||ADT^A08^ADT_A01|ADT88421|P|2.5.1
EVN||20260916090000
PID|1||4711^^^KLINIK^MR||MUSTER^ERIKA-SOPHIE||19750314|F
PV1|1|O|RAD^ANMELDUNG
```

**Was du daran abliest:** Die Nachricht transportiert eine Änderung an bestehenden Stammdaten — hier korrigiert sich der Vorname. Die Patient ID bleibt gleich, es entsteht keine neue Identität; eine Patient ID lässt sich mit A08 auch gar nicht ändern, dafür ist A40 da (IHE RAD TF-2, Abschnitt 4.12). `EVN` sagt, wann das Ereignis erfasst wurde, und ist in beiden Beispielen dieser Lektion Pflicht. Die Nachrichtenstruktur heißt `ADT_A01`, weil A08 dieselbe Struktur wie die Aufnahme A01 verwendet (HL7-Tabelle 0354; HL7 v2.5.1, Abschnitt 3.3.8). Ob das empfangende RIS eine Änderung automatisch übernimmt, hängt vom vereinbarten Profil und seiner Konfiguration ab.

> Welcher Trigger-Event genau verwendet wird, in welcher HL7-Version und mit welcher lokalen Feldbelegung, ist installationsabhängig. Für den Betrieb zählt die vereinbarte Schnittstellenspezifikation, nicht ein allgemeines Lehrbuchbeispiel.

## Patient ist nicht Fall ist nicht Untersuchung

Trenne gedanklich vier Ebenen:

<!-- kein-beispiel -->
```text
Patient
  └─ Encounter / Fall
       └─ Order / Auftrag
            └─ DICOM Study
```

Ein Patient kann mehrere Fälle haben. Ein Fall kann mehrere Aufträge enthalten. Ein Auftrag kann eine oder mehrere bildgebungsbezogene Studien nach sich ziehen.

**Was du daran abliest:** Wenn zwei PACS-Studien „beim falschen Fall“ erscheinen, muss nicht die Patient ID falsch sein. Es kann genauso gut die Zuordnung zum Encounter/Order-Kontext sein.

## Die gefährliche Abkürzung: Name + Geburtsdatum

Zwei Systeme zeigen:

```text
RIS:
Patient ID: 4711
Name:       MUSTER^ERIKA
DOB:        19750314

PACS:
Patient ID: 4711
Name:       MUSTER^ERIKA-SOPHIE
DOB:        19750314
```

**Was du daran abliest:** Das sieht nach derselben Person aus, beweist aber nicht, dass beide Systeme dieselbe Identifier-Domäne meinen. Umgekehrt beweist ein abweichender Name nicht, dass es zwei Personen sind.

## Merge ist ein Workflow, kein String-Replace

Bei einer Patientenzusammenführung reicht es nicht, an einer Stelle die Patient ID zu ersetzen. Systeme müssen nachvollziehen, welche Identität führend ist, welche alte Identität ersetzt wurde und welche bereits erzeugten Untersuchungen betroffen sind.

**Beispiel: ADT^A40 — Merge Patient – Patient Identifier List.** Stark gekürzt:

```text
MSH|^~\&|KIS|HAUS|RIS|RAD|20260916094500||ADT^A40^ADT_A39|ADT88433|P|2.5.1
EVN||20260916094500
PID|1||4711^^^KLINIK^MR||MUSTER^ERIKA
MRG|4699^^^KLINIK^MR
```

**Was du daran abliest:** `PID-3` trägt die weiterhin gültige (überlebende) Patient ID, `MRG-1` die Identität, die aufgelöst wird. Nach HL7 wird die alte ID danach logisch nicht mehr verwendet; manche Systeme bewahren sie trotzdem auf, etwa für den Audit-Trail (HL7 v2.5.1, Abschnitt 3.3.40). Wer später noch Untersuchungen unter `4699` findet, muss nachvollziehen können, dass diese ID in `4711` aufgegangen ist.

> Auch hier gilt: Genaues Feldlayout, Version und ob ein Haus A40 überhaupt in dieser Form nutzt, richten sich nach dem lokalen Profil der Installation. In IHE Scheduled Workflow gehören A08 und A40 zur Transaktion Patient Update (RAD-12, IHE RAD TF-2 Abschnitt 4.12).

Für PACS-Administratoren bedeutet das:

1. Quelle des Merge-Ereignisses identifizieren
2. Zeitpunkt und Message Control ID finden
3. prüfen, ob RIS/Interface Engine die Nachricht akzeptiert haben
4. prüfen, wie das PACS Patientenreconciliation umsetzt
5. Änderungen nicht blind direkt in DICOM-Daten „reparieren“, ohne Workflow und Audit zu verstehen

## Im Alltag heißt das

Wenn „der Patient doppelt“ ist, sammelst du zuerst:

- beide Patient IDs
- Assigning Authorities / Issuer
- betroffene Accession Numbers
- Study Instance UIDs
- Zeitpunkt der Korrektur/des Merge
- ADT Message Control ID
- Audit-/Reconciliation-Ereignisse des PACS

Erst dann entscheidest du, ob der Fehler in der Quelle, im Transport, im Mapping oder in der PACS-Reconciliation liegt.

## Stolperfallen

- **Direkt im PACS umbenennen.** Das kann die sichtbare Oberfläche korrigieren und gleichzeitig die Systemkette weiter auseinanderziehen.
- **Nur die aktuelle KIS-Sicht betrachten.** Entscheidend ist, welche Nachricht zum damaligen Zeitpunkt an die nachgelagerten Systeme ging.
- **Merge und Update verwechseln.** Eine Stammdatenkorrektur (A08) und die Zusammenführung zweier Identitäten (A40) sind fachlich verschieden — und eine Patient ID ändert nur A40.
- **Issuer/Assigning Authority ignorieren.** Gerade bei mehreren Standorten oder angebundenen Einrichtungen ist das riskant.

## Selbstcheck

1. Warum ist ein doppelter Patient im PACS nicht automatisch ein PACS-Fehler?
2. Welche Ebenen liegen zwischen Patient und DICOM Study?
3. Welche Informationen brauchst du, bevor du eine Patientenreconciliation manuell anstößt?

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Im PACS erscheint ein Patient zweimal. Was folgt daraus zuerst?**
1. Das PACS hat einen Softwarefehler
2. Die Ursache kann in einer KIS-seitigen Korrektur oder einem Merge liegen, die nicht alle Systeme erreicht hat
3. Die Study Instance UID ist falsch vergeben
4. Der Fehler liegt zwingend im DICOM-Transport

**q2 — Welche Aussagen stimmen?** *(Mehrfachauswahl)*
1. Ein Patient kann mehrere Fälle/Encounter haben
2. Übereinstimmender Name und Geburtsdatum beweisen dieselbe Identifier-Domäne
3. Ein Merge ist mehr als das Ersetzen einer Patient ID an einer Stelle
4. Direktes Umbenennen im PACS kann die Systemkette weiter auseinanderziehen

**q3 — Eine A40-Nachricht enthält `PID|1||4711^^^KLINIK^MR||MUSTER^ERIKA` und `MRG|4699^^^KLINIK^MR`. Welche Patient ID gilt danach weiter?**
1. `4699`
2. `4711`
3. Beide gleichberechtigt
4. Keine — das Zielsystem vergibt eine neue

**q4 — Welches Segment trägt in einer A40-Nachricht die Patient ID, die aufgelöst wird? Nur das Kürzel.** *(Freitext)*

**q5 — Im KIS wurde die falsche Patient ID eines Patienten korrigiert. Mit welcher Nachricht erfährt das RIS davon nach IHE Scheduled Workflow?**
1. ADT^A08 — Update Patient Information
2. ADT^A40 — Merge Patient
3. OMG^O19 — neuer Auftrag
4. ACK — Bestätigung

---

Normstellen geprüft am 04.10.2026: HL7 v2.5.1 Abschnitte 3.3.8 (A08) und 3.3.40 (A40), HL7-Tabelle 0354 (Nachrichtenstrukturen); IHE RAD TF-2 Rev. 23.0, Abschnitt 4.12 (Patient Update, RAD-12).
