---
title: Patient, Study, Series, Instance
teaser: Die Hierarchie steht in keiner Datenbank. Sie steckt in jeder einzelnen Datei — und genau deshalb kann sie kaputtgehen.
objectives:
  - Die vier Ebenen und ihre Schlüssel auseinanderhalten
  - Erklären, warum ein Archiv die Hierarchie aus den Objekten ableitet
  - Aus einem Ordner ablesen, wie viele Studies und Series darin liegen
---

## „Die Untersuchung ist doppelt drin"

Anruf aus der Radiologie: Eine Thorax-CT von heute Morgen steht zweimal in der Liste. Gleicher Patient, gleiche Uhrzeit, gleiches Gerät. Einmal mit 12 Bildern, einmal mit 48.

Niemand hat zweimal gescannt. Trotzdem sind es für das Archiv zwei Untersuchungen.

Um zu verstehen, wie das passieren kann — und es passiert häufig —, musst du wissen, dass ein PACS seine Ordnung nicht verwaltet, sondern **errechnet**.

## Vier Ebenen

```
   PATIENT          Muster, Erika · ID 4711
      │
      └── STUDY     CT Thorax nativ · 11.09.2026 07:04 · Auftrag A2609110017
             │
             ├── SERIES   2 · CT · Thorax 1.0 B70f      12 Bilder
             │      └── INSTANCE  1, 2, 3 … 12
             │
             └── SERIES   3 · CT · Thorax 5.0 B31f      48 Bilder
                    └── INSTANCE  1, 2, 3 … 48
```

| Ebene | Was es in der Wirklichkeit ist | Der Schlüssel |
|---|---|---|
| **Patient** | Ein Mensch | `PatientID` (0010,0020) |
| **Study** | Ein Untersuchungstermin, ein Auftrag | `StudyInstanceUID` (0020,000D) |
| **Series** | Eine Aufnahme oder eine Rekonstruktion daraus | `SeriesInstanceUID` (0020,000E) |
| **Instance** | Ein einzelnes Objekt, meist ein Bild | `SOPInstanceUID` (0008,0018) |

Daneben gibt es für jede Ebene Angaben, die für Menschen gemacht sind und **nichts identifizieren**: `PatientName`, `StudyDescription`, `AccessionNumber`, `SeriesNumber`, `SeriesDescription`, `InstanceNumber`. Sie sind für die Anzeige da. Verlassen kannst du dich nur auf die UIDs — mehr dazu in Lektion 1.4.

**Series ist die Ebene, die am meisten missverstanden wird.** Ein einziger Scan erzeugt selten eine Serie. Das CT rekonstruiert dieselben Rohdaten in dünnen und dicken Schichten, im Weichteil- und im Knochenkern, vielleicht noch eine Übersichtsaufnahme dazu — und jede Rekonstruktion ist eine eigene Serie mit eigener UID. Wenn die MTRA von „der Untersuchung" spricht, meint sie die Study; wenn der Hersteller von „der Serie" spricht, meint er eine davon.

## Die eigentliche Pointe: Es gibt keine Ordner

Das hier ist der Satz, um den sich die ganze Lektion dreht:

> **Jedes einzelne Objekt trägt seine komplette Abstammung in sich.**

In jeder der 60 Dateien steht die PatientID, die StudyInstanceUID, die SeriesInstanceUID und die eigene SOPInstanceUID. Das Archiv legt keine Struktur an, in die es Dateien einsortiert — es nimmt jedes Objekt einzeln entgegen, liest diese vier Werte und **gruppiert danach**.

```
$ dcmdump +P PatientID +P StudyInstanceUID +P SeriesInstanceUID +P SOPInstanceUID \
          daten/ct-thorax/0001.dcm
(0008,0018) UI [1.2.276.0.7230010.3.1.4.8323329.11150.1757580901.3] #  50, 1 SOPInstanceUID
(0010,0020) LO [4711]                                   #   4, 1 PatientID
(0020,000d) UI [1.2.276.0.7230010.3.1.2.8323329.11150.1757580901.1] #  50, 1 StudyInstanceUID
(0020,000e) UI [1.2.276.0.7230010.3.1.3.8323329.11150.1757580901.2] #  50, 1 SeriesInstanceUID
```

**Was du daran abliest:** Diese eine Datei weiß selbst, zu wem sie gehört, zu welcher Untersuchung und zu welcher Serie. Du könntest sie auf einen USB-Stick legen, drei Jahre liegen lassen und in ein fremdes Archiv schicken — sie würde sich dort wieder an genau dieselbe Stelle einsortieren.

