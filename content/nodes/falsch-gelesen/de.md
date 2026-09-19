---
title: Falsch gelesen
scenario_title: Ein Bild, das erfolgreich ankam — und trotzdem falsch aussieht
---

## Briefing

Ticket aus der Diagnostik-Workstation: Ein neues Thorax-Bild vom
mobilen Röntgengerät MOBIL-CR-9 wirkt komplett invertiert — Knochen
erscheinen dunkel, Luft hell. Das Bild lässt sich vollständig öffnen,
nichts fehlt. Ein älteres Bild derselben Modalität sieht normal aus.

Ein erfolgreich übertragenes und vollständig lesbares Bild sagt nichts
darüber aus, ob es auch richtig *interpretiert* wird. Deine Aufgabe:
Transport, Kodierung (Transfer Syntax) und Bildinterpretation
(Image Pixel Module) als getrennte Fragen behandeln — und die Stelle
finden, an der die Kette tatsächlich reißt.

Vorkenntnisse: Lektion 3.2. Rechne mit 15 Minuten.

## Hints

### h1

Ein erfolgreich gespeichertes, vollständig lesbares Bild sagt nichts
darüber aus, ob die Metadaten zur späteren Darstellung passen. Transport,
Transfer-Syntax-Dekodierung und Bildinterpretation sind drei
unabhängige Fragen — grenze zuerst ein, welche davon überhaupt betroffen
sein könnte.

### h2

Vergleiche das auffällige Bild mit einem unauffälligen Bild derselben
Quelle. Welches Attribut im Image Pixel Module unterscheidet sich,
obwohl beide Bilder vom selben physikalischen Gerätetyp stammen?

### h3

Sieh dir PhotometricInterpretation in beiden Bildern an — und was die
Konformitätserklärung des Geräts über dessen native Bildkonvention
sagt. Unterstelle dabei nicht schon, dass ein bestimmter Wert
grundsätzlich falsch ist.

## Write-up

### Symptom

Ein neues Thorax-Bild wirkt im Viewer komplett invertiert — Knochen
dunkel, Luft hell — obwohl es sich vollständig öffnen lässt und nichts
fehlt. Ein älteres Bild derselben Modalität sieht normal aus. Das
äußere Bild allein sagt nicht, auf welcher Ebene der Fehler liegt.

### Technisch erfolgreiche Ebenen

<!-- kein-beispiel -->
```text
Transport erfolgreich (C-STORE, PACS-Bestand vollständig)
        ≠
Transfer Syntax korrekt dekodiert (Pixelmatrix vollständig lesbar)
        ≠
Pixelinformationen semantisch korrekt beschrieben (PhotometricInterpretation)
        ≠
Viewer stellt sie korrekt dar
```

Vier getrennte Aussagen — keine folgt automatisch aus einer anderen.
In diesem Fall sind die ersten beiden Ebenen nachweislich in Ordnung:
C-STORE meldet Success, die Objektgröße entspricht der erwarteten
Pixelmatrix, und die Transfer Syntax (Explicit VR Little Endian,
unkomprimiert) dekodiert vollständig fehlerfrei — identisch mit dem
unauffälligen Vergleichsbild.

### Hypothesen

1. **Der C-STORE-Transfer hat Pixeldaten abgeschnitten oder
   beschädigt.** Widerlegt: Transfer erfolgreich, Objektgröße
   entspricht der erwarteten Pixelmatrix vollständig.
2. **Die Transfer Syntax wird falsch dekodiert.** Widerlegt: Transfer
   Syntax unkomprimiert, dekodiert vollständig fehlerfrei, identisch
   mit dem unauffälligen Vergleichsbild.
3. **Ein Viewer verarbeitet eine gültige DICOM-Eigenschaft falsch.**
   Widerlegt: Dieselbe Workstation zeigt das unauffällige Vergleichsbild
   korrekt an — sie honoriert PhotometricInterpretation grundsätzlich
   richtig.
4. **Die Pixel-Interpretationsmetadaten (PhotometricInterpretation)
   passen nicht zum tatsächlichen Bildinhalt.** Bestätigt: Das
   Bildgateway BRIDGE-IMG schreibt seit einem Versions-Update pauschal
   MONOCHROME2 in jedes durchlaufende Objekt — unabhängig von der
   nativen Konvention der Quelle.

### Evidenz

- **Transport**: C-STORE Success, PACS-Bestand vollständig (1 von 1
  erwarteter Instanz).
- **Kodierung**: Transfer Syntax unkomprimiert, identisch mit dem
  Vergleichsbild, Pixelmatrix vollständig dekodierbar.
- **Image Pixel Module** (auffälliges Bild): `SamplesPerPixel 1`,
  `PhotometricInterpretation MONOCHROME2`, `BitsAllocated 16`,
  `BitsStored 12`, `HighBit 11`, `PixelRepresentation 0`.
- **Vergleichsbild** derselben Modalität, eine Woche älter: identische
  Struktur, aber `PhotometricInterpretation MONOCHROME1`.
- **Konformitätserklärung MOBIL-CR-9**: Gerät liefert Rohbilder nativ
  in inverser Graustufen-Konvention (entspricht MONOCHROME1).
- **Bildgateway-Log BRIDGE-IMG**: Seit einem Versions-Update vor zwei
  Wochen wird pauschal `PhotometricInterpretation = MONOCHROME2` in
  jedes weitergeleitete Objekt geschrieben, unabhängig vom Quellgerät.
  Das auffällige Bild stammt von nach dem Update, das unauffällige von
  davor.

### Erste fehlerhafte Stelle

Nicht das Gerät, nicht der Transfer, nicht der Viewer — das
Bildgateway BRIDGE-IMG überschreibt beim Weiterleiten pauschal
PhotometricInterpretation mit einem Standardwert, der zur nativen
Konvention der Quelle nicht passt. MONOCHROME1 selbst ist dabei nicht
das Problem: Es ist eine gültige, im Standard vorgesehene Konvention.
Das Problem ist, dass ein pauschal geschriebener Wert nicht mehr zur
tatsächlichen Bildquelle passt.

### Betriebliche Maßnahme

Die Standard-Vorlage im Bildgateway korrigieren, sodass
PhotometricInterpretation pro Quellgerät (oder unverändert aus dem
Originalobjekt) übernommen wird, statt pauschal überschrieben zu
werden. Betroffene, bereits archivierte Objekte aus dem Update-Zeitraum
identifizieren und die Metadaten dort gezielt korrigieren — kein
erneuter Transfer, kein Viewer- oder Workstation-Eingriff, da beide
nachweislich nicht die Ursache sind.

### Was du mitnimmst

Erfolgreich gespeichert und vollständig lesbar heißt nicht automatisch
korrekt interpretierbar. Transport, Transfer-Syntax-Dekodierung und
Bildinterpretation sind drei unabhängige Ebenen — ein Fehler auf der
dritten Ebene sieht von außen identisch aus wie ein Viewer-Defekt,
lässt sich aber, wie hier, eindeutig auf ein falsch gesetztes
Metadaten-Tag in einer Zwischenstation zurückführen, sobald man ein
Vergleichsobjekt und die Gerätedokumentation heranzieht.

### Verwandte Inhalte

Lektion 3.2 — Pixeldaten, Photometric Interpretation, Bits Allocated
