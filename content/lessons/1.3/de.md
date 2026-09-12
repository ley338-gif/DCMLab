---
title: Tags, VR und was in einer Zeile steckt
teaser: Eine Zeile aus einem Dump hat fünf Bestandteile. Wer sie liest, braucht kein Wörterbuch mehr.
objectives:
  - Eine Dump-Zeile in ihre Bestandteile zerlegen
  - Standard-Tags von privaten unterscheiden
  - Aus VR und Value Multiplicity ableiten, was ein Wert sein darf
---

## „Schauen Sie mal in 0008,103E"

Der Support des Herstellers sagt diesen Satz, als wäre es eine Wegbeschreibung. Für ihn ist es das auch. Du legst auf und hast eine Zahl, die aussieht wie eine Fehlermeldung.

Nach dieser Lektion ist sie eine Adresse — und die Zeile, die dort steht, liest du wie einen Satz.

## Die Anatomie einer Zeile

Das hier ist eine einzige Zeile aus `dcmdump`. Fünf Bestandteile, immer in derselben Reihenfolge:

```
(0008,103e) LO [Thorax  1.0  B70f]                      #  18, 1 SeriesDescription
 └───┬────┘ └┬┘ └───────┬───────┘                          └┬┘ └┬┘ └──────┬─────┘
   Tag      VR        Wert                                Länge VM    Keyword
```

| Teil | Was er sagt |
|---|---|
| **Tag** | Die Adresse: Group und Element, je vier Hexziffern |
| **VR** | Value Representation — welcher Art der Wert ist |
| **Wert** | Der Inhalt, in eckigen Klammern |
| **Länge** | Wie viele Bytes der Wert belegt |
| **VM** | Value Multiplicity — wie viele Einzelwerte darin stecken |
| **Keyword** | Der Klarname. **Steht nicht in der Datei** — er kommt aus dem Wörterbuch des Werkzeugs |

Der letzte Punkt überrascht viele: In der Datei stehen nur Tag, VR, Länge und Wert. Dass daraus `SeriesDescription` wird, ist eine Leistung von `dcmdump`, nicht der Datei. Ein Tag, das dein Werkzeug nicht kennt, zeigt es trotzdem an — nur eben ohne Namen.

## Group und Element

Beide sind 16 Bit, geschrieben als vier Hexziffern. Die Group fasst thematisch zusammen:

| Group | Thema |
|---|---|
| `0002` | File Meta Information — der Kopf der Datei, nicht Teil des Datensatzes |
| `0008` | Allgemeines zur Identifikation: Modality, Datum, Beschreibungen, SOP-Angaben |
| `0010` | Patient |
| `0018` | Aufnahmeparameter |
| `0020` | Beziehungen: Study, Series, Nummern, Positionen |
| `0028` | Bilddarstellung: Größe, Bittiefe, Fensterung |
| `7FE0` | Die Pixeldaten |

Eine Regel, die du dir merken solltest, weil sie im Alltag wirklich hilft:

> **Gerade Group = Standard. Ungerade Group = privat.**

Private Tags sind der Bereich, in dem Hersteller eigene Angaben unterbringen — Rekonstruktionsparameter, interne Kennungen, Protokollnamen. Sie sind erlaubt, sie sind verbreitet, und sie sind für dich nur lesbar, wenn du weißt, von wem sie stammen.

Damit sich zwei Hersteller im selben ungeraden Group nicht ins Gehege kommen, gibt es eine Reservierung: Ein Hersteller trägt seinen Namen in ein Element zwischen `0010` und `00FF` ein — den *Private Creator* — und besitzt damit einen Block von 256 Elementen darüber.

```
$ dcmdump +P "0029,0010" +P "0029,1008" daten/ct-thorax/0001.dcm
(0029,0010) LO [SIEMENS CSA HEADER]                     #  18, 1 PrivateCreator
(0029,1008) CS [IMAGE NUM 4]                            #  12, 1 Unknown Tag & Data
```

**Was du daran abliest:** Ohne die erste Zeile ist die zweite bedeutungslos — dieselbe Adresse kann bei einem anderen Hersteller etwas völlig anderes bedeuten. Wenn dir jemand ein privates Tag nennt, ist die Frage nach dem Private Creator immer die erste Rückfrage.

## VR — was der Wert sein darf

Die Value Representation ist ein Zwei-Buchstaben-Code. Sie bestimmt, welche Zeichen erlaubt sind, wie lang der Wert maximal sein darf und wie er zu lesen ist. Die, die dir ständig begegnen:

