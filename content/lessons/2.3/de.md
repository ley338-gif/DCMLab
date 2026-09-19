---
title: "C-FIND: Query-Level, Matching-Keys, Wildcards"
teaser: Suchen ohne zu übertragen — und die Kunst, mit Bruchstücken statt mit exakten Werten zu fragen.
objectives:
  - Query-Retrieve-Level (PATIENT/STUDY/SERIES/IMAGE) unterscheiden
  - Matching-Keys von Rückgabefeldern trennen
  - Eine Studie mit Wildcards statt exakten Werten finden
---

## Man kennt nur den Nachnamen

Eine Anfrage aus der Anmeldung: „Gibt es eine Studie von jemandem, der
ungefähr Muster heißt?" Keine StudyInstanceUID, kein exakter
Vorname — nur ein Bruchstück. Genau für diesen Fall ist
{{term:c-find}} gemacht.

## Eine Ebene pro Abfrage

Eine C-FIND-Abfrage arbeitet immer auf genau einer Ebene — PATIENT,
STUDY, SERIES oder IMAGE — nicht auf mehreren gleichzeitig:

```
$ findscu -v -S -k QueryRetrieveLevel=STUDY -k PatientName=MUSTER^ERIKA \
          -k PatientID -k StudyDescription -k StudyInstanceUID \
          -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242
I: Requesting Association
I: Association Accepted
I: Sending Find Request: MsgID 1
I: 
I: # Request Identifier
I: (0008,0052) CS [STUDY]                                  # 1 QueryRetrieveLevel
I: (0008,1030) LO (no value available)                     # 0 StudyDescription
I: (0010,0010) PN [MUSTER^ERIKA]                           # 1 PatientName
I: (0010,0020) LO (no value available)                     # 0 PatientID
I: (0020,000D) UI (no value available)                     # 0 StudyInstanceUID
I: 
I: Find SCP Response: 1 - 0xFF00 (Pending)
I: 
I: # Response Identifier
I: (0008,0005) CS [ISO_IR 100]                             # 1 SpecificCharacterSet
I: (0008,0052) CS [STUDY]                                  # 1 QueryRetrieveLevel
I: (0008,0054) AE [ORTHANC]                                # 1 RetrieveAETitle
I: (0008,1030) LO [CT Thorax nativ]                        # 1 StudyDescription
I: (0010,0010) PN [MUSTER^ERIKA]                           # 1 PatientName
I: (0010,0020) LO [4711]                                   # 1 PatientID
I: (0020,000D) UI [1.2.826.0.1.3680043.8.498.52598660049033215684825773717865486783] # 1 StudyInstanceUID
I: 
I: Find SCP Result: 0x0000 (Success)
I: Releasing Association
```
**Was du daran abliest:** Ein `-k` mit Wert (`PatientName=MUSTER^ERIKA`)
ist ein Matching-Key — er filtert. Ein `-k` ohne Wert
(`PatientID`, `StudyDescription`, `StudyInstanceUID`) ist ein
Rückgabefeld — er bittet nur um den Wert, ohne selbst einzuschränken.
Die Antwort füllt genau diese leeren Felder.

## Mit einem Bruchstück statt einem exakten Wert

```
$ findscu -v -S -k QueryRetrieveLevel=STUDY -k "PatientName=MUST*" \
          -k PatientID -k StudyDescription -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242
I: Requesting Association
I: Association Accepted
I: Sending Find Request: MsgID 1
I: 
I: # Request Identifier
I: (0008,0052) CS [STUDY]                                  # 1 QueryRetrieveLevel
I: (0008,1030) LO (no value available)                     # 0 StudyDescription
I: (0010,0010) PN [MUST*]                                  # 1 PatientName
I: (0010,0020) LO (no value available)                     # 0 PatientID
I: 
I: Find SCP Response: 1 - 0xFF00 (Pending)
I: 
I: # Response Identifier
I: (0008,0005) CS [ISO_IR 100]                             # 1 SpecificCharacterSet
I: (0008,0052) CS [STUDY]                                  # 1 QueryRetrieveLevel
I: (0008,0054) AE [ORTHANC]                                # 1 RetrieveAETitle
I: (0008,1030) LO [CT Thorax nativ]                        # 1 StudyDescription
I: (0010,0010) PN [MUSTER^ERIKA]                           # 1 PatientName
I: (0010,0020) LO [4711]                                   # 1 PatientID
I: 
I: Find SCP Result: 0x0000 (Success)
I: Releasing Association
```
**Was du daran abliest:** `MUST*` findet denselben Datensatz wie der
exakte Name oben — `*` steht für eine beliebige Zeichenfolge, `?` (hier
nicht gezeigt) für genau ein Zeichen. Genau das ist die Anfrage aus der
Anmeldung: „ungefähr Muster" reicht.

## Eine Ebene tiefer: mehrere Treffer auf einmal

