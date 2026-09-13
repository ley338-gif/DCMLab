---
title: Transfer Syntax und Kompression
teaser: Die Verbindung steht, die Namen stimmen — und trotzdem kommt nichts an. Meistens liegt es hier.
objectives:
  - Eine Transfer Syntax aus einer UID erkennen und benennen
  - Erklären, warum eine Verbindung für manche Objekte funktioniert und für andere nicht
  - Ein Objekt in eine andere Kodierung überführen und die Folgen prüfen
---

## „Seit dem Update kommt vom Ultraschall nichts mehr"

Die Verbindung ist unverändert. AE Titles stimmen, C-ECHO ist grün, das Gerät meldet keinen Fehler. Trotzdem landet seit Dienstag kein Bild mehr im Archiv.

Was sich geändert hat: Das Gerät komprimiert jetzt. Und das Archiv nimmt diese Kompression nicht an.

Das ist kein exotischer Fall, sondern einer der drei häufigsten Ausfälle nach Wartungen. Er ist unangenehm, weil alles, was man üblicherweise prüft, in Ordnung aussieht.

## Was eine Transfer Syntax festlegt

Ein Datensatz aus Lektion 1.3 ist eine Liste von Tags mit Werten. Wie diese Liste tatsächlich in Bytes geschrieben wird, ist damit noch nicht gesagt. Genau das legt die Transfer Syntax fest, und zwar drei Dinge auf einmal:

| | |
|---|---|
| **Byte-Reihenfolge** | Little Endian oder Big Endian |
| **VR-Kodierung** | Steht die VR in der Datei (explicit) oder muss sie nachgeschlagen werden (implicit)? |
| **Kompression der Pixeldaten** | keine, oder eines von mehreren Verfahren |

Jede Kombination hat eine eigene UID — eine von den well-known UIDs aus Lektion 1.4.

## Die, die du kennen musst

**Unkomprimiert:**

| UID | Name | Wo du sie triffst |
|---|---|---|
| `1.2.840.10008.1.2` | Implicit VR Little Endian | Der Notnagel. Jede DICOM-Anwendung **muss** sie können |
| `1.2.840.10008.1.2.1` | Explicit VR Little Endian | Der Normalfall in Dateien und auf der Leitung |
| `1.2.840.10008.1.2.2` | Explicit VR Big Endian | Zurückgezogen. Nur noch in Altbeständen |

**Komprimiert** (Auswahl):

| UID | Name | Verlustfrei? |
|---|---|---|
| `1.2.840.10008.1.2.4.50` | JPEG Baseline | nein |
| `1.2.840.10008.1.2.4.70` | JPEG Lossless, SV1 | ja |
| `1.2.840.10008.1.2.4.80` | JPEG-LS Lossless | ja |
| `1.2.840.10008.1.2.4.90` | JPEG 2000 Lossless | ja |
| `1.2.840.10008.1.2.4.91` | JPEG 2000 | nein |
| `1.2.840.10008.1.2.5` | RLE Lossless | ja |

Die Nummer hinter `1.2.840.10008.1.2` ist also nicht kosmetisch — sie sagt dir sofort, ob du ein Kompressionsproblem vor dir hast. Alles mit `.4.` ist JPEG-Familie, `.5` ist RLE.

**Implicit VR Little Endian ist die Rückfallebene.** Jede konforme Anwendung muss sie beherrschen. Wenn zwei Systeme sich auf sonst nichts einigen können, ist das der gemeinsame Nenner — und der Grund, warum manche Verbindungen unerwartet doch funktionieren, nur langsamer und größer.

## In der Datei nachsehen

```
$ dcmdump +P TransferSyntaxUID +P Rows +P Columns +P BitsAllocated \
          daten/ct-thorax/0001.dcm
(0002,0010) UI [1.2.840.10008.1.2.1]                    #  20, 1 TransferSyntaxUID
(0028,0010) US 512                                      #   2, 1 Rows
(0028,0011) US 512                                      #   2, 1 Columns
(0028,0100) US 16                                       #   2, 1 BitsAllocated
```

**Was du daran abliest:** Ein 512×512-Bild mit 16 Bit je Pixel, unkomprimiert in Explicit VR Little Endian. Die Pixeldaten belegen damit rund 512 KB — rechne nach: 512 × 512 × 2 Byte. Bei 60 Bildern sind das etwa 31 MB für die Untersuchung. Diese Rechnung brauchst du, wenn jemand fragt, warum eine Leitung ausgelastet ist.