Das ist die große Stärke des Formats. Und es ist die Quelle der hässlichsten PACS-Probleme, weil dieselbe Eigenschaft rückwärts gilt: **Ändert sich ein Schlüssel in einer Datei, wandert diese Datei woandershin.**

## Zurück zur doppelten Untersuchung

Jetzt lässt sich der Anruf vom Anfang beantworten. Zähl nach, was im Ordner liegt:

```
$ for f in daten/ct-thorax/*.dcm; do dcmdump +P StudyInstanceUID "$f"; done | sort | uniq -c
     60 (0020,000d) UI [1.2.276.0.7230010.3.1.2.8323329.11150.1757580901.1] #  50, 1 StudyInstanceUID
```

**Was du daran abliest:** Alle 60 Dateien gehören zu einer Study — hier ist alles in Ordnung. Kämen zwei verschiedene UIDs heraus, hättest du die Erklärung für den Anruf gefunden: Die Modalität hat mitten in der Untersuchung eine neue StudyInstanceUID vergeben, und das Archiv hat daraus pflichtgemäß zwei Untersuchungen gemacht. Typische Auslöser sind ein Neustart der Konsole, ein nachträglich geänderter Auftrag oder eine zweite Rekonstruktion, die separat übertragen wurde.

Dieselbe Zählung eine Ebene tiefer sagt dir, wie viele Serien du vor dir hast:

```
$ for f in daten/ct-thorax/*.dcm; do dcmdump +P SeriesInstanceUID "$f"; done | sort | uniq -c
     12 (0020,000e) UI [1.2.276.0.7230010.3.1.3.8323329.11150.1757580901.2] #  50, 1 SeriesInstanceUID
     48 (0020,000e) UI [1.2.276.0.7230010.3.1.3.8323329.11150.1757580901.4] #  50, 1 SeriesInstanceUID
```

**Was du daran abliest:** Zwei Serien, 12 und 48 Bilder — dünne und dicke Schichten. Wer nur „die Untersuchung ist unvollständig" gemeldet bekommt, kann mit dieser einen Zeile prüfen, ob eine ganze Serie fehlt oder einzelne Bilder.

## Dasselbe aus Sicht des Archivs

Das Archiv beantwortet Fragen auf genau diesen Ebenen. Es zählt dabei selbst mit:

```
$ findscu -v -S -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242 \
          -k QueryRetrieveLevel=STUDY -k PatientID=4711 \
          -k StudyDescription -k NumberOfStudyRelatedSeries -k NumberOfStudyRelatedInstances

I: Find Response: 1 (Pending)
I: (0008,0052) CS [STUDY]                               #   6, 1 QueryRetrieveLevel
I: (0008,1030) LO [CT Thorax nativ]                     #  16, 1 StudyDescription
I: (0010,0020) LO [4711]                                #   4, 1 PatientID
I: (0020,1206) IS [2]                                   #   2, 1 NumberOfStudyRelatedSeries
I: (0020,1208) IS [60]                                  #   2, 1 NumberOfStudyRelatedInstances
I: Received Final Find Response (Success)
```

**Was du daran abliest:** Das Archiv sieht dieselben zwei Serien und 60 Objekte wie deine Zählung im Ordner. Weichen die Zahlen voneinander ab, weißt du sofort, auf welcher Seite etwas fehlt — und das ist bei „da fehlen Bilder"-Tickets die erste nützliche Information überhaupt.

Fragst du dieselbe Study eine Ebene tiefer ab, bekommst du die beiden Serien einzeln — das ist genau die zweistufige Abfrage aus deinem Lab. Welche Abfrageebenen es gibt und welche Schlüssel sie verlangen, steht in Lektion 2.3.

## Im Alltag heißt das

Bei jeder Meldung sortierst du zuerst, auf welcher Ebene das Problem sitzt. Das entscheidet, wo du überhaupt suchst:

| Meldung | Ebene | Woran es meistens liegt |
|---|---|---|
| „Der Patient ist doppelt angelegt" | Patient | Zwei verschiedene PatientIDs, oft Ambulanz gegen stationär |
| „Die Untersuchung ist doppelt / gesplittet" | Study | Zwei StudyInstanceUIDs für einen Termin |
| „Eine Serie fehlt" | Series | Übertragung abgebrochen, oder die Rekonstruktion wurde nie gesendet |
| „Einzelne Bilder fehlen" | Instance | Einzelne C-STORE abgelehnt — Log der Gegenstelle prüfen |
| „Falscher Name, aber richtige Bilder" | Patient/Study | Coercion-Regel im Archiv, siehe 4.6 |

