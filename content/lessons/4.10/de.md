---
title: 'Systematik: Logs, Wireshark-Filter, Reproduzieren'
teaser: 'Die Abschlusslektion des Tracks: ein Vorgehen, das auch bei Fehlerbildern trägt, die hier nicht vorkamen.'
objectives:
  - Einen Fehler zuverlässig reproduzieren, bevor man ihn erklärt
  - Die Schichten von außen nach innen eingrenzen, statt zu raten
  - Einen Mitschnitt gezielt filtern, statt ihn zu lesen
---

## Ein Ticket, das in keines der neun Fehlerbilder passt

Die letzten neun Lektionen dieses Tracks hatten je ein bekanntes
Fehlerbild. In der Praxis kommt aber irgendwann ein Ticket, das in
keines davon passt. Diese Lektion ist kein zehntes Fehlerbild —
sie ist das Vorgehen, mit dem man auch ein unbekanntes eingrenzt.

## Erst reproduzieren, dann erklären

Ein Fehler, den man nicht auslösen kann, lässt sich auch nicht
zuverlässig beheben — jede Änderung wäre Raten. Was man vor dem
ersten Erklärungsversuch festhält:

- **Genauer Zeitpunkt und genaue Beteiligte** (welches Gerät, welches
  Archiv, welche AE Titles) — nicht „das CT", sondern der konkrete
  Sendeauftrag.
- **Was genau beobachtet wurde** — eine Fehlermeldung wörtlich, nicht
  „es hat nicht funktioniert".
- **Ob es reproduzierbar ist** — beim nächsten Versuch, mit denselben
  Beteiligten, dasselbe Bild?

## Von außen nach innen eingrenzen

Dieselbe Reihenfolge wie in den vorigen Lektionen dieses Tracks, jetzt
als durchgehendes Werkzeug:

<!-- kein-beispiel -->
```
Netzweg     ->  Port    ->  Association  ->  Presentation Context  ->  einzelnes Objekt
(4.4)           (4.1)       (4.1)             (4.2)                    (4.3, 4.6)
```
Jede Stufe hat ihr eigenes Werkzeug aus den vorigen Lektionen —
`ping`/`echoscu` für die äußeren beiden, `echoscu -v` für die
Association, `storescu -d -cx` für den Presentation Context,
`findscu`/`dcmdump` für das einzelne Objekt. Wer eine Stufe überspringt,
rät bei der nächsten.

## Denselben Sendevorgang mit drei Verbositätsstufen ansehen

```
$ storescu -aec ORTHANC 127.0.0.1 4242 instance-0001.dcm
$ echo $?
0
```
**Was du daran abliest:** Die Standardeinstellung ist stumm — kein
einziges Zeichen Ausgabe bei Erfolg, nur der Exit-Code verrät etwas.
Für eine erste Reproduktion reicht das, für eine Diagnose nicht.

```
$ storescu -v -aec ORTHANC 127.0.0.1 4242 instance-0001.dcm
I: Requesting Association
I: Association Accepted
I: Sending file: instance-0001.dcm
I: Sending Store Request: MsgID 1, (CT)
I: Received Store Response (Status: 0x0000 - Success)
I: Releasing Association
```
**Was du daran abliest:** `-v` zeigt die sechs Schritte eines
Sendevorgangs als kurze Statuszeilen — genug, um zu sehen, *dass*
jeder Schritt passiert ist, aber nicht, *was* dabei ausgehandelt
wurde.

```
$ storescu -d -cx -aec ORTHANC 127.0.0.1 4242 instance-0001.dcm
D: Presentation Context:
D:   Context ID:        1 (Proposed)
D:     Abstract Syntax: =CT Image Storage
D:     Proposed Transfer Syntax:
D:       =Explicit VR Little Endian
D:   Context ID:        1 (Accepted)
D:     Abstract Syntax: =CT Image Storage
D:     Accepted Transfer Syntax: =Explicit VR Little Endian
I: Association Accepted
I: Sending Store Request: MsgID 1, (CT)
I: Received Store Response (Status: 0x0000 - Success)
```
**Was du daran abliest:** `-d` zeigt für denselben Vorgang deutlich
mehr Zeilen als `-v` (der volle Mitschnitt enthält zusätzlich einzelne
PDU- und DIMSE-Header, hier auf die Presentation-Context-Zeilen
konzentriert) — Proposed und Accepted stehen nebeneinander (Lektion
4.2), genau die Ebene, auf der sich Ablehnungen erklären. Für einen
ersten Überblick zu viel, für eine Presentation-Context-Frage genau
richtig. Die passende Verbositätsstufe zu wählen ist selbst ein
Eingrenzungsschritt — nicht immer gleich die ausführlichste nehmen.

## Einen Mitschnitt gezielt filtern

Lektion 1.0 hat den Wireshark-Anzeigefilter `dicom` bereits eingeführt.
Auf der Kommandozeile heißt dasselbe Werkzeug `tshark`:

```
$ tshark -i lo -f "tcp port 4242" -Y dicom
4   0.000318   127.0.0.1 → 127.0.0.1   DICOM 353  A-ASSOCIATE request STORESCU --> ORTHANC
6   0.000566   127.0.0.1 → 127.0.0.1   DICOM 258  A-ASSOCIATE accept  STORESCU <-- ORTHANC
8   0.003941   127.0.0.1 → 127.0.0.1   DICOM 146  P-DATA, C-STORE-RQ ID=1
10  0.054650   127.0.0.1 → 127.0.0.1   DICOM 568  P-DATA, CT Image Storage
13  0.062733   127.0.0.1 → 127.0.0.1   DICOM 224  P-DATA, C-STORE-RSP ID=1 (Success)
15  0.065415   127.0.0.1 → 127.0.0.1   DICOM 76   A-RELEASE request
16  0.065647   127.0.0.1 → 127.0.0.1   DICOM 76   A-RELEASE response
```
**Was du daran abliest:** `-f` filtert schon beim Mitschneiden (nur
Port 4242), `-Y dicom` beim Anzeigen zusätzlich auf den DICOM-Dissector
— zusammen bleibt von einem ganzen Netzwerkmitschnitt nur die
eigentliche Association übrig: Aufbau, ein C-STORE, sauberer Abbau.
Wiresharks Dissector erkennt sogar den Objekttyp im Datenpaket
(„P-DATA, CT Image Storage") ganz ohne eigenes Zutun.

```
$ tshark -r mitschnitt.pcap -Y "dicom && tcp.stream eq 1"
20  0.207611   127.0.0.1 → 127.0.0.1   DICOM 16195  A-ASSOCIATE request STORESCU --> ORTHANC
22  0.208827   127.0.0.1 → 127.0.0.1   DICOM 3947   A-ASSOCIATE accept  STORESCU <-- ORTHANC
24  0.216538   127.0.0.1 → 127.0.0.1   DICOM 236    P-DATA, C-STORE-RQ ID=1
26  0.257189   127.0.0.1 → 127.0.0.1   DICOM 568    P-DATA, CT Image Storage
29  0.257640   127.0.0.1 → 127.0.0.1   DICOM 224    P-DATA, C-STORE-RSP ID=1 (Success)
31  0.260051   127.0.0.1 → 127.0.0.1   DICOM 76     A-RELEASE request
32  0.260106   127.0.0.1 → 127.0.0.1   DICOM 76     A-RELEASE response
```
**Was du daran abliest:** Derselbe Mitschnitt enthielt zuvor auch noch
ein `echoscu` — `tcp.stream eq 1` grenzt auf genau die zweite
TCP-Verbindung ein, hier die des Sendevorgangs. Bei einem
Mitschnitt über mehrere Minuten mit Dutzenden Associations ist das
der Unterschied zwischen "lesbar" und "unlesbar" — gezielt filtern
statt vollständig lesen.

## Was man dokumentiert

Damit der Nächste nicht neu anfängt: die genaue Reproduktionsschritte,
die Verbositätsstufe, bei der sich das Problem zeigte, und die Ebene,
auf der die Eingrenzung stehen geblieben ist (nicht nur "Fehler
gefunden", sondern "Association kommt zustande, Presentation Context
für CT Image Storage wird abgelehnt").

## Im Alltag

| Frage | Werkzeug |
|---|---|
| Ist der Host überhaupt erreichbar? | `ping` |
| Steht die TCP-Verbindung? | `echoscu` gegen den Zielport |
| Kommt die Association zustande? | `echoscu -v` |
| Wird ein bestimmter Objekttyp/Kodierung abgelehnt? | `storescu -d -cx` |
| Was genau ging über die Leitung? | `tshark -Y dicom` |
| Zu viele Associations im Mitschnitt? | `tcp.stream eq N` |

## Stolperfallen

- **Zwei Dinge gleichzeitig ändern.** Wer AE Title und Transfer Syntax
  in einem Schritt korrigiert, weiß hinterher nicht, welche Änderung
  gewirkt hat — eine Variable pro Versuch.
- **Mitschnitt ohne Filter lesen.** Ein Mitschnitt ohne `-f`/`-Y` zeigt
  jedes Paket jedes Protokolls — die eigentliche Association geht darin
  unter.
- **Die ausführlichste Stufe zuerst wählen.** `-d` beantwortet nicht
  automatisch mehr relevante Fragen als `-v` — nur mehr Fragen
  insgesamt, von denen die meisten für das aktuelle Problem irrelevant
  sind.

## Selbstcheck

1. Ein Kollege ändert AE Title und Portnummer gleichzeitig, danach
   funktioniert es. Was weiß er jetzt über die ursprüngliche Ursache?
2. Ein Mitschnitt enthält 40 Associations. Wie kommst du gezielt an die
   eine, die dich interessiert?
3. Wann reicht `-v`, wann brauchst du `-d`?

*Wissenskarten (spaced repetition) folgen, sobald `meta.yml` ein
strukturiertes `quiz`-Feld mit Antwortschlüssel bekommt — siehe
`docs/content-todo.md`, Abschnitt P3.*

Werkzeuglage geprüft am: 2026-09-13
