---
title: UIDs — warum alles eine Nummer hat
teaser: Zwei Objekte mit derselben UID sind für jedes Archiv der Welt dasselbe Objekt. Auch wenn sie es nicht sind.
objectives:
  - Well-known UIDs von erzeugten UIDs unterscheiden
  - Erklären, was passiert, wenn eine UID doppelt vergeben wird
  - Aus dem Anfang einer UID ablesen, wer sie erzeugt hat
---

## Ein Testbild, das ein echtes gelöscht hat

Jemand braucht ein Testobjekt. Er nimmt sich ein CT-Bild aus dem Archiv, ändert mit einem Werkzeug den Patientennamen auf `TEST^TEST` und schickt es zurück ins Archiv, um eine Anzeigefrage zu klären.

Danach fehlt in der ursprünglichen Untersuchung ein Bild. Nicht verschoben, nicht umbenannt — weg.

Das Archiv hat sich dabei völlig korrekt verhalten. Es hat ein Objekt bekommen, dessen Identität es bereits kannte, und die vorhandene Fassung durch die neue ersetzt. Für DICOM war das kein zweites Bild. Es war dasselbe Bild, nur anders.

Wer versteht, warum, macht diesen Fehler nie.

## Was eine UID ist

Ein Unique Identifier ist eine Zeichenkette aus **Ziffern und Punkten**, höchstens 64 Zeichen lang. Keine Buchstaben, keine Bindestriche, keine führenden Nullen in den einzelnen Bestandteilen.

```
1.2.840.10008.5.1.4.1.1.2
└──┬──┘└──┬──┘└──────┬─────┘
 Wurzel  DICOM    Was genau
```

Der Anspruch dahinter ist groß: **weltweit eindeutig, für immer.** Zwei Geräte in zwei Ländern dürfen nie dieselbe UID erzeugen, und eine einmal vergebene UID darf nie wiederverwendet werden — auch dann nicht, wenn das Objekt längst gelöscht ist.

Das funktioniert über Wurzeln. Eine Organisation bekommt einen Präfix zugeteilt und ist unterhalb davon allein zuständig. `1.2.840.10008` gehört der NEMA und damit dem Standard selbst; `1.2.276` ist die deutsche Wurzel.

## Zwei völlig verschiedene Sorten

Das ist die Unterscheidung, die im Alltag zählt:

| | **Well-known UIDs** | **Erzeugte UIDs** |
|---|---|---|
| Kommen aus | dem Standard | dem Gerät, das das Objekt erzeugt |
| Beginnen mit | `1.2.840.10008` | der Wurzel des Herstellers |
| Beispiele | SOP Classes, Transfer Syntaxen | Study-, Series-, SOP-Instance-UID |
| Anzahl | endlich, nachschlagbar | praktisch unendlich |
| Darfst du ändern | nie | nur mit sehr guten Gründen |

```
$ dcmdump +P SOPClassUID +P TransferSyntaxUID +P SOPInstanceUID daten/ct-thorax/0001.dcm
(0002,0010) UI [1.2.840.10008.1.2.1]                    #  20, 1 TransferSyntaxUID
(0008,0016) UI [1.2.840.10008.5.1.4.1.1.2]              #  26, 1 SOPClassUID
(0008,0018) UI [1.2.276.0.7230010.3.1.4.8323329.11150.1757580901.3] #  50, 1 SOPInstanceUID
```

**Was du daran abliest:** Die ersten beiden beginnen mit `1.2.840.10008` — das sind Werte aus dem Standard, die weltweit dieselbe Bedeutung haben (`…5.1.4.1.1.2` heißt *CT Image Storage*, `…1.2.1` heißt *Explicit VR Little Endian*). Die dritte ist erzeugt worden, als dieses Bild entstand, und existiert genau einmal.

## Der Anfang verrät den Erzeuger

Das ist ein praktischer Nebeneffekt, der bei Störungssuchen erstaunlich oft hilft:

```
$ for f in daten/ct-thorax/*.dcm; do dcmdump +P SOPInstanceUID "$f"; done | \
      sed 's/.*\[\([0-9.]*\)\..*/\1/' | cut -d. -f1-6 | sort -u
1.2.276.0.7230010.3
```