Die Zuordnung spart den häufigsten Irrweg: stundenlang die Netzwerkverbindung prüfen, obwohl eine Ebene tiefer eine UID nicht passt.

> ### Stolperfallen
>
> **„Der Patientenname identifiziert den Patienten."**
> Tut er nicht. Identifikator ist die `PatientID`. Namen ändern sich, werden falsch geschrieben, und zwei Menschen können gleich heißen. Für das Archiv sind zwei Objekte mit gleicher ID derselbe Patient — auch wenn die Namen abweichen.
>
> **„Eine Untersuchung ist eine Serie."**
> Ein CT-Thorax liefert routinemäßig zwei bis fünf Serien, weil jede Rekonstruktion eine eigene ist. Wer „die Serie" sagt, muss dazusagen, welche.
>
> **„Die Modalität steht auf der Study."**
> Sie steht auf der **Series**. Deshalb kann eine Study gemischt sein — ein PET/CT enthält PT- und CT-Serien in derselben Untersuchung. Auf Study-Ebene gibt es dafür `ModalitiesInStudy`, eine Zusammenfassung, kein Original.
>
> **„Das Archiv hat die Struktur angelegt, also kann ich sie dort reparieren."**
> Die Struktur ist abgeleitet. Viele Archive bieten zwar Werkzeuge zum Zusammenführen und Verschieben an — die schreiben aber in Wahrheit die Schlüssel in den Objekten um. Was harmlos klingt, ist ein Eingriff in jedes einzelne Bild. Mehr dazu in 4.5 und 4.6.
>
> **„Gleicher Patient, gleicher Tag, gleiches Gerät — also eine Study."**
> Nein. Nur die StudyInstanceUID entscheidet. Zwei Untersuchungen am selben Vormittag sind zwei Studies, und zwei UIDs für denselben Termin sind zwei Studies. Die Uhrzeit hat damit nichts zu tun.

## Dein Lab

Im Lab **Zwei Ebenen tiefer** bekommst du einen Ordner mit gemischten Objekten und die Meldung, es fehle etwas. Deine Aufgabe: sagen, wie viele Patienten, Studies und Series darin liegen — und welche Ebene tatsächlich unvollständig ist.

Du brauchst dafür nur die Zählschleife aus dieser Lektion, einmal je Ebene.

## Selbstcheck

<details>
<summary>Eine Untersuchung erscheint im PACS zweimal, mit zusammen der richtigen Bildzahl. Was prüfst du zuerst?</summary>

Ob die Objekte zwei verschiedene `StudyInstanceUID` tragen. Eine Zählschleife über den Ordner beantwortet das in einer Zeile. Sind es zwei UIDs, hat die Modalität mitten in der Untersuchung eine neue vergeben — typisch nach einem Neustart der Konsole oder wenn ein Auftrag nachträglich geändert wurde. Das Archiv hat sich dann korrekt verhalten; der Fehler liegt vorher.
</details>

<details>
<summary>Warum kann ein Archiv eine Datei nicht „in den falschen Ordner legen"?</summary>

Weil es keine Ordner gibt. Das Archiv liest aus jedem Objekt dessen vier Schlüssel und gruppiert danach. Eine Datei landet immer genau dort, wohin ihre eigenen Werte zeigen. Wenn sie an der falschen Stelle auftaucht, stimmen ihre Werte nicht — nicht die Ablage.
</details>

<details>
<summary>Ein PET/CT erzeugt PT- und CT-Bilder. Sind das zwei Studies?</summary>

Nein, normalerweise eine Study mit Serien verschiedener Modality. `Modality` sitzt auf der Series-Ebene, nicht auf der Study. Auf Study-Ebene fasst `ModalitiesInStudy` zusammen, was darin vorkommt — als Zusammenfassung, nicht als eigenständige Angabe.
</details>

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Welcher Wert identifiziert eine Untersuchung eindeutig?**
1. AccessionNumber
2. StudyDescription
3. StudyInstanceUID
4. StudyDate zusammen mit StudyTime

**q2 — Welche Aussagen stimmen?** *(Mehrfachauswahl)*
1. Jedes Objekt enthält die Schlüssel aller übergeordneten Ebenen
2. `Modality` steht auf der Series-Ebene
3. Ein CT-Scan erzeugt immer genau eine Serie
4. Der Patientenname ist der Identifikator des Patienten

**q3 — Mit welchem Schlüssel unterscheidest du zwei Serien derselben Untersuchung?** *(Freitext)*

---

**Als Nächstes:** [1.3 — Tags, VR und was in einer Zeile steckt](../1.3/) zerlegt die Zeilen, die du in dieser Lektion schon gelesen hast, in ihre Bestandteile.