| VR | Bedeutung | Beispiel |
|---|---|---|
| `PN` | Person Name — **strukturiert**, nicht einfach Text | `MUSTER^ERIKA^^^` |
| `LO` / `SH` | Langer / kurzer Text (64 bzw. 16 Zeichen) | `CT Thorax nativ` |
| `CS` | Code String — Großbuchstaben, Ziffern, aus einer festen Liste | `ORIGINAL\PRIMARY\AXIAL` |
| `DA` / `TM` | Datum / Uhrzeit, **immer** `YYYYMMDD` bzw. `HHMMSS` | `20260911` |
| `UI` | Unique Identifier — nur Ziffern und Punkte | `1.2.840.10008.1.2.1` |
| `IS` / `DS` | Ganzzahl / Dezimalzahl, gespeichert **als Text** | `2` bzw. `1.0\1.0` |
| `US` / `UL` | Zahl, binär gespeichert | `512` |
| `SQ` | Sequence — eine Liste eingebetteter Datensätze | — |
| `OB` / `OW` | Rohdaten, byte- bzw. wortweise | Pixeldaten |

Zwei davon sind regelmäßig Ursache für Ärger.

**`PN` ist nicht einfach ein Name.** Das Zeichen `^` trennt fünf Bestandteile: Familienname, Vorname, mittlerer Name, Präfix, Suffix. `MUSTER^ERIKA` heißt „Erika Muster", nicht „Muster hoch Erika". Systeme, die das ignorieren und alles in ein Feld schreiben, produzieren Namen, die sich nicht mehr sortieren oder suchen lassen.

**`IS` und `DS` sind Text.** Eine Schichtdicke steht als Zeichenkette in der Datei. `1.0` und `1.00` sind für DICOM zwei verschiedene Werte, obwohl sie dieselbe Zahl meinen. Wer Werte vergleicht, ohne sie vorher in Zahlen zu wandeln, bekommt falsche Ergebnisse.

## Die Länge ist immer gerade

Das ist keine Kuriosität, sondern hat eine praktische Folge:

```
$ dcmdump +P PatientID +P Modality daten/ct-thorax/0001.dcm
(0008,0060) CS [CT]                                     #   2, 1 Modality
(0010,0020) LO [4711]                                   #   4, 1 PatientID
```

**Was du daran abliest:** Beide Werte haben von Haus aus gerade Länge. Ein Wert mit ungerader Zeichenzahl — etwa `4711A` mit fünf Zeichen — würde in der Datei auf sechs Bytes aufgefüllt: Textwerte mit einem Leerzeichen, UIDs mit einem Null-Byte. Das Werkzeug schneidet die Füllung beim Anzeigen weg. Wer Dateien byteweise vergleicht oder eigene Objekte baut, muss daran denken, sonst entstehen Datensätze, die kein Archiv annimmt.

## Mehrere Werte in einem Tag

Der Backslash trennt Einzelwerte. Die letzte Spalte im Dump zählt sie:

```
$ dcmdump +P ImageType +P PixelSpacing daten/ct-thorax/0001.dcm
(0008,0008) CS [ORIGINAL\PRIMARY\AXIAL]                 #  22, 3 ImageType
(0028,0030) DS [0.703125\0.703125]                      #  18, 2 PixelSpacing
```

**Was du daran abliest:** `ImageType` hat drei Werte, `PixelSpacing` zwei. Die Zahl hinter dem Komma ist die Value Multiplicity. Ein Tag mit VM 1, in dem plötzlich ein Backslash auftaucht, ist ein Fehler — und einer, der sich in Namensfeldern gern einschleicht, wenn ein System Werte zusammenbaut.

## Wenn du es weiterverarbeiten willst

`dcm2json` gibt denselben Inhalt maschinenlesbar aus, mit der VR im Klartext:

```
$ dcm2json daten/ct-thorax/0001.dcm | head -20
{
  "00080008": { "vr": "CS", "Value": [ "ORIGINAL", "PRIMARY", "AXIAL" ] },
  "00080016": { "vr": "UI", "Value": [ "1.2.840.10008.5.1.4.1.1.2" ] },
  "00080060": { "vr": "CS", "Value": [ "CT" ] },
  "00100010": { "vr": "PN", "Value": [ { "Alphabetic": "MUSTER^ERIKA" } ] },
  "00100020": { "vr": "LO", "Value": [ "4711" ] }
```

**Was du daran abliest:** Hier ist die Struktur sichtbar, die der Dump verbirgt — `ImageType` ist wirklich eine Liste aus drei Elementen, und der Personenname ist ein eigenes Objekt mit benannten Bestandteilen. Für Auswertungen über viele Dateien ist das die bessere Grundlage als Textausgabe mit `grep`.

## Woher die VR kommt, wenn sie nicht dasteht

Es gibt zwei Kodierungen. In **Explicit VR** steht die VR in der Datei, bei jedem Element. In **Implicit VR** steht sie nicht da — das lesende Programm muss sie aus dem Wörterbuch nachschlagen.

Daraus folgt: Bei Implicit VR ist ein privates Tag, dessen Hersteller du nicht kennst, **nicht interpretierbar**. Das Werkzeug weiß nicht einmal, ob es Text oder eine Zahl ist, und zeigt `UN` — Unknown. Welche Kodierung verwendet wird, sagt die Transfer Syntax, und die ist Thema von Lektion 1.7.