**Was du daran abliest:** Alle Objekte in diesem Ordner tragen dieselbe Wurzel — `1.2.276.0.7230010.3` gehört dem DCMTK-Toolkit von OFFIS. Diese Dateien sind also mit dcmtk erzeugt oder umgeschrieben worden, nicht von einer Modalität. Bei einem echten Bestand siehst du an dieser Stelle den Hersteller der Modalität, und wenn plötzlich eine fremde Wurzel auftaucht, hat unterwegs ein Werkzeug das Objekt angefasst. Bei „woher kommen diese Objekte eigentlich" ist das oft die schnellste Antwort.

## Was UIDs im Alltag zusammenhalten

Aus Lektion 1.2 kennst du die Hierarchie. Sie besteht aus nichts anderem als drei UIDs:

```
$ dcmdump +P StudyInstanceUID +P SeriesInstanceUID +P SOPInstanceUID \
          daten/ct-thorax/0001.dcm daten/ct-thorax/0002.dcm

# daten/ct-thorax/0001.dcm
(0008,0018) UI [1.2.276.0.7230010.3.1.4.8323329.11150.1757580901.3]
(0020,000d) UI [1.2.276.0.7230010.3.1.2.8323329.11150.1757580901.1]
(0020,000e) UI [1.2.276.0.7230010.3.1.3.8323329.11150.1757580901.2]

# daten/ct-thorax/0002.dcm
(0008,0018) UI [1.2.276.0.7230010.3.1.4.8323329.11150.1757580901.7]
(0020,000d) UI [1.2.276.0.7230010.3.1.2.8323329.11150.1757580901.1]
(0020,000e) UI [1.2.276.0.7230010.3.1.3.8323329.11150.1757580901.2]
```

**Was du daran abliest:** Study und Series sind identisch, die SOP Instance UID ist verschieden. Genau so sieht „zwei Bilder derselben Serie" aus. Wären die Series-UIDs verschieden, hättest du zwei Serien; wären die Study-UIDs verschieden, zwei Untersuchungen — und niemand würde es den Bildern ansehen.

## Und so passiert der Unfall vom Anfang

```
$ cp daten/ct-thorax/0001.dcm /tmp/test.dcm
$ dcmodify -m PatientName="TEST^TEST" /tmp/test.dcm
$ dcmdump +P PatientName +P SOPInstanceUID /tmp/test.dcm
(0008,0018) UI [1.2.276.0.7230010.3.1.4.8323329.11150.1757580901.3] #  50, 1 SOPInstanceUID
(0010,0010) PN [TEST^TEST]                              #   9, 1 PatientName
```

**Was du daran abliest:** Der Name ist neu, die Identität ist es nicht. Für jedes Archiv der Welt ist das **dasselbe Objekt** wie das Original — nur mit geändertem Inhalt. Schickst du es hin, ersetzt es die vorhandene Fassung oder wird abgelehnt, je nach Archiv. Ein drittes Verhalten gibt es nicht, und keines davon ist, was du wolltest.

Richtig wäre gewesen, beim Kopieren neue UIDs zu erzeugen. Die Werkzeuge können das — `dcmodify` etwa mit der Option, die UIDs neu zu vergeben. Die Regel dahinter ist einfach:

> **Sobald der Inhalt ein anderer sein soll, muss die Identität eine andere sein.**

## Anonymisieren ist mehr als Namen ersetzen

Dieselbe Regel erklärt, warum Anonymisierung schwieriger ist, als sie klingt. Wer nur die Patientenangaben überschreibt und die UIDs stehen lässt, hat einen Datensatz gebaut, der sich über seine UIDs jederzeit wieder dem Original zuordnen lässt. Das ist keine Anonymisierung, sondern eine Pseudonymisierung mit offenem Schlüssel.

Wer dagegen alle UIDs **zufällig** neu vergibt, zerreißt die Hierarchie: Aus 60 Bildern werden 60 Studies, wenn jede Datei eine neue Study-UID bekommt.

Richtig ist der Mittelweg: Jede alte UID wird auf **genau eine** neue abgebildet, und dieselbe Abbildung gilt für alle Objekte des Datensatzes. Die Struktur bleibt erhalten, der Bezug zum Original ist weg. Gute Anonymisierungswerkzeuge machen das von allein — man muss nur wissen, dass es die Anforderung ist. Track 5 geht darauf ein.

Und noch eine Regel dazu: **Nie eine UID aus Patientendaten ableiten.** Eine UID, die eine Fallnummer oder ein Geburtsdatum enthält, ist ein personenbezogenes Merkmal, das in jedem Export mitreist.

## Im Alltag heißt das

