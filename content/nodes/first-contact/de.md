---
title: First Contact
scenario_title: Eine Datei ohne Namen, ohne Endung, ohne Kontext
---

## Briefing

Auf einem USB-Stick liegt eine einzelne Datei ohne Endung — kein
`.dcm`, kein Hinweis, woher sie stammt. Jemand vermutet, es könnte ein
DICOM-Objekt sein.

Deine Umgebung:

| System | Adresse | Was du darfst |
|---|---|---|
| Deine Workstation | 10.10.0.50 | Shell mit dcmftest, dcmdump — plus Befehlsvorlagen; die Datei liegt bereits lokal |

Deine Aufgaben: Nachweisen, dass es sich um ein DICOM-Objekt handelt.
Herausfinden, von welchem Gerätetyp sie stammt. Und den Namen der
Untersuchung als Flag eingeben.

Vorkenntnisse: Lektion 1.1. Rechne mit 8 Minuten.

## Hints

### h1

Die erste Frage bei jeder Datei unbekannter Herkunft: Ist es überhaupt
DICOM? `dcmftest` beantwortet genau das, ohne die Datei zu interpretieren.

### h2

Sobald das Format bestätigt ist, zeigt `dcmdump` den Inhalt. Die
Modality steht in einem eigenen Tag — sie sagt dir, von welcher Art
Gerät die Aufnahme stammt.

### h3

Der Name der Untersuchung steht im Tag `StudyDescription` — das ist
das Flag.

## Write-up

### Der Weg

1. Erst das Format prüfen, bevor irgendetwas interpretiert wird

```
$ dcmftest datei-ohne-namen
yes: datei-ohne-namen
```
**Was du daran abliest:** Die Datei erfüllt das DICOM-Dateiformat.
Das beantwortet die erste Aufgabe, ohne dass ein einziges Tag gelesen
wurde.

2. Den Inhalt auslesen

```
$ dcmdump datei-ohne-namen
I: (0008,0060) CS [US]  # xx, 1 Modality
I: (0008,1030) LO [Abdomen komplett]  # xx, 1 StudyDescription
```
**Was du daran abliest:** `Modality` `US` sagt: die Aufnahme stammt
von einem Ultraschallgerät, nicht von CT oder MRT. `StudyDescription`
nennt die Untersuchung selbst — genau die Information, nach der
gefragt war.

3. Flag: der Name der Untersuchung — `Abdomen komplett`.

### Was du mitnimmst

Eine DICOM-Datei trägt ihren Kontext immer bei sich — Patientendaten,
Gerätetyp, Untersuchungsname stehen im selben Objekt wie die
Bilddaten. Zwei Werkzeuge reichen für den ersten Kontakt mit einer
unbekannten Datei: `dcmftest` beantwortet die Formatfrage, `dcmdump`
liest danach alles Weitere aus. Das funktioniert unabhängig davon, ob
die Datei eine `.dcm`-Endung trägt — DICOM-Werkzeuge verlassen sich
nie auf den Dateinamen.

### Verwandte Inhalte

Lektion 1.1 — Was DICOM eigentlich ist
