---
title: Der Patient existiert zweimal
scenario_title: Die Überweisung nennt eine Patient ID, unter der nichts liegt
---

## Briefing

Eine CT-Abdomen-Studie soll befundet werden. Die Überweisung nennt eine
Patient ID — im Archiv findest du darunter nur eine leere Registrierung,
keine Study.

Deine Umgebung:

| System | Adresse | Was du darfst |
|---|---|---|
| Deine Workstation | 10.80.0.50 | Shell mit findscu — plus Befehlsvorlagen |
| Archiv | 10.80.0.10 | C-FIND stellen; Konfiguration gesperrt (Herstellerzugang) |

Deine Aufgabe: Finde heraus, unter welcher Patient ID die Study wirklich
liegt, und gib sie als Flag ein.

```
$ findscu -S -k QueryRetrieveLevel=STUDY -k PatientID=00123 \
          -k StudyInstanceUID -k StudyDescription \
          -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.80.0.10 104
I: # Dicom-Data-Set
I: (0008,0052) CS [STUDY]  # xx, 1 QueryRetrieveLevel
I: (0010,0020) LO [00123]  # xx, 1 PatientID
I: (0010,0010) PN [WEBER^PETRA]  # xx, 1 PatientName
I: Number of Matches: 1
```
**Was du daran abliest:** Ein Treffer — die Patient ID existiert also —,
aber ohne `StudyInstanceUID` oder `StudyDescription` in der Antwort. Diese
Registrierung trägt keine Study.

Vorkenntnisse: Lektion 1.2, 1.3. Rechne mit 15 Minuten.

## Hints

### h1

Die Patient ID aus der Überweisung existiert im Archiv, aber ohne Study.
Vielleicht ist dieselbe Person unter einer zweiten ID registriert — suche
über den Namen statt über die ID.

### h2

`findscu` mit einem Wildcard-Muster auf `PatientName` findet alle
Registrierungen einer Person, unabhängig von der jeweiligen Patient ID.

### h3

`findscu -S -k QueryRetrieveLevel=STUDY -k PatientName=WEBER* -k PatientID -k StudyInstanceUID -k StudyDescription -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.80.0.10 104`
liefert zwei Treffer — nimm die Patient ID aus dem Treffer, der eine
Study trägt.

## Write-up

### Der Weg

1. Mit der Patient ID aus der Überweisung suchen

```
$ findscu -S -k QueryRetrieveLevel=STUDY -k PatientID=00123 \
          -k StudyInstanceUID -k StudyDescription \
          -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.80.0.10 104
I: # Dicom-Data-Set
I: (0008,0052) CS [STUDY]  # xx, 1 QueryRetrieveLevel
I: (0010,0020) LO [00123]  # xx, 1 PatientID
I: (0010,0010) PN [WEBER^PETRA]  # xx, 1 PatientName
I: Number of Matches: 1
```
**Was du daran abliest:** Die ID stimmt, aber die Registrierung ist leer
— kein Hinweis auf eine Study. Die Überweisung war korrekt abgetippt,
trotzdem fehlt etwas.

2. Über den Patientennamen suchen statt über die ID

```
$ findscu -S -k QueryRetrieveLevel=STUDY -k PatientName=WEBER* -k PatientID \
          -k StudyInstanceUID -k StudyDescription \
          -aet DCMLAB-WS -aec KLINIK-ARCHIV 10.80.0.10 104
I: # Dicom-Data-Set
I: (0008,0052) CS [STUDY]  # xx, 1 QueryRetrieveLevel
I: (0010,0020) LO [00123]  # xx, 1 PatientID
I: (0010,0010) PN [WEBER^PETRA]  # xx, 1 PatientName
I: # Dicom-Data-Set
I: (0008,0052) CS [STUDY]  # xx, 1 QueryRetrieveLevel
I: (0010,0020) LO [000123]  # xx, 1 PatientID
I: (0010,0010) PN [WEBER^PETRA]  # xx, 1 PatientName
I: (0020,000d) UI [1.2.276.0.7230010.3.1.4.412738605519]  # xx, 1 StudyInstanceUID
I: (0008,1030) LO [CT Abdomen nativ]  # xx, 1 StudyDescription
I: (0008,0020) DA [20260228]  # xx, 1 StudyDate
I: Number of Matches: 2
```
**Was du daran abliest:** Zwei Registrierungen derselben Person, zwei
verschiedene Patient IDs — `00123` und `000123` (eine führende Null
unterscheidet sie). Nur `000123` trägt tatsächlich die Study.

3. Flag: die Patient ID mit der echten Study — `000123`.

### Was du mitnimmst

Eine Patient ID, die existiert, garantiert keine vollständige
Registrierung — sie kann leer sein, während dieselbe Person unter einer
zweiten ID die eigentlichen Daten trägt. Der Patientenname ist in so
einem Fall der zuverlässigere Suchweg, gerade weil er (anders als die ID)
nicht Ziel des Tippfehlers oder der doppelten Anlage war. Das ist derselbe
Mechanismus wie bei einer falsch abgetippten ID (siehe Node
„Studien, die verschwinden“) — nur diesmal mit zwei echten, aber
unterschiedlichen IDs für dieselbe Person statt einem einzelnen Tippfehler.

### Verwandte Inhalte

Lektion 1.2 — Patient → Study → Series → Instance: das Datenmodell
Lektion 1.3 — Tags, Groups, Elements, VR, Value Multiplicity
Node „Studien, die verschwinden“ (medium) — dieselbe Suchtechnik, anderer Fehler
