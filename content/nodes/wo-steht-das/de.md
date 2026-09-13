---
title: Wo steht das?
scenario_title: Drei Fragen zu einer Datei — keine Tag-Nummern dabei
---

## Briefing

Ein Kollege fragt: „Welchen Rekonstruktionskern hat diese Schicht?
Welche Schichtdicke? Und von wann ist die Aufnahme?" Tag-Nummern nennt
er nicht — die musst du selbst finden.

Deine Umgebung:

| System | Adresse | Was du darfst |
|---|---|---|
| Deine Workstation | 10.15.0.50 | Shell mit dcmdump — plus Befehlsvorlagen; die Datei liegt bereits lokal |

Deine Aufgabe: Finde alle drei Werte, und entscheide dabei, ob es sich
um Standard- oder um Herstellerangaben handelt. Flag ist der
Rekonstruktionskern.

Vorkenntnisse: Lektion 1.1, 1.3. Rechne mit 8 Minuten.

## Hints

### h1

Ohne Tag-Nummer hilft ein voller `dcmdump` weiter — er zeigt alles,
was das Objekt an Tags trägt.

### h2

Alle drei gesuchten Werte tragen offizielle DICOM-Schlüsselwörter:
`AcquisitionDate`, `SliceThickness`, `ConvolutionKernel`. Das sind
Standard-Tags aus dem öffentlichen DICOM-Wörterbuch — keine
herstellerspezifischen Tags mit ungerader Group-Nummer.

### h3

Der Rekonstruktionskern steht im Tag `ConvolutionKernel`, Wert `B60f`
— das ist das Flag.

## Write-up

### Der Weg

1. Die Datei vollständig auslesen

```
$ dcmdump schicht-0001.dcm
I: (0008,0022) DA [20260910]  # xx, 1 AcquisitionDate
I: (0018,0050) DS [3.0]  # xx, 1 SliceThickness
I: (0018,1210) SH [B60f]  # xx, 1 ConvolutionKernel
```
**Was du daran abliest:** Alle drei gesuchten Werte stehen im Dump —
`AcquisitionDate` das Aufnahmedatum, `SliceThickness` die Schichtdicke,
`ConvolutionKernel` den Rekonstruktionskern. Keine Tag-Nummer war vorher
nötig, um sie zu finden.

2. Standard oder Herstellerangabe?

Alle drei Tags — `(0008,0022)`, `(0018,0050)`, `(0018,1210)` — liegen
in geraden Group-Nummern und tragen offizielle DICOM-Schlüsselwörter.
Das sind Standard-Tags. Der **Wert** von `ConvolutionKernel` (`B60f`)
ist zwar herstellerspezifisch formatiert — welcher Rekonstruktionskern
wie heißt, legt jeder Hersteller selbst fest —, aber das Tag selbst,
in dem dieser Wert steht, ist im öffentlichen DICOM-Wörterbuch
definiert. Ein echtes Herstellertag würde stattdessen eine ungerade
Group-Nummer tragen, etwa `(0029,xxxx)`.

3. Flag: der Rekonstruktionskern — `B60f`.

### Was du mitnimmst

Ein unbekannter Wert lässt sich fast immer per `dcmdump` und einem
Blick ins DICOM-Wörterbuch einordnen, auch ohne dass jemand vorher die
Tag-Nummer nennt. Und: "klingt herstellerspezifisch" ist nicht
dasselbe wie "ist ein Herstellertag" — der Rekonstruktionskern-Wert
selbst ist proprietär, das Tag, das ihn trägt, ist es nicht. Die
sichere Unterscheidung liefert nur die Group-Nummer, nicht der Klang
des Feldnamens.

### Verwandte Inhalte

Lektion 1.1 — Was DICOM eigentlich ist
Lektion 1.3 — Tags, Groups, Elements, VR
