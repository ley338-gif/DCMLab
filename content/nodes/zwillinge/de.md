---
title: Zwillinge
scenario_title: Zwei Studies, die auf den ersten Blick identisch aussehen
---

## Briefing

Ein Befund soll zu einer aktuellen CT-Thorax-Anforderung geschrieben
werden. Im Archiv liegen zwei CT-Thorax-Studies derselben Patientin —
gleiche Beschreibung, unterschiedliches Datum.

Deine Umgebung:

| System | Adresse | Was du darfst |
|---|---|---|
| Deine Workstation | 10.90.0.50 | Shell mit findscu — plus Befehlsvorlagen |
| Archiv | 10.90.0.10 | C-FIND stellen; Konfiguration gesperrt (Herstellerzugang) |

Deine Aufgabe: Finde die Study Instance UID der Study, die zur
Anforderung (`anforderung.txt`) gehört, und gib sie als Flag ein.

```
$ findscu -S -k QueryRetrieveLevel=STUDY -k PatientID=4711 \
          -k StudyInstanceUID -k StudyDescription -k AccessionNumber \
          -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.90.0.10 104
I: Number of Matches: 2
```
**Was du daran abliest:** Zwei Studies, gleiche Patientin, wahrscheinlich
gleiche Beschreibung — die Patient ID allein reicht nicht, um die richtige
auszuwählen.

Vorkenntnisse: Lektion 1.2, 1.3. Rechne mit 15 Minuten.

## Hints

### h1

`anforderung.txt` nennt eine Accession Number — ein Feld, das speziell
dafür gedacht ist, eine Anforderung eindeutig einer Study zuzuordnen.

### h2

Filtere direkt nach `AccessionNumber`, statt beide Treffer der
Patienten-Suche manuell zu vergleichen.

### h3

`findscu -S -k QueryRetrieveLevel=STUDY -k AccessionNumber=R2026-08812 -k StudyInstanceUID -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.90.0.10 104`
liefert genau eine Study — deren `StudyInstanceUID` ist das Flag.

## Write-up

### Der Weg

1. Nach der Patientin suchen

```
$ findscu -S -k QueryRetrieveLevel=STUDY -k PatientID=4711 \
          -k StudyInstanceUID -k StudyDescription -k AccessionNumber \
          -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.90.0.10 104
I: # Dicom-Data-Set
I: (0008,0052) CS [STUDY]  # xx, 1 QueryRetrieveLevel
I: (0010,0020) LO [4711]  # xx, 1 PatientID
I: (0010,0010) PN [MUSTER^ERIKA]  # xx, 1 PatientName
I: (0020,000d) UI [1.2.276.0.7230010.3.1.4.541902387011]  # xx, 1 StudyInstanceUID
I: (0008,1030) LO [CT Thorax nativ]  # xx, 1 StudyDescription
I: (0008,0020) DA [20260110]  # xx, 1 StudyDate
I: (0008,0050) SH [R2026-04471]  # xx, 1 AccessionNumber
I: # Dicom-Data-Set
I: (0008,0052) CS [STUDY]  # xx, 1 QueryRetrieveLevel
I: (0010,0020) LO [4711]  # xx, 1 PatientID
I: (0010,0010) PN [MUSTER^ERIKA]  # xx, 1 PatientName
I: (0020,000d) UI [1.2.276.0.7230010.3.1.4.541902387012]  # xx, 1 StudyInstanceUID
I: (0008,1030) LO [CT Thorax nativ]  # xx, 1 StudyDescription
I: (0008,0020) DA [20260305]  # xx, 1 StudyDate
I: (0008,0050) SH [R2026-08812]  # xx, 1 AccessionNumber
I: Number of Matches: 2
```
**Was du daran abliest:** Beide Studies teilen sich Patient ID und
Beschreibung — ohne ein weiteres Unterscheidungsmerkmal ist unklar,
welche gemeint ist. `StudyDate` und `AccessionNumber` unterscheiden sich
aber bereits hier.

2. Gezielt nach der Accession Number aus der Anforderung suchen

```
$ findscu -S -k QueryRetrieveLevel=STUDY -k AccessionNumber=R2026-08812 \
          -k StudyInstanceUID \
          -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.90.0.10 104
I: # Dicom-Data-Set
I: (0008,0052) CS [STUDY]  # xx, 1 QueryRetrieveLevel
I: (0010,0020) LO [4711]  # xx, 1 PatientID
I: (0010,0010) PN [MUSTER^ERIKA]  # xx, 1 PatientName
I: (0020,000d) UI [1.2.276.0.7230010.3.1.4.541902387012]  # xx, 1 StudyInstanceUID
I: (0008,1030) LO [CT Thorax nativ]  # xx, 1 StudyDescription
I: (0008,0020) DA [20260305]  # xx, 1 StudyDate
I: (0008,0050) SH [R2026-08812]  # xx, 1 AccessionNumber
I: Number of Matches: 1
```
**Was du daran abliest:** Genau ein Treffer — die Accession Number
verweist eindeutig auf diese eine Study, unabhängig davon, wie viele
andere Studies derselben Patientin mit ähnlicher Beschreibung existieren.

3. Flag: die Study Instance UID dieser Study —
   `1.2.276.0.7230010.3.1.4.541902387012`.

### Was du mitnimmst

"Gleich aussehende" Studies (gleicher Patient, gleiche Beschreibung) sind
im Alltag normal, nicht die Ausnahme — Verlaufskontrollen erzeugen sie
systematisch. PatientID und StudyDescription identifizieren einen
Menschen und eine Untersuchungsart, aber nicht eine einzelne Study. Die
Accession Number existiert genau für diesen Zweck: eine Anforderung
eindeutig einer Study zuzuordnen, unabhängig von Datum oder Beschreibung.
Die Study Instance UID ist die technisch eindeutige Kennung darunter —
die Accession Number ist der Weg dorthin.

### Verwandte Inhalte

Lektion 1.2 — Patient → Study → Series → Instance: das Datenmodell
Lektion 1.4 — UIDs: warum alles eine Nummer hat, Root-UIDs, Eindeutigkeit
Node „Studien, die verschwinden“ (medium) — dieselbe Suchtechnik, anderer Fehler