| Beobachtung | Was die UIDs dir sagen |
|---|---|
| Eine Untersuchung erscheint doppelt | Zwei Study-UIDs für einen Termin |
| Bilder verschwinden nach einem Import | Doppelte SOP-Instance-UIDs — überschrieben |
| Eine Serie zerfällt in viele | Series-UID variiert innerhalb der Aufnahme |
| Objekte „gehören nicht dazu" | Andere Wurzel als der Rest — unterwegs umgeschrieben |
| Archiv lehnt einen Import ab | Objekt mit bereits bekannter UID, strenge Einstellung |

> ### Stolperfallen
>
> **„Ich vergebe die UID von Hand."**
> Nie. Weder ausdenken noch hochzählen. Die Werkzeuge erzeugen UIDs aus einer registrierten Wurzel plus einem eindeutigen Teil; alles andere riskiert Kollisionen, die erst Jahre später auffallen.
>
> **„Eine Kopie ist ein neues Objekt."**
> Für das Dateisystem ja, für DICOM nein. Identität steckt in der UID, nicht im Dateinamen oder im Speicherort.
>
> **„Nach dem Löschen ist die UID wieder frei."**
> Nein. Eine UID wird nie wiederverwendet, auch nicht nach Jahren. Archive führen teilweise Listen gelöschter UIDs, damit gelöschte Objekte nicht versehentlich zurückkommen.
>
> **„64 Zeichen sind genug für alles."**
> Meistens ja — aber Systeme, die UIDs verketten oder Präfixe anhängen, laufen darüber. Was länger ist, ist ungültig, und manche Gegenstelle weist das Objekt kommentarlos ab.
>
> **„Anonymisieren heißt, den Namen zu löschen."**
> Siehe oben. Ohne konsistent neu vergebene UIDs bleibt der Bezug zum Original bestehen.

## Dein Lab

Im Lab **Zwillinge** liegen zwei Studies im Archiv, die in jeder sichtbaren Angabe übereinstimmen — gleicher Patient, gleiches Datum, gleiche Beschreibung. Eine davon ist die echte. Deine Aufgabe: herausfinden, welche, und begründen, woran du es erkannt hast.

## Selbstcheck

<details>
<summary>Warum kann man ein DICOM-Objekt nicht einfach kopieren und den Inhalt ändern?</summary>

Weil die Kopie dieselbe SOP Instance UID trägt und damit für jedes Archiv dasselbe Objekt ist. Beim Senden wird die vorhandene Fassung ersetzt oder das Objekt abgelehnt. Wer den Inhalt ändert, muss die Identität mitändern — also neue UIDs erzeugen lassen.
</details>

<details>
<summary>Eine UID beginnt mit `1.2.840.10008`. Was weißt du sofort?</summary>

Dass es ein Wert aus dem Standard ist, kein erzeugter. Diese Wurzel gehört der NEMA; darunter liegen SOP Class UIDs, Transfer Syntax UIDs und andere festgelegte Werte. Man kann sie nachschlagen, und sie bedeuten weltweit dasselbe. Erzeugte UIDs haben immer die Wurzel des erzeugenden Herstellers.
</details>

<details>
<summary>Was ist falsch daran, bei einer Anonymisierung alle UIDs zufällig neu zu vergeben?</summary>

Die Hierarchie zerfällt. Wenn jede Datei eine eigene neue Study- und Series-UID bekommt, werden aus einer Untersuchung mit 60 Bildern 60 Untersuchungen. Richtig ist eine konsistente Abbildung: jede alte UID auf genau eine neue, für den ganzen Datensatz dieselbe.
</details>

## Quiz

*Wissenskarten — kommen später zur Wiederholung zurück.*

**q1 — Zwei Objekte haben dieselbe SOP Instance UID. Wie behandelt ein Archiv sie?**
1. Als zwei verschiedene Bilder
2. Als dasselbe Objekt — es ersetzt oder lehnt ab
3. Es legt beide nebeneinander ab
4. Das hängt vom Patientennamen ab

**q2 — Welche Aussagen über UIDs stimmen?** *(Mehrfachauswahl)*
1. Sie dürfen nur Ziffern und Punkte enthalten
2. Sie sind auf 64 Zeichen begrenzt
3. Eine gelöschte UID darf wiederverwendet werden
4. Der Anfang verrät, wer die UID erzeugt hat

**q3 — Welche UID-Wurzel gehört dem DICOM-Standard selbst?** *(Freitext)*

---

**Als Nächstes:** [1.5 — SCU und SCP](../1.5/) verlässt die Datei und geht ins Netz: wer bei einer Verbindung welche Rolle hat.
