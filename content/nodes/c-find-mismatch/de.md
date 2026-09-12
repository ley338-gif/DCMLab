---
title: Studien, die verschwinden
scenario_title: Das Archiv kennt die Studie, aber C-FIND findet sie nicht
---

## Briefing

Ein MR-Kopf von heute Vormittag soll befundet werden. Der Radiologe sucht
über die Patient ID aus der Anmeldeliste — nichts. Das Archiv-Team
schwört, die Studie liegt da.

Deine Umgebung:

| System | Adresse | Was du darfst |
|---|---|---|
| Deine Workstation | 10.50.0.50 | Shell mit findscu, dcmdump — plus Befehlsvorlagen |
| Archiv | 10.50.0.10 | C-FIND stellen; Konfiguration gesperrt (Herstellerzugang) |

Deine Aufgabe: Finde heraus, unter welcher Patient ID die Studie im Archiv
wirklich liegt, und gib sie als Flag ein.

```
$ findscu -S -k QueryRetrieveLevel=STUDY -k PatientID=MEYER,HANS \
          -k StudyInstanceUID -k StudyDescription \
          -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.50.0.10 104
I: Number of Matches: 0
```
**Was du daran abliest:** Die Anfrage geht durch (die Association steht),
aber es gibt keinen Treffer — das ist eine Aussage über die Daten, nicht
über die Verbindung.

Vorkenntnisse: Lektion 1.2, 1.3. Rechne mit 15 Minuten.

## Hints

### h1

Die Anmeldeliste (`anmeldeliste.txt`) zeigt, wie die Patient ID *getippt*
wurde. Ob im Archiv exakt derselbe Text steht, ist eine andere Frage —
C-FIND vergleicht zeichengenau, keine Toleranz für Leerzeichen.

### h2

Ersetze den exakten Wert durch ein Wildcard-Muster (`*` steht für eine
beliebige Zeichenfolge) und lies genau ab, was das Archiv als PatientID
tatsächlich zurückgibt.

### h3

`findscu -S -k QueryRetrieveLevel=STUDY -k PatientID=MEYER* -k PatientName -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.50.0.10 104`
liefert die echte Patient ID direkt im Ergebnis.

## Write-up

### Der Weg

1. Mit der Patient ID aus der Anmeldeliste suchen

```
$ findscu -S -k QueryRetrieveLevel=STUDY -k PatientID=MEYER,HANS \
          -k StudyInstanceUID -k StudyDescription \
          -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.50.0.10 104
I: Number of Matches: 0
```
**Was du daran abliest:** Kein Treffer heißt nicht "keine Studie da" —
C-FIND meldet nur, dass keine Study zu *dieser exakten* Anfrage passt.

2. Mit einem Wildcard-Muster suchen statt mit dem exakten Wert

```
$ findscu -S -k QueryRetrieveLevel=STUDY -k PatientID=MEYER* \
          -k PatientName -k StudyInstanceUID -k StudyDescription -k StudyDate \
          -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.50.0.10 104
I: # Dicom-Data-Set
I: (0008,0052) CS [STUDY]  # xx, 1 QueryRetrieveLevel
I: (0010,0020) LO [MEYER, HANS]  # xx, 1 PatientID
I: (0010,0010) PN [MEYER^HANS]  # xx, 1 PatientName
I: (0020,000d) UI [1.2.276.0.7230010.3.1.4.318852901447]  # xx, 1 StudyInstanceUID
I: (0008,1030) LO [MR Kopf nativ]  # xx, 1 StudyDescription
I: (0008,0020) DA [20260310]  # xx, 1 StudyDate
I: Number of Matches: 1
```
**Was du daran abliest:** Ein Treffer — und die zurückgegebene PatientID
`MEYER, HANS` trägt ein Leerzeichen nach dem Komma, das in der
Anmeldeliste fehlt. Zwei Zeichenketten, die ein Mensch als "denselben
Namen" liest, sind für ein Archiv zwei verschiedene Werte.

3. Flag: die echte Patient ID, wie sie im Archiv steht — `MEYER, HANS`.

### Was du mitnimmst

Ein leeres C-FIND-Ergebnis ist keine Aussage über den Datenbestand, nur
über die Anfrage. Wildcards (`*` für eine beliebige Zeichenfolge, `?` für
genau ein Zeichen) sind das Werkzeug, um einen unsicheren exakten Wert in
eine sichere Suche zu verwandeln — und das Ergebnis zeigt dir anschließend
den echten Wert, zeichengenau. Dieselbe Zeichengenauigkeit, die in
Lektion 1.6 AE Titles betrifft, gilt für jedes Matching-Feld in DICOM.

### Verwandte Inhalte

Lektion 1.2 — Patient → Study → Series → Instance: das Datenmodell
Lektion 1.3 — Tags, Groups, Elements, VR, Value Multiplicity