Beachte, wo die Angabe steht: in **Group 0002**, dem File Meta Header. Sie gehört zur Datei, nicht zum Datensatz — und deshalb gibt es sie auf der Leitung gar nicht. Dort wird sie ausgehandelt (Lektion 1.8).

## Eine Kodierung ändern

`dcmconv` schreibt dasselbe Objekt in einer anderen Syntax neu:

```
$ dcmconv +ti daten/ct-thorax/0001.dcm /tmp/implicit.dcm
$ dcmdump +P TransferSyntaxUID /tmp/implicit.dcm
(0002,0010) UI [1.2.840.10008.1.2]                      #  18, 1 TransferSyntaxUID

$ ls -l daten/ct-thorax/0001.dcm /tmp/implicit.dcm
-rw-r--r-- 1 analyst analyst 527842 Sep 12 09:14 daten/ct-thorax/0001.dcm
-rw-r--r-- 1 analyst analyst 527096 Sep 12 09:15 /tmp/implicit.dcm
```

**Was du daran abliest:** Die UID hat sich geändert, die Dateigröße kaum. Implicit spart nur die zwei VR-Zeichen je Element — das sind ein paar hundert Byte. **Die Wahl zwischen implicit und explicit ist keine Frage der Größe**, sondern eine der Verträglichkeit: Explicit ist eindeutig lesbar, implicit braucht ein Wörterbuch und macht private Tags unlesbar.

## Wirklich komprimieren

```
$ dcmcjpeg +e1 daten/ct-thorax/0001.dcm /tmp/lossless.dcm
$ dcmdump +P TransferSyntaxUID +P LossyImageCompression /tmp/lossless.dcm
(0002,0010) UI [1.2.840.10008.1.2.4.70]                 #  22, 1 TransferSyntaxUID

$ ls -l /tmp/lossless.dcm
-rw-r--r-- 1 analyst analyst 271455 Sep 12 09:18 /tmp/lossless.dcm
```

**Was du daran abliest:** Knapp die Hälfte der Größe, verlustfrei — `LossyImageCompression` ist gar nicht vorhanden, also wurde nichts weggeworfen. Das Bild ist Pixel für Pixel dasselbe und lässt sich zurückwandeln:

```
$ dcmdjpeg /tmp/lossless.dcm /tmp/zurueck.dcm
$ dcmdump +P TransferSyntaxUID /tmp/zurueck.dcm
(0002,0010) UI [1.2.840.10008.1.2.1]                    #  20, 1 TransferSyntaxUID
```

**Was du daran abliest:** Man kommt aus einer verlustfreien Kompression vollständig wieder heraus. Aus einer verlustbehafteten nicht — da ist der Unterschied weg, egal wie oft man umwandelt.

**Zur verlustbehafteten Kompression:** JPEG Baseline arbeitet mit 8 Bit je Kanal und passt damit auf Ultraschall, Endoskopie oder eingescannte Dokumente, nicht auf ein 16-Bit-CT. Für Schnittbilder kommen verlustfreie Verfahren oder JPEG 2000 in Frage. Wo verlustbehaftet komprimiert wurde, **muss** das Objekt es kennzeichnen: `LossyImageCompression` auf `01`, dazu Verfahren und Verhältnis. Wer diese Kennzeichnung wegwirft, macht aus einem verlustbehafteten Bild ein scheinbar unangetastetes — und das ist ein Problem, das weit über Technik hinausgeht.

## Und der Fall vom Anfang

Genau dieses Muster steckt hinter „seit dem Update kommt nichts mehr
an": Nicht das Objekt ist falsch und nicht der Name — die Gegenstelle
akzeptiert diese *Kodierung* für diesen Objekttyp nicht. Ein Sendeversuch
scheitert dabei sofort mit einer Ablehnung, die ausdrücklich keinen
gemeinsamen Presentation Context findet — die Verbindung selbst wäre
zustande gekommen. Genau deshalb sieht bei diesem Fehlerbild alles
gesund aus: C-ECHO grün, AE Titles korrekt, und trotzdem geht kein Bild
durch. Ein echter Mitschnitt dieser Ablehnung, Zeile für Zeile erklärt,
folgt in Lektion 4.2.

Zwei Wege heraus: Das Archiv lernt die Kodierung, oder der Sender wandelt vorher um. Welcher richtig ist, steht im Conformance Statement der Gegenstelle — und nicht in deinem Bauchgefühl.

## Im Alltag heißt das

