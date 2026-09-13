---
title: "C-ECHO: der Ping, der keiner ist"
teaser: Ein Klick, eine grüne Meldung — und trotzdem beweist er mehr als jedes ICMP-Ping.
objectives:
  - Erklären, was ein C-ECHO tatsächlich prüft — und was nicht
  - Den Ablauf einer Verification-Association nachvollziehen
  - Ein erfolgreiches C-ECHO von den Grenzen dieses Erfolgs unterscheiden
---

## „Ist die Verbindung überhaupt da?"

Bevor irgendetwas anderes geprüft wird, steht immer dieselbe Frage
zuerst: lebt die Gegenstelle überhaupt? {{term:c-echo}} beantwortet
genau das — aber anders, als der Name „Verification" vermuten lässt,
ist es weit mehr als ein Netzwerk-Ping.

## Eine eigene Association, nicht bloß ein Signal

Ein ICMP-Ping prüft nur, ob ein Host antwortet. Ein C-ECHO baut eine
vollständige DICOM-Association auf — mit genau einer SOP Class,
Verification, real `1.2.840.10008.1.1`:

```
$ echoscu -v -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242
I: Requesting Association
I: Association Accepted
I: Sending Echo Request: MsgID 1
I: Received Echo Response (Status: 0x0000 - Success)
I: Releasing Association
```
**Was du daran abliest:** Fünf eigene Schritte, nicht nur eine
Antwort — Verbindungsaufbau, Aushandlung, Anfrage, Antwort mit
explizitem DIMSE-Status `0x0000`, Abbau. Jeder dieser Schritte kann
für sich genommen scheitern; ein Ping kennt keinen davon.

## Auf der Leitung: genau eine Presentation Context

```
$ echoscu -d -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242
D: Presentation Contexts:
D:   Context ID:        1 (Proposed)
D:     Abstract Syntax: =VerificationSOPClass
D:     Proposed Transfer Syntax(es):
D:       =LittleEndianImplicit
...
D:   Context ID:        1 (Accepted)
D:     Abstract Syntax: =VerificationSOPClass
D:     Accepted Transfer Syntax: =LittleEndianImplicit
```
**Was du daran abliest:** Ein C-ECHO schlägt nur die eine Verification
SOP Class vor — kein Bildtyp, keine Transfer Syntax für Pixeldaten.
Genau das ist die Grenze dieses Diensts: Er verhandelt nichts über das,
was später tatsächlich übertragen werden soll.

```
$ tshark -i lo -f "tcp port 4242" -Y dicom
4   0.000286   127.0.0.1 → 127.0.0.1   DICOM 353 A-ASSOCIATE request MEINE-WS --> ORTHANC
6   0.000426   127.0.0.1 → 127.0.0.1   DICOM 258 A-ASSOCIATE accept  MEINE-WS <-- ORTHANC
8   0.003854   127.0.0.1 → 127.0.0.1   DICOM 146 P-DATA, C-ECHO-RQ ID=1
10  0.003982   127.0.0.1 → 127.0.0.1   DICOM 144 P-DATA, C-ECHO-RSP ID=1 (Success)
12  0.006452   127.0.0.1 → 127.0.0.1   DICOM 76  A-RELEASE request
13  0.006510   127.0.0.1 → 127.0.0.1   DICOM 76  A-RELEASE response
```
**Was du daran abliest:** Der komplette Zyklus in sechs Paketen —
Aufbau, ein einziges Anfrage/Antwort-Paar, Abbau. Kein Bildobjekt,
kein Presentation-Context-Streit — dafür sorgt die Beschränkung auf
genau eine SOP Class.

## Wenn die Gegenstelle gar nicht antwortet

```
$ echoscu -aet MEINE-WS -aec ORTHANC 127.0.0.1 104
E: Association request failed: unable to connect to remote
E: TCP Initialisation Error: [Errno 111] Connection refused
```
**Was du daran abliest:** Auf Port 104 hört in dieser Spielwiese
niemand — eine reine *Transport*-Aussage, die noch vor jeder
DICOM-Aushandlung entsteht. Ein Fehler auf dieser Ebene sagt nichts
über AE Titles oder SOP Classes aus.

## Was ein grünes C-ECHO nicht beweist

Diese Spielwiese akzeptiert jeden Called AE Title — verifiziert:
`echoscu -aec VOELLIG-FALSCH` gegen dieselbe Instanz war ebenso
erfolgreich wie oben. Eine AE-Title-basierte Ablehnung lässt sich hier
deshalb nicht live erzeugen (dieselbe, bereits in ADR 0017
dokumentierte Großzügigkeit); real reproduzierbar ist sie in Node
„Silent CT". Und selbst ein AE-Title-Match beweist nur, dass *diese*
eine SOP Class akzeptiert wurde — nichts darüber, ob ein bestimmter
Bildtyp später durchgeht. Dafür verhandelt jede Übertragung ihre
eigene Presentation Context (Lektion 1.8, vertieft in Lektion 4.2).

## Im Alltag

| Frage | Womit prüfen |
|---|---|
| Lebt die Gegenstelle überhaupt? | `echoscu -v` |
| Stimmen die AE Titles? | Ein erfolgreiches C-ECHO — aber nur gegen ein Archiv, das tatsächlich prüft |
| Wird ein bestimmter Bildtyp akzeptiert? | Nicht mit C-ECHO zu beantworten — eigene Presentation Context nötig (Lektion 4.2) |

## Stolperfallen

- **„C-ECHO grün, also geht alles."** Ein erfolgreiches C-ECHO prüft
  ausschließlich die Verification SOP Class — nichts über
  Bildübertragung.
- **C-ECHO mit einem Ping verwechseln.** Ein Ping prüft nur
  TCP/IP-Erreichbarkeit, kein DICOM-Protokoll, keine AE Titles.
- **Auf Großzügigkeit von Testservern verlassen.** Übungsarchive
  akzeptieren oft mehr als ein echtes Haus-Archiv — was hier grün ist,
  ist im Haus nicht automatisch grün.

## Selbstcheck

1. Was genau prüft ein erfolgreiches C-ECHO — und was ausdrücklich
   nicht?
2. Ein `echoscu`-Aufruf scheitert mit „Connection refused". Auf
   welcher Ebene liegt dieser Fehler?
3. Warum reicht C-ECHO allein nicht aus, um zu wissen, ob ein
   bestimmter Bildtyp später akzeptiert wird?
