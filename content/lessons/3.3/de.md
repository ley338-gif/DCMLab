---
title: "Window Center/Width, Rescale Slope/Intercept, LUTs"
teaser: Ein CT-Bild ist "schwarz" — nicht weil die Aufnahme fehlgeschlagen ist, sondern weil niemand dem Viewer gesagt hat, wohin er schauen soll.
objectives:
  - Rescale Slope/Intercept als Umrechnung von Rohwert zu Hounsfield Units erklären
  - Window Center/Width als Ausschnitt aus dem Wertebereich verstehen
  - Ein "schwarzes" Bild auf eine falsche oder fehlende Fensterung zurückführen
---

## „Das CT-Bild ist komplett schwarz"

Die Datei ist valide, `dcmftest` bestätigt es, die Pixeldaten sind da —
und trotzdem zeigt der Viewer nur eine schwarze Fläche. Kein
Übertragungsfehler, keine beschädigte Datei. Das Bild ist tatsächlich
da; niemand hat dem Viewer nur gesagt, in welchem Ausschnitt des
Wertebereichs er suchen soll.

## Zwei getrennte Umrechnungen

Ein CT-Rohwert ist erst einmal nur eine Zahl ohne Einheit. Bis daraus
etwas Anzeigbares wird, passieren zwei unabhängige Schritte:

1. **Rohwert → {{term:hounsfield-unit}}s.** `RescaleSlope` und
   `RescaleIntercept` rechnen den gerätespezifischen Rohwert in die
   genormte, geräteunabhängige Hounsfield-Skala um:
   `HU = Rohwert × RescaleSlope + RescaleIntercept`.
2. **Hounsfield Units → das, was der Viewer zeigt.** `WindowCenter` und
   `WindowWidth` legen fest, welcher HU-Bereich überhaupt sichtbar wird
   — alles darunter wird schwarz, alles darüber weiß, dazwischen liegt
   eine Grauwertrampe.

Das Testobjekt der Spielwiese trägt diese Attribute nicht:

```
$ dcmdump +P RescaleSlope +P RescaleIntercept +P WindowCenter +P WindowWidth \
          daten/ct-thorax-60/instance-0001.dcm
```
**Was du daran abliest:** Der Befehl liefert keine einzige Zeile — die
Testdaten der Spielwiese sind bewusst minimal und tragen diese vier
Attribute nicht. Genau das lässt sich real durchspielen: Ein reales
Gerät setzt sie, hier fehlen sie schlicht.

## Beide Schritte real ergänzen und nachrechnen

```
$ dcmodify -i "RescaleIntercept=-1024" -i "RescaleSlope=1" \
           -i "WindowCenter=40" -i "WindowWidth=400" \
           instance-0001.dcm
$ dcmdump +P RescaleSlope +P RescaleIntercept +P WindowCenter +P WindowWidth \
          instance-0001.dcm
(0028,1053) DS [1]                                      #   2, 1 RescaleSlope
(0028,1052) DS [-1024]                                  #   6, 1 RescaleIntercept
(0028,1050) DS [40]                                     #   2, 1 WindowCenter
(0028,1051) DS [400]                                    #   4, 1 WindowWidth
```
**Was du daran abliest:** `-i` fügt ein Tag neu ein (es gab vorher
keins). Die Werte sind keine Erfindung: `RescaleIntercept -1024` mit
`RescaleSlope 1` ist die in der CT-Praxis nahezu universelle Konvention
— sie verschiebt einen unsigned Rohwertbereich so, dass Luft auf
`-1000 HU` landet. `WindowCenter 40`/`WindowWidth 400` ist die verbreitete
Voreinstellung für ein Weichteilfenster.

Jetzt lässt sich echt nachrechnen, warum unser Testobjekt schwarz
bliebe. Die Pixeldaten der Spielwiese sind durchgängig `0`:

<!-- kein-beispiel -->
```
HU = Rohwert × RescaleSlope + RescaleIntercept
   = 0 × 1 + (-1024)
   = -1024
```
**Was du daran abliest:** `-1024 HU` ist der Wert für Luft/Vakuum —
plausibel für ein leeres, synthetisches Testbild. Das Weichteilfenster
(`Center 40`, `Width 400`) deckt den Bereich von `40 - 200 = -160` bis
`40 + 200 = 240` HU ab. `-1024` liegt weit unterhalb davon — der Pixel
würde also unabhängig vom genauen Rohwert als Schwarz dargestellt,
weil er außerhalb des gewählten Fensters liegt, nicht weil die Datei
kaputt ist.

**Warum diese Rechnung, aber kein Bild:** Der Werkzeugkasten der
Spielwiese enthält keinen Bild-Viewer (siehe `content/tools/de.yml`).
Was diese Lektion real zeigen kann, ist die vollständige Rechenkette
bis zum HU-Wert und zur Window-Grenze — ob ein konkreter Viewer daraus
tatsächlich ein schwarzes Pixel macht, ist eine Eigenschaft des
Viewers, nicht der Datei.

## Und wenn die Werte selbst nicht linear sind: LUTs

Nicht jede Umrechnung ist eine einfache Gerade. Für beide Schritte gibt
es eine Alternative, wenn eine reine Steigungs-/Achsenabschnitts-Formel
nicht reicht:

- **Modality LUT Sequence** (`0028,3000`) kann statt `RescaleSlope`/
  `RescaleIntercept` eine beliebige, nicht-lineare Rohwert-zu-Wert-Tabelle
  mitbringen.
- **VOI LUT Sequence** (`0028,3010`) kann statt `WindowCenter`/`WindowWidth`
  eine beliebige, nicht-lineare Kurve für die Anzeige mitbringen — z. B.
  wenn ein Hersteller eine speziell geformte Kontrastkurve vorschlagen will.

Beide sind optional; fehlen sie, gelten die linearen Formeln von oben.

## Im Alltag heißt das

| Beobachtung | Wahrscheinliche Ursache |
|---|---|
| Bild komplett schwarz, Datei valide | Fensterung liegt außerhalb des tatsächlichen HU-Bereichs |
| Zwei Viewer zeigen denselben Datensatz unterschiedlich hell | Unterschiedliche `WindowCenter`/`WindowWidth`-Voreinstellungen — beide können richtig sein |
| Werte wirken „verschoben" gegenüber der Erwartung | `RescaleIntercept` fehlt oder ist falsch — Rohwert und HU werden verwechselt |

> ### Stolperfallen
>
> **„Schwarzes Bild heißt kaputte Datei."**
> Nicht zwangsläufig — siehe oben. Erst `RescaleSlope`/`Intercept` und
> `WindowCenter`/`Width` prüfen, bevor die Datei selbst infrage steht.
>
> **„Es gibt nur eine richtige Fensterung."**
> Es gibt eine sinnvolle *für eine bestimmte Frage* (Weichteil, Lunge,
> Knochen — jeweils andere Center/Width-Werte). Zwei unterschiedliche,
> beide korrekte Darstellungen desselben Datensatzes sind normal.

## Selbstcheck

1. Ein Rohwert ist `1024`, `RescaleSlope` ist `1`, `RescaleIntercept`
   ist `-1024`. Welcher HU-Wert ergibt sich, und was bedeutet er?
2. `WindowCenter` ist `40`, `WindowWidth` ist `400`. Welcher HU-Bereich
   wird dargestellt?
3. Zwei Viewer zeigen denselben Datensatz unterschiedlich hell. Ist
   automatisch einer der beiden falsch konfiguriert?
