---
title: „Studie ist gesplittet / doppelt"
teaser: Eine Untersuchung, zwei Einträge — hier entscheidet eine einzige UID darüber, was der Befunder sieht.
objectives:
  - Split und Dublette als zwei verschiedene Fehlerbilder auseinanderhalten
  - Die Study Instance UID als entscheidendes Merkmal prüfen
  - Typisches Fehlverhalten von Modalitäten benennen, das zum Split führt
---

## „Die Untersuchung steht zweimal in der Liste"

Ticket aus der Anmeldung: Eine Patientin taucht in der Worklist-Ansicht
zweimal mit derselben Untersuchung auf — gleicher Name, gleiche
Beschreibung, gleicher Tag. Niemand hat zweimal untersucht.

Zwei völlig verschiedene technische Ursachen erzeugen genau dasselbe
äußere Bild — und nur eine einzige, unsichtbare Angabe entscheidet,
welche es ist: die Study Instance UID.

## Split und Dublette sind nicht dasselbe

- **Split:** Dieselbe Untersuchung bekommt **zwei verschiedene** Study
  Instance UIDs — das Archiv sieht sie als zwei völlig eigenständige
  Studies. Häufigste Ursache: das Gerät wird mitten in der Untersuchung
  neu gestartet oder die Untersuchung ohne Worklist-Bezug neu
  begonnen, und generiert dabei eine neue UID, statt die alte
  weiterzuverwenden.
- **Dublette:** Dieselben Objekte werden **unter derselben** Study
  Instance UID ein zweites Mal eingespielt — z. B. weil ein Sendeauftrag
  versehentlich wiederholt wird.

Diese beiden Fälle verhalten sich am Archiv fundamental
unterschiedlich, wie sich in der Spielwiese direkt zeigen lässt.

## Eine Dublette verändert den Bestand nicht

```
$ findscu -S -k QueryRetrieveLevel=STUDY -k PatientID=5522 \
          -k StudyInstanceUID -k StudyDescription \
          -k NumberOfStudyRelatedInstances \
          -aec ORTHANC 127.0.0.1 4242
I: (0008,1030) LO [CT Abdomen nativ]                        # 1 StudyDescription
I: (0010,0020) LO [5522]                                    # 1 PatientID
I: (0020,000D) UI [1.2.826.0.1.3680043.8.498.726868...]     # 1 StudyInstanceUID
I: (0020,1208) IS [3]                                       # 1 NumberOfStudyRelatedInstances
I: Find SCP Result: 0x0000 (Success)
```
**Was du daran abliest:** Eine Study mit drei Instances — das Ergebnis,
nachdem dieselben drei Dateien **zweimal** per `storescu` gesendet
wurden. Kein zweiter Eintrag, keine sechs Instances. Das Archiv
erkennt jede Instance an ihrer eigenen SOP Instance UID (Lektion 1.4)
und ersetzt beim erneuten Einspielen einfach dasselbe Objekt — eine
Dublette beim Senden erzeugt am Archiv gar keinen sichtbaren Fehler.

## Ein Split erzeugt einen echten zweiten Eintrag

```
$ findscu -S -k QueryRetrieveLevel=STUDY -k PatientID=5522 \
          -k StudyInstanceUID -k StudyDescription \
          -k NumberOfStudyRelatedInstances \
          -aec ORTHANC 127.0.0.1 4242
I: Find SCP Response: 1 - 0xFF00 (Pending)
I: (0008,1030) LO [CT Abdomen nativ]                        # 1 StudyDescription
I: (0010,0020) LO [5522]                                    # 1 PatientID
I: (0020,000D) UI [1.2.826.0.1.3680043.8.498.654687...]     # 1 StudyInstanceUID
I: (0020,1208) IS [2]                                       # 1 NumberOfStudyRelatedInstances
I: Find SCP Response: 2 - 0xFF00 (Pending)
I: (0008,1030) LO [CT Abdomen nativ]                        # 1 StudyDescription
I: (0010,0020) LO [5522]                                    # 1 PatientID
I: (0020,000D) UI [1.2.826.0.1.3680043.8.498.726868...]     # 1 StudyInstanceUID
I: (0020,1208) IS [3]                                       # 1 NumberOfStudyRelatedInstances
I: Find SCP Result: 0x0000 (Success)
```
**Was du daran abliest:** Zwei vollständig getrennte Studies —
derselbe Patient, dieselbe Beschreibung, aber zwei unterschiedliche
`StudyInstanceUID`-Werte. Für das Archiv sind das zwei verschiedene
Untersuchungen, nicht eine doppelt gezählte. Genau das ist ein Split:
Er entstand hier dadurch, dass die zweite Aufnahme mit einer frisch
generierten UID gesendet wurde, statt die bereits vorhandene
weiterzuverwenden.