## Im Alltag heißt das

| Wenn jemand sagt … | … dann brauchst du |
|---|---|
| „Schauen Sie in Tag XXXX,YYYY" | `dcmdump +P "XXXX,YYYY" datei.dcm` |
| „Das Feld ist leer" | Prüfen, ob das Tag fehlt oder mit leerem Wert (Länge 0) vorhanden ist — das ist nicht dasselbe |
| „Der Name steht falsch drin" | `PN`-Struktur prüfen: Stehen die `^` an der richtigen Stelle? |
| „Die Werte unterscheiden sich" | Bei `IS`/`DS` erst in Zahlen wandeln, dann vergleichen |
| „Da ist ein Herstellerfeld" | Erst den Private Creator lesen, dann das Tag |

> ### Stolperfallen
>
> **„Der Keyword-Name steht in der Datei."**
> Nein. In der Datei stehen Zahlen. Namen kommen aus dem Wörterbuch des Werkzeugs — bei privaten Tags oft gar nicht. Zwei Programme können dasselbe Tag verschieden benennen.
>
> **„Tag fehlt ist dasselbe wie Tag leer."**
> Ist es nicht. Ein fehlendes Pflichtattribut lässt ein Archiv das Objekt ablehnen; ein vorhandenes Attribut mit leerem Wert ist in vielen Fällen zulässig. Im Dump ist der Unterschied die Zeile, die da ist oder nicht.
>
> **„Datumsfelder sind lokal formatiert."**
> `DA` ist immer `YYYYMMDD`, ohne Punkte. `11.09.2026` in einem DA-Feld ist ein Fehler, auch wenn manche Anzeige es klaglos schluckt.
>
> **„Ungerade Group heißt kaputt."**
> Heißt privat. Völlig normal und erlaubt — nur eben nur mit Private Creator lesbar.
>
> **„Ich lösche einfach das störende Tag."**
> Manche Tags sind Pflicht, manche hängen voneinander ab, und einige sind Schlüssel der Hierarchie aus Lektion 1.2. Entfernen kann ein Objekt unbrauchbar machen. Was man wie ändert, steht in Track 4.

## Dein Lab

Im Lab **Wo steht das?** bekommst du drei Fragen zu einer Datei — welcher Rekonstruktionskern, welche Schichtdicke, welches Aufnahmedatum — und keine Tag-Nummern dazu. Du sollst die Werte finden und dabei entscheiden, ob es sich um Standard- oder Herstellerangaben handelt.

## Selbstcheck

<details>
<summary>Du siehst `(0029,1010) UN` in einem Dump. Was weißt du, und was nicht?</summary>

Du weißt: Group 0029 ist ungerade, also ein privates Tag. `UN` heißt, dass dein Werkzeug die Art des Werts nicht kennt. Was du nicht weißt, ist die Bedeutung — dafür brauchst du den Private Creator, also das Tag `(0029,0010)` bis `(0029,00FF)` mit dem Herstellernamen. Ohne den ist der Wert nicht interpretierbar.
</details>

<details>
<summary>Zwei Dateien haben die Schichtdicke `1.0` und `1.00`. Sind sie gleich?</summary>

Als Zahl ja, als DICOM-Wert nein. `DS` wird als Text gespeichert, und die beiden Zeichenketten sind verschieden. Beim Vergleichen — etwa in Skripten oder bei Abfragen — muss man sie erst in Zahlen wandeln, sonst findet man Unterschiede, wo fachlich keine sind.
</details>

<details>
<summary>Warum kann die Länge eines Werts nie ungerade sein?</summary>

Weil DICOM Elemente auf gerade Bytegrenzen auffüllt: Textwerte mit einem Leerzeichen, UIDs mit einem Null-Byte. Die Werkzeuge schneiden die Füllung beim Anzeigen weg, deshalb sieht man es meistens nicht. Beim Erzeugen eigener Objekte oder beim byteweisen Vergleich muss man daran denken.
</details>

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Was sagt dir eine ungerade Group-Nummer?**
1. Das Tag ist beschädigt
2. Das Tag ist privat, also herstellerspezifisch
3. Das Tag gehört zu den Pixeldaten
4. Das Tag ist optional

**q2 — Welche Aussagen stimmen?** *(Mehrfachauswahl)*
1. Der Keyword-Name steht mit in der Datei
2. `IS` und `DS` werden als Text gespeichert
3. Der Backslash trennt mehrere Werte in einem Tag
4. `PN` besteht aus bis zu fünf durch `^` getrennten Bestandteilen

**q3 — Wie lautet das Format eines `DA`-Werts?** *(Freitext)*

---

**Als Nächstes:** [1.4 — UIDs](../1.4/) nimmt sich die Sorte Wert vor, die du in 1.2 schon benutzt hast, ohne zu wissen, woher sie kommt.
