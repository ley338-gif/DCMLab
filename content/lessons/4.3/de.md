---
title: „Nur manche Bilder kommen an"
teaser: 'Der Teiltransfer ist der unangenehmste Fall: nichts wirkt kaputt, aber die Studie ist unvollständig.'
objectives:
  - Einen Teiltransfer als eigenes Fehlerbild erkennen, statt ihn als Netzwerkproblem zu behandeln
  - Eine nicht akzeptierte SOP Class als Ursache nachweisen
  - Größen- und Mengenlimits als zweite Ursachenklasse prüfen
---

## „Im Archiv fehlt ein Bild — aber wo?"

Ticket aus dem Befundungsraum: Eine Study wirkt unvollständig. Kein
Fehler in irgendeinem Log, kein rotes Symbol an der Modalität — die
Bilder, die da sind, sehen normal aus. Nur sind es zu wenige.

Das ist der unangenehmste Fall in diesem Track: Ein Teiltransfer sieht
von außen wie Erfolg aus, solange man nur die Erfolgsmeldungen zählt,
nicht die Bilder selbst.

## Der Trugschluss: „storescu hat nichts gemeldet, also ist alles da"

```
$ storescu -v -aec ORTHANC 127.0.0.1 4242 instance-0001.dcm
I: Requesting Association
I: Association Accepted
I: Sending file: instance-0001.dcm
I: Sending Store Request: MsgID 1, (CT)
I: Received Store Response (Status: 0x0000 - Success)
I: Releasing Association
```
**Was du daran abliest:** `Status: 0x0000 - Success` heißt: *dieses eine
Objekt* ist angekommen. Es heißt nichts über die anderen 59 Objekte
derselben Study — jeder `storescu`-Aufruf ist sein eigener,
unabhängiger Vorgang. Ein Sendeskript, das nur die eigenen
Erfolgsmeldungen zählt, kann eine unvollständige Study für vollständig
halten, wenn irgendwo unterwegs ein Aufruf schlicht ausgelassen wurde
— etwa weil ein Skript abbricht, ohne es zu melden, oder eine Datei
beim Kopieren übersehen wird.

## Nachweisen, statt der Meldung zu glauben

```
$ ls instance-*.dcm | wc -l
60
```
**Was du daran abliest:** Lokal liegen 60 Dateien — das ist die
erwartete Anzahl für diese Study (`ct-thorax-60`, siehe Lektion 1.2).
Ob wirklich alle 60 im Archiv angekommen sind, sagt diese Zeile aber
noch nicht — sie zeigt nur, was lokal vorhanden war, bevor gesendet
wurde.

```
$ findscu -S -k QueryRetrieveLevel=STUDY -k PatientID=4711 \
          -k StudyInstanceUID -k StudyDescription \
          -k NumberOfStudyRelatedInstances \
          -aec ORTHANC 127.0.0.1 4242
I: (0008,0052) CS [STUDY]                                  # 1 QueryRetrieveLevel
I: (0008,1030) LO [CT Thorax nativ]                        # 1 StudyDescription
I: (0010,0020) LO [4711]                                   # 1 PatientID
I: (0020,000D) UI [1.2.826.0.1.3680043.8.498.634114...]    # 1 StudyInstanceUID
I: (0020,1208) IS [59]                                     # 1 NumberOfStudyRelatedInstances
I: Find SCP Result: 0x0000 (Success)
```
**Was du daran abliest:** `NumberOfStudyRelatedInstances` ist ein
reales, vom Archiv selbst berechnetes Feld (PS3.4 C.6.2.1.1) — hier
`59` statt der erwarteten `60`. Das ist der eigentliche Nachweis eines
Teiltransfers: nicht ein Fehler irgendwo im Log, sondern eine Zahl, die
nicht zur Erwartung passt. Genau dieser Abgleich — lokale Anzahl gegen
`NumberOfStudyRelatedInstances` — ist der zuverlässige Weg,
Vollständigkeit zu prüfen, statt sich auf Erfolgsmeldungen zu
verlassen.

## Zwei weitere Ursachen, die dasselbe Bild erzeugen

