---
title: Zwei Ebenen tiefer
scenario_title: Ein Ordner mit gemischten Objekten — irgendwo fehlt etwas
---

## Briefing

Ein Ordner mit fünf Objekten liegt vor dir, dazu die Meldung: „Da
fehlt etwas." Mehr Kontext gibt es nicht.

Deine Umgebung:

| System | Adresse | Was du darfst |
|---|---|---|
| Deine Workstation | 10.20.0.50 | Shell mit dcmdump — plus Befehlsvorlagen; alle fünf Dateien liegen bereits lokal |

Deine Aufgabe: Sag, wie viele Patienten, Studies und Series im Ordner
liegen — und welcher Patient die unvollständige Study hat. Flag ist
dessen Patient ID.

Vorkenntnisse: Lektion 1.2. Rechne mit 10 Minuten.

## Hints

### h1

Du brauchst nur die Zählschleife aus der Lektion, einmal je Ebene:
`dcmdump` auf jede Datei, dann die Werte für `PatientID`,
`StudyInstanceUID` und `SeriesInstanceUID` vergleichen.

### h2

Zähle zuerst pro Study, wie viele unterschiedliche
`SeriesInstanceUID`-Werte darin vorkommen. Eine Study hat zwei, die
andere nur eine.

### h3

`PatientID` `4712` gehört zur Study mit nur einer Series — die andere
Study desselben Datensatzes hat zwei. Das ist das Flag.

## Write-up

### Der Weg

1. Erst zählen, wie viele Dateien überhaupt da sind

```
$ ls
      262144  bild-01.dcm
      262144  bild-02.dcm
      262144  bild-03.dcm
      262144  bild-04.dcm
      262144  bild-05.dcm
```
**Was du daran abliest:** Fünf Objekte, alle gleich groß — die Größe
allein verrät nichts über die Hierarchie darüber.

2. Jede Datei einzeln auslesen

```
$ dcmdump bild-01.dcm
I: (0010,0020) LO [4711]  # xx, 1 PatientID
I: (0020,000D) UI [1.2.276.0.7230010.3.1.4.881100001]  # xx, 1 StudyInstanceUID
I: (0020,000E) UI [1.2.276.0.7230010.3.1.3.881100011]  # xx, 1 SeriesInstanceUID

$ dcmdump bild-02.dcm
I: (0010,0020) LO [4711]  # xx, 1 PatientID
I: (0020,000D) UI [1.2.276.0.7230010.3.1.4.881100001]  # xx, 1 StudyInstanceUID
I: (0020,000E) UI [1.2.276.0.7230010.3.1.3.881100011]  # xx, 1 SeriesInstanceUID

$ dcmdump bild-03.dcm
I: (0010,0020) LO [4711]  # xx, 1 PatientID
I: (0020,000D) UI [1.2.276.0.7230010.3.1.4.881100001]  # xx, 1 StudyInstanceUID
I: (0020,000E) UI [1.2.276.0.7230010.3.1.3.881100012]  # xx, 1 SeriesInstanceUID

$ dcmdump bild-04.dcm
I: (0010,0020) LO [4712]  # xx, 1 PatientID
I: (0020,000D) UI [1.2.276.0.7230010.3.1.4.881100002]  # xx, 1 StudyInstanceUID
I: (0020,000E) UI [1.2.276.0.7230010.3.1.3.881100021]  # xx, 1 SeriesInstanceUID

$ dcmdump bild-05.dcm
I: (0010,0020) LO [4712]  # xx, 1 PatientID
I: (0020,000D) UI [1.2.276.0.7230010.3.1.4.881100002]  # xx, 1 StudyInstanceUID
I: (0020,000E) UI [1.2.276.0.7230010.3.1.3.881100021]  # xx, 1 SeriesInstanceUID
```
**Was du daran abliest:** Fünf Instances, aber nur zwei unterschiedliche
`PatientID`-Werte (`4711`, `4712`) und nur zwei unterschiedliche
`StudyInstanceUID`-Werte — jeder Patient hat also genau eine Study.
Bei den `SeriesInstanceUID`-Werten wird es interessant: Study
`...881100001` (Patient `4711`) hat zwei verschiedene
(`...011` und `...012`), Study `...881100002` (Patient `4712`) nur
eine (`...021`), obwohl sie über zwei Instances verteilt ist.

3. Die Ebenen gegenrechnen

| Ebene | Anzahl |
|---|---|
| Patient | 2 (`4711`, `4712`) |
| Study | 2 (je eine pro Patient) |
| Series | 3 insgesamt — 2 bei Patient `4711`, nur 1 bei Patient `4712` |
| Instance | 5 |

**Was du daran abliest:** Jede Ebene für sich sieht plausibel aus.
Erst der Vergleich zwischen den beiden Studies zeigt die Lücke: Eine
Study hat zwei Series, die andere nur eine — die Series-Ebene ist die
unvollständige.

4. Flag: die Patient ID der unvollständigen Study — `4712`.

### Was du mitnimmst

Ein Archiv verwaltet die Hierarchie nicht in einer eigenen Tabelle,
sondern errechnet sie aus den Objekten selbst — deshalb lässt sie sich
mit derselben Zählschleife nachvollziehen, mit der sie entsteht.
Vollständigkeit prüft man nicht auf einer einzelnen Ebene, sondern im
Vergleich zwischen gleichartigen Gruppen: Zwei Studies, die eigentlich
demselben Untersuchungstyp entsprechen, aber unterschiedlich viele
Series enthalten — genau das ist der Hinweis, nicht ein einzelner
fehlender Wert.

### Verwandte Inhalte

Lektion 1.2 — Patient, Study, Series, Instance