| Symptom | Verdacht |
|---|---|
| Von einem Gerät kommt gar nichts, C-ECHO ist grün | Keine gemeinsame Transfer Syntax |
| Von einem Gerät kommen nur manche Serien | Nur bestimmte Serien sind komprimiert |
| Nach einem Geräteupdate ist Schluss | Neue Voreinstellung für Kompression |
| Bilder kommen an, sind aber riesig | Es wird unkomprimiert übertragen |
| Ein altes Objekt lässt sich nicht öffnen | Explicit VR Big Endian oder ein exotisches Verfahren |
| Viewer zeigt es, Archiv lehnt ab | Viewer ist tolerant, Archiv prüft die Syntax |

Die erste Zeile ist Lektion 4.2, die zweite 4.3. Diese Lektion ist die Voraussetzung für beide.

> ### Stolperfallen
>
> **„Komprimiert heißt schlechter."**
> Nicht zwangsläufig. Verlustfreie Verfahren halbieren die Größe, ohne ein Pixel zu ändern. Nur verlustbehaftet verändert das Bild — und das muss gekennzeichnet sein.
>
> **„Das Archiv nimmt alles."**
> Kein Archiv nimmt alles. Was es annimmt, steht in seinem Conformance Statement, und es ist oft weniger, als die Verkaufsunterlage vermuten lässt.
>
> **„Implicit VR ist veraltet."**
> Nein, es ist die verpflichtende Rückfallebene. Veraltet ist Explicit VR Big Endian.
>
> **„Ich wandle einfach alles um."**
> Umwandeln erzeugt ein neues Objekt mit neuem Inhalt — nach Lektion 1.4 gehört dazu streng genommen eine Entscheidung über die UIDs. In einer Verarbeitungskette ist das legitim, im Vorbeigehen an Produktivdaten nicht.
>
> **„Zweimal verlustbehaftet komprimieren macht nichts."**
> Jeder Durchgang wirft erneut Information weg. Aus JPEG-Baseline nach JPEG-Baseline entstehen sichtbare Artefakte, die niemand mehr zurückholt.

## Dein Lab

Im Lab **Halbe Sache** nimmt das Archiv von einer Modalität nur einen Teil der Serien an. Deine Aufgabe: herausfinden, welche Eigenschaft die abgelehnten Serien gemeinsam haben — und einen Weg zeigen, wie die Übertragung trotzdem gelingt, ohne die Bilder zu verändern.

## Selbstcheck

<details>
<summary>Ein Objekt hat die Transfer Syntax `1.2.840.10008.1.2.4.91`. Was weißt du sofort?</summary>

Der Teil `.4.` sagt JPEG-Familie, die `91` ist JPEG 2000 in der verlustbehafteten Variante. Das Objekt ist also komprimiert, und zwar verlustbehaftet — es sollte entsprechend gekennzeichnet sein. Für die Übertragung heißt das: Die Gegenstelle muss genau diese Syntax akzeptieren, sonst kommt es nicht durch.
</details>

<details>
<summary>C-ECHO ist grün, `storescu` meldet „No presentation context". Wo liegt das Problem?</summary>

Nicht bei Netzwerk, Adressierung oder Namen — die Association wäre zustande gekommen. Die Gegenstelle akzeptiert die Kombination aus Objekttyp und Kodierung nicht. Entweder kennt sie die SOP Class nicht, oder sie kennt die angebotene Transfer Syntax nicht. Der Text der Meldung nennt beides.
</details>

<details>
<summary>Warum ist die Transfer Syntax nicht Teil des Datensatzes?</summary>

Weil sie beschreibt, *wie* der Datensatz kodiert ist — sie muss also gelesen werden können, bevor man ihn liest. In der Datei steht sie deshalb im File Meta Header (Group 0002), der immer in Explicit VR Little Endian geschrieben ist. Auf der Leitung gibt es diesen Header nicht; dort handeln die beiden Seiten die Syntax beim Verbindungsaufbau aus.
</details>

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Welche Transfer Syntax muss jede DICOM-Anwendung beherrschen?**
1. Explicit VR Little Endian
2. Implicit VR Little Endian
3. JPEG Lossless
4. Explicit VR Big Endian

**q2 — Welche Aussagen stimmen?** *(Mehrfachauswahl)*
1. Verlustfreie Kompression lässt sich vollständig rückgängig machen
2. Die Transfer Syntax steht im Datensatz, nicht im File Meta Header
3. Verlustbehaftete Kompression muss im Objekt gekennzeichnet werden
4. Alles mit `1.2.840.10008.1.2.4.` gehört zur JPEG-Familie

**q3 — Wie lautet die UID von Explicit VR Little Endian?** *(Freitext)*

---

**Als Nächstes:** [1.8 — Association und Presentation Context](../1.8/) zeigt, wo genau diese Aushandlung stattfindet und wie man sie mitliest.
