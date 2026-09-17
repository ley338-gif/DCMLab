---
title: Eine HL7-v2-Nachricht lesen
teaser: MSH, PID, PV1, ORC, OBR, OBX — aus scheinbarem Zeichensalat wird in wenigen Minuten eine lesbare Ereigniskette.
objectives:
  - Segmente, Felder, Komponenten und Wiederholungen unterscheiden
  - MSH, PID, PV1, ORC, OBR und OBX fachlich einordnen
  - Aus einer Nachricht die für das Troubleshooting wichtigsten Identifikatoren herausziehen
---

## Der Logeintrag, vor dem viele zuerst zurückschrecken

<!-- kein-beispiel -->
```text
MSH|^~\&|KIS|HAUS|RIS|RAD|20260916081500||ORM^O01|MSG0004711|P|2.5
PID|1||4711^^^KLINIK^MR||MUSTER^ERIKA||19750314|F
PV1|1|O|RAD^ANMELDUNG
ORC|NW|ORD93821^KIS|||
OBR|1|ORD93821^KIS||CTTHORAX^CT Thorax nativ|||20260916083000
```

Auf den ersten Blick wirkt das wie eine CSV-Datei mit zu vielen Sonderzeichen. Tatsächlich ist die Struktur sehr regelmäßig.

## Zeilen sind Segmente

Die ersten drei Zeichen sagen, welche Art Information folgt:

- `MSH` — Message Header: Absender, Empfänger, Zeitpunkt, Nachrichtentyp, Control ID, Version
- `PID` — Patient Identification
- `PV1` — Patient Visit: Fall-/Aufenthaltskontext
- `ORC` — Common Order: Status und Identität eines Auftrags
- `OBR` — Observation Request: angeforderte Untersuchung/Leistung
- `OBX` — Observation/Result: Ergebnisdaten

**Was du daran abliest:** Du musst eine Nachricht nicht vollständig kennen. Für den Anfang reicht die Frage: „Welche Segmente sind vorhanden und welche fachliche Ebene beschreiben sie?“

## `|`, `^`, `~`, `\`, `&`

HL7 v2 benutzt Trennzeichen. In einer typischen ER7-Nachricht ist `|` der Feldtrenner und `^` trennt Komponenten innerhalb eines Feldes.

Nimm diese Zeile:

<!-- kein-beispiel -->
```text
PID|1||4711^^^KLINIK^MR||MUSTER^ERIKA||19750314|F
```

Zerlegt:

<!-- kein-beispiel -->
```text
Segment: PID
PID-1: 1
PID-3: 4711^^^KLINIK^MR
PID-5: MUSTER^ERIKA
PID-7: 19750314
PID-8: F
```

Und `PID-3` noch einmal:

```text
4711 ^   ^   ^ KLINIK ^ MR
  │              │      │
  │              │      └─ Identifier Type
  │              └──────── Assigning Authority
  └─────────────────────── Identifier
```

**Was du daran abliest:** `4711` allein ist nicht immer genug. Die ausstellende Domäne/Assigning Authority kann entscheiden, ob zwei identisch aussehende IDs tatsächlich denselben Patienten meinen.

## Warum MSH besonders ist

Der Header trägt unter anderem die Kommunikationsbeziehung:

<!-- kein-beispiel -->
```text
MSH|^~\&|KIS|HAUS|RIS|RAD|20260916081500||ORM^O01|MSG0004711|P|2.5
```

Für die Administration interessieren dich zuerst:

```text
Sending Application: KIS
Sending Facility:    HAUS
Receiving Application: RIS
Receiving Facility:    RAD
Message Type:          ORM^O01
Message Control ID:    MSG0004711
Version:               2.5
```

**Was du daran abliest:** Die Message Control ID ist ein hervorragender Suchanker für Interface-Engine-Logs. Sie ist etwas anderes als Patient ID, Order Number oder Accession Number.

## Ein Feld ist nicht automatisch „die Wahrheit“

Angenommen, zwei Nachrichten enthalten:

<!-- kein-beispiel -->
```text
PID|1||4711^^^KLINIK-A^MR||MUSTER^ERIKA
```

und

```text
PID|1||4711^^^KLINIK-B^MR||MUSTER^ERIKA
```

**Was du daran abliest:** Beide tragen den numerischen Wert `4711`, aber unterschiedliche Assigning Authorities. Wer beim Abgleich nur die Ziffern betrachtet, kann Patienten falsch zusammenführen.

## Die fünf Suchanker für den PACS-Admin

Wenn du ein Problem über mehrere Systeme verfolgst, notiere dir:

1. Message Control ID
2. Patient Identifier plus Assigning Authority
3. Placer/Filler Order Number
4. Accession Number, wenn im Profil vorhanden
5. später Study Instance UID

Damit kannst du einen Fall über Interface Engine, RIS und PACS korrelieren.

## Im Alltag heißt das

Du musst keine HL7-Nachricht auswendig dekodieren. Ein guter Erstblick ist:

<!-- kein-beispiel -->
```text
MSH → Wer spricht mit wem? Welcher Typ? Welche Control ID?
PID → Welcher Patient und aus welcher Identifier-Domäne?
PV1 → Welcher Fall/Aufenthalt?
ORC → Was passiert mit dem Auftrag?
OBR → Welche Untersuchung ist gemeint?
OBX → Welches Ergebnis wird übertragen?
```

## Stolperfallen

- **Feldnummern blind aus einer anderen Schnittstelle übernehmen.** HL7-Version und lokales Profil prüfen.
- **Patientenname als Primärschlüssel behandeln.** Namen ändern sich, sind nicht eindeutig und können unterschiedlich geschrieben werden.
- **Message Control ID mit Order Number verwechseln.** Die Control ID identifiziert die Nachricht, nicht die medizinische Leistung.
- **Leere Felder ignorieren.** Ein leerer Wert kann fachlich entscheidend sein, etwa wenn ein lokales Mapping darauf angewiesen ist.

## Selbstcheck

1. Was ist der Unterschied zwischen einem Segment und einem Feld?
2. Warum ist `4711^^^KLINIK-A^MR` aussagekräftiger als nur `4711`?
3. Welchen Identifier würdest du zuerst verwenden, um dieselbe HL7-Nachricht in mehreren Interface-Engine-Logs zu suchen?

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Welches Segment enthält Sending/Receiving Application, Message Type und Message Control ID?**
1. PID
2. MSH
3. OBR
4. ORC

**q2 — Welche Aussagen stimmen?** *(Mehrfachauswahl)*
1. `^` trennt innerhalb eines HL7-v2-Feldes Komponenten
2. Die Message Control ID identifiziert dieselbe medizinische Leistung wie die Order Number
3. `4711^^^KLINIK^MR` enthält zusätzlich zur ID eine Assigning-Authority-Angabe
4. Zwei identische numerische Patient-IDs aus unterschiedlichen Assigning Authorities meinen nicht zwangsläufig denselben Patienten