## Die UID direkt in der Datei nachsehen

```
$ dcmdump +P StudyInstanceUID +P PatientName +P PatientID instance-0001.dcm
(0020,000d) UI [1.2.826.0.1.3680043.8.498.726868...] #  64, 1 StudyInstanceUID
(0010,0010) PN [WEBER^SOPHIE]                        #  12, 1 PatientName
(0010,0020) LO [5522]                                #   4, 1 PatientID
```
**Was du daran abliest:** Die UID steht direkt im Objekt, nicht nur in
der Archiv-Antwort — sie lässt sich schon vor dem Senden prüfen.
`PatientName` und `PatientID` sind bei beiden Aufnahmen identisch;
allein das beweist noch nichts über Split oder Dublette, denn
Namensgleichheit sagt nichts über die UID aus.

```
$ dcm2json instance-0001.dcm
{
  ...
  "0020000D": {
    "vr": "UI",
    "Value": [
      "1.2.826.0.1.3680043.8.498.261919..."
    ]
  },
  ...
}
```
**Was du daran abliest:** Dieselbe Information als JSON — praktisch,
wenn man zwei Dateien nicht von Auge, sondern per Skript vergleichen
will (z. B. `dcm2json a.dcm | jq '."0020000D".Value[0]'` gegen dieselbe
Abfrage für die zweite Datei). Für einen einzelnen Vergleich reicht
`dcmdump`, für automatisierte Prüfungen ist `dcm2json` die
maschinenlesbare Variante desselben Werts.

## Im Alltag

| Symptom | Ebene | Was das Archiv selbst zeigt |
|---|---|---|
| Zwei Einträge, zwei UIDs | Split | `findscu` liefert zwei getrennte Responses |
| Ein Eintrag, unverändert nach erneutem Senden | Dublette | `findscu` liefert weiterhin nur eine Response, Instance-Zahl bleibt gleich |

Ein Split lässt sich nicht durch erneutes Senden reparieren — die
beiden Studies bleiben zwei Studies, bis sie aktiv zusammengeführt
werden (Merge, siehe Lektion 4.6). Das ist ein Eingriff in den
Datenbestand, kein Sendevorgang, und deshalb bewusst nicht Teil dieser
Lektion.

## Stolperfallen

- **UID-Präfix als Herstellermerkmal fehlgedeutet.** Der Anfang einer
  UID (z. B. `1.2.826.0.1.3680043.8.498`) identifiziert die
  UID-Registrierungsstelle, die sie ausgegeben hat — nicht den
  Hersteller des Geräts, das sie benutzt. Zwei Geräte verschiedener
  Hersteller können UIDs mit demselben Präfix erzeugen, wenn sie
  dieselbe Softwarebibliothek verwenden.
- **Namensgleichheit als Beweis nehmen.** Gleicher Patientenname,
  gleiches Datum, gleiche Beschreibung beweisen nichts über die
  UID — genau deshalb sieht ein Split von außen wie ein harmloser
  Zufall aus.
- **Eine Dublette für einen Fehler halten.** Ein zweites Mal gesendete,
  identische Objekte verändern den Bestand am Archiv nicht — das ist
  kein Systemversagen, sondern korrektes Verhalten.

## Lab

Im Node **„Geteilte Studie"** bekommst du zwei Einträge, die auf den ersten Blick nach Duplikat aussehen. Wende genau das Split-vs-Dublette-Modell aus dieser Lektion an, um zu entscheiden, was wirklich vorliegt.

## Selbstcheck

1. `findscu` liefert für einen Patienten zwei getrennte Study-Einträge
   mit unterschiedlichen `StudyInstanceUID`-Werten. Split oder Dublette?
2. Dieselben drei Dateien wurden versehentlich zweimal gesendet.
   Wie viele Einträge zeigt `findscu` danach — und warum?
3. Ein Kollege sagt: „Die beiden UIDs fangen unterschiedlich an, das
   müssen verschiedene Hersteller sein." Stimmt das?

*Wissenskarten (spaced repetition) folgen, sobald `meta.yml` ein
strukturiertes `quiz`-Feld mit Antwortschlüssel bekommt — siehe
`docs/content-todo.md`, Abschnitt P3.*

### Verwandte Inhalte

Lektion 1.4 — UIDs, inklusive Node „Zwillinge" (zwei ähnlich
aussehende Studies anhand der Accession Number unterscheiden)
Lektion 4.6 — „Falscher Patient" (Merge und Move als Korrektur)

Werkzeuglage geprüft am: 2026-09-13
