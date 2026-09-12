---
title: Die Serie, die zu groß ist
scenario_title: Ein Bild kommt an, das andere nicht — beide sollten
---

## Briefing

Eine Serie mit zwei Objekten soll ins Archiv: eine klassische Einzelschicht
und ein Enhanced-Volumen mit vielen Frames in einer Datei. Nach dem Senden
fehlt eines der beiden im Archiv — welches, und warum?

Deine Umgebung:

| System | Adresse | Was du darfst |
|---|---|---|
| Deine Workstation | 10.60.0.50 | Shell mit storescu — plus Befehlsvorlagen; die beiden Dateien liegen bereits lokal |
| Archiv | 10.60.0.10 | C-STORE annehmen; Konfiguration gesperrt (Herstellerzugang) |

Deine Aufgabe: Finde heraus, welches Objekt tatsächlich ankommt, und gib
seine Größe in Bytes als Flag ein.

```
$ ls
conformance-auszug.txt
      524288  klein.dcm
    78643200  gross.dcm
```
**Was du daran abliest:** `gross.dcm` ist mit 78.643.200 Byte (≈ 75 MiB)
über 100 Mal so groß wie `klein.dcm` — das Conformance Statement des
Archivs (`conformance-auszug.txt`) nennt bereits ein Limit von 50 MB je
Objekt.

Vorkenntnisse: Lektion 1.5. Rechne mit 15 Minuten.

## Hints

### h1

Sende beide Dateien einzeln, nicht als ein Vorgang — schau dir jede
Antwort für sich an.

### h2

Eine Ablehnung auf C-STORE-Ebene betrifft nur das eine Objekt, nicht die
ganze Association. Prüfe nach jedem Versuch, ob der Bestand im Archiv
gewachsen ist.

### h3

`storescu -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.60.0.10 104 klein.dcm`
kommt an. Seine Größe (aus `ls`) ist das Flag.

## Write-up

### Der Weg

1. Erst das große Objekt senden

```
$ storescu -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.60.0.10 104 gross.dcm
F: Store Failed, file: gross.dcm
F:   Status: 0xa700 (Refused: Out of Resources)
```
**Was du daran abliest:** Die Association selbst kommt zustande (kein
Fehler auf Verbindungsebene) — erst der C-STORE-Vorgang für dieses eine
Objekt wird mit einem echten DIMSE-Statuscode abgelehnt. `0xA7xx` ist in
PS3.7 Annex C als "Refused: Out of Resources" definiert, keine
herstellerspezifische Erfindung.

2. Dann das kleine Objekt senden

```
$ storescu -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.60.0.10 104 klein.dcm
$ echo $?
0
```
**Was du daran abliest:** Kein Fehler, stiller Erfolg — und der Bestand
im Archiv wächst jetzt tatsächlich um eine Study.

3. Flag: die Größe des angekommenen Objekts — `524288` Byte. Das ist kein
   Zufallswert: 512 × 512 Pixel × 2 Byte (16 Bit) = genau 524.288 Byte
   reine Pixeldaten, eine einzelne klassische CT-Schicht. `gross.dcm`
   dagegen bündelt 150 solcher Schichten in einem Enhanced-Volumen
   (150 × 524.288 Byte = 78.643.200 Byte) — über dem Limit.

### Was du mitnimmst

Eine Association kann stehen, während einzelne C-STORE-Anfragen darin
trotzdem scheitern — Verbindungserfolg und Objekterfolg sind zwei
verschiedene Ebenen. Ein Größenlimit ist keine Erfindung des Archivs,
sondern steht im Conformance Statement und lässt sich vorher nachlesen,
statt es beim Debuggen zu erraten. Und: "die Serie ist unvollständig"
heißt in der Praxis oft nicht "Netzwerkfehler", sondern "ein einzelnes
Objekt hat aus einem dokumentierten Grund nicht gepasst".

### Verwandte Inhalte

Lektion 1.5 — SCU und SCP, Called und Calling AE Title
Lektion 4.3 — „Nur manche Bilder kommen an"