Ein fehlendes Objekt sieht in der Zählung immer gleich aus — `59`
statt `60` — ganz gleich, warum es fehlt. Zwei reale, im Alltag
häufige Ursachen, die *nicht* an einem vergessenen Sendeaufruf liegen,
sondern daran, dass das Archiv ein Objekt aktiv ablehnt:

<!-- kein-beispiel -->
```
Das lässt sich in der aktuellen Spielwiese nicht live erzeugen: Orthanc
nimmt in diesem Aufbau jedes getestete Objekt an, unabhaengig von SOP
Class oder Groesse -- getestet mit einem echten Secondary-Capture-
Objekt (Screenshot-artiger Objekttyp, real: SOP Class UID
1.2.840.10008.5.1.4.1.1.7) und mit einem echten, rund 400 MB grossen
CT-Volumen. Beide wurden ohne jede Ablehnung gespeichert. Dieselbe
Grosszuegigkeit, die in Lektion 4.1 und 4.2 schon andere
Ablehnungsgruende unmoeglich gemacht hat (siehe dort), betrifft hier
die beiden folgenden Ursachen:

  SOP Class nicht registriert (PS3.8 Table 9-18, Result 3
  "abstract-syntax-not-supported"): ein Objekttyp, den das Archiv nicht
  kennt -- z. B. ein automatisch mitgespeicherter Screenshot neben den
  eigentlichen Bildern. Durchspielbar in Node "Teiltransfer".

  Groessenlimit (PS3.7 Annex C, Status 0xA7xx "Refused: Out of
  Resources"): ein einzelnes Objekt, das die vom Archiv akzeptierte
  Groesse ueberschreitet -- z. B. ein Enhanced-Volumen mit vielen
  Frames in einer Datei. Durchspielbar in Node "Die Serie, die zu
  gross ist".

Beide Nodes zeigen den jeweiligen echten Ablehnungsgrund einzeln --
siehe Lab unten.
```

## Im Alltag

| Symptom | Nächster Schritt |
|---|---|
| Study "sieht unvollständig aus" | Lokale Dateianzahl gegen `NumberOfStudyRelatedInstances` prüfen |
| Alle Sendeaufrufe meldeten Erfolg, trotzdem fehlt etwas | Nicht dem Sendelog vertrauen — das Archiv selbst befragen |
| Ein bestimmter Objekttyp fehlt regelmäßig (z. B. Screenshots) | SOP Class prüfen — Node „Teiltransfer" |
| Ein bestimmtes, besonders großes Objekt fehlt | Größenlimit prüfen — Node „Die Serie, die zu groß ist" |

## Stolperfallen

- **„Kein Fehler im Log heißt vollständig."** Jeder `storescu`-Aufruf
  ist unabhängig — ein Erfolg sagt nichts über andere Objekte derselben
  Study.
- **Instances mit Bildern verwechseln.** Multiframe-Objekte (ein
  Enhanced-Volumen) können mehrere Bilder in einer einzigen Instance
  bündeln — Instance-Anzahl und Bildanzahl sind nicht automatisch
  dasselbe.
- **Eine stille Ablehnung für einen Netzwerkfehler halten.** Die
  Association selbst funktioniert einwandfrei — abgelehnt wird nur das
  einzelne Objekt, mit einem eigenen, im Standard definierten Grund.

## Selbstcheck

1. Ein Sendeskript meldet für alle Dateien Erfolg. Wie prüfst du trotzdem,
   ob wirklich alles im Archiv angekommen ist?
2. `NumberOfStudyRelatedInstances` zeigt `59` statt `60`. Ist das allein
   schon ein Beweis für eine nicht akzeptierte SOP Class?
3. Nenne zwei reale, im Standard definierte Gründe, aus denen ein
   einzelnes Objekt abgelehnt werden kann, ohne dass die Association
   scheitert.

*Wissenskarten (spaced repetition) folgen, sobald `meta.yml` ein
strukturiertes `quiz`-Feld mit Antwortschlüssel bekommt — siehe
`docs/content-todo.md`, Abschnitt P3.*

Werkzeuglage geprüft am: 2026-09-13