```
$ findscu -v -S -k QueryRetrieveLevel=SERIES -k PatientID=4711 \
          -k SeriesInstanceUID -k SeriesDescription -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242
I: Find SCP Response: 1 - 0xFF00 (Pending)
I: (0008,103E) LO [Thorax 1.0 B70f]                        # 1 SeriesDescription
I: (0020,000E) UI [1.2.826.0.1.3680043.8.498.4338920864261375266257595380712726263] # 1 SeriesInstanceUID
I: 
I: Find SCP Response: 2 - 0xFF00 (Pending)
I: (0008,103E) LO [Thorax 5.0 B31f]                        # 1 SeriesDescription
I: (0020,000E) UI [1.2.826.0.1.3680043.8.498.99488604269505747968361656265259609108] # 1 SeriesInstanceUID
I: 
I: Find SCP Result: 0x0000 (Success)
I: Releasing Association
```
**Was du daran abliest:** Auf SERIES-Ebene kommen zwei getrennte
`Find SCP Response`-Blöcke zurück — die Studie besteht tatsächlich aus
zwei Serien (dünne und dicke Schichten, siehe „Liegt bereit" oben).
Jede Ebene tiefer kann mehr als einen Treffer liefern; PATIENT- oder
STUDY-Ebene fasst das noch zu einem Datensatz zusammen.

## Eine leere Antwort ist eine gültige Antwort

```
$ findscu -v -S -k QueryRetrieveLevel=STUDY -k PatientName=SCHMIDT^HANS \
          -k PatientID -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242
I: Requesting Association
I: Association Accepted
I: Sending Find Request: MsgID 1
I: 
I: # Request Identifier
I: (0008,0052) CS [STUDY]                                  # 1 QueryRetrieveLevel
I: (0010,0010) PN [SCHMIDT^HANS]                           # 1 PatientName
I: (0010,0020) LO (no value available)                     # 0 PatientID
I: 
I: Find SCP Result: 0x0000 (Success)
I: Releasing Association
```
**Was du daran abliest:** Kein Treffer für einen Namen, der schlicht
nicht vorkommt — `Find SCP Result: 0x0000 (Success)` ohne eine einzige
`Find SCP Response`-Zeile davor. Genau wie bei der Worklist (Lektion
2.5) ist eine leere Antwort technisch korrekt, kein Fehler.

## Auf der Leitung: Anfrage und Antwort als eigene Pakete

```
$ tshark -i lo -f "tcp port 4242" -Y dicom
4   0.000897   127.0.0.1 → 127.0.0.1   DICOM 2731 A-ASSOCIATE request MEINE-WS --> ORTHANC
6   0.001090   127.0.0.1 → 127.0.0.1   DICOM 792  A-ASSOCIATE accept  MEINE-WS <-- ORTHANC
8   0.055715   127.0.0.1 → 127.0.0.1   DICOM 166  P-DATA, C-FIND-RQ ID=1
10  0.096133   127.0.0.1 → 127.0.0.1   DICOM 112  P-DATA, C-FIND-RQ-DATA
14  0.096749   127.0.0.1 → 127.0.0.1   DICOM 154  P-DATA, C-FIND-RSP ID=1
18  0.096769   127.0.0.1 → 127.0.0.1   DICOM 134  P-DATA, C-FIND-RSP-DATA
22  0.096792   127.0.0.1 → 127.0.0.1   DICOM 154  P-DATA, C-FIND-RSP ID=1 (Success)
24  0.099287   127.0.0.1 → 127.0.0.1   DICOM 76   A-RELEASE request
25  0.099354   127.0.0.1 → 127.0.0.1   DICOM 76   A-RELEASE response
```
**Was du daran abliest:** Die Matching-Keys selbst reisen als eigenes
`C-FIND-RQ-DATA`-Paket, getrennt vom `C-FIND-RQ`-Kommando — und die
Antwortwerte entsprechend als eigenes `C-FIND-RSP-DATA`. Das
`C-FIND-RSP ID=1 (Success)` ganz am Ende ist die
Abschlussmeldung, nicht die eigentliche Antwort mit den Werten.

## Im Alltag

| Frage | Womit prüfen |
|---|---|
| Auf welcher Ebene suche ich? | PATIENT/STUDY/SERIES/IMAGE — eine pro Abfrage |
| Was filtert, was liefert nur einen Wert? | `-k` mit Wert = Matching-Key, `-k` ohne Wert = Rückgabefeld |
| Nur ein Bruchstück bekannt? | `*`/`?` als Wildcard im Matching-Key |

## Stolperfallen

- **Matching-Key und Rückgabefeld verwechseln.** Ein `-k` ohne Wert
  filtert nicht — er fragt nur den Wert ab.
- **Zu enge Wildcards.** `MUSTER` statt `MUST*` findet nur exakte
  Schreibweisen; ein zu kurzer Wildcard-Rest (`M*`) liefert dagegen
  unter Umständen mehr Treffer als gewollt.
- **Eine leere Antwort für einen Fehler halten.** `Success` ohne
  Treffer ist ein gültiges Ergebnis, kein technisches Problem.

## Lab

Im Node **„Serie ohne Studie"** bekommst du eine Abfrage, die scheitert, obwohl die gesuchte Untersuchung nachweislich im Archiv liegt. Finde heraus, welcher Teil der Anfrage nicht zum gewählten Query-Level passt.

## Selbstcheck

1. Worin unterscheidet sich ein Matching-Key von einem Rückgabefeld in
   derselben Abfrage?
2. Eine SERIES-Abfrage liefert zwei `Find SCP Response`-Blöcke für
   dieselbe Studie. Was bedeutet das über die Studie?
3. Eine `findscu`-Abfrage mit einem nicht vorkommenden Namen liefert
   `Success` ohne jede Antwortzeile. Ist das ein Fehler?
