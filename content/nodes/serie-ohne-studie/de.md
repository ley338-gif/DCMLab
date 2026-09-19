---
title: Serie ohne Studie
scenario_title: Eine Abfrage, die strukturell nicht funktionieren kann
---

## Briefing

Ein Kollege hat ein Ticket geschrieben (`ticket.txt`): Er braucht die
Serien einer bekannten Untersuchung für den Viewer-Import, aber seine
Abfrage kommt mit einem Fehler zurück statt mit Serien. Das Archiv-Team
beteuert, die Untersuchung liegt vor.

Deine Umgebung:

| System | Adresse | Was du darfst |
|---|---|---|
| Deine Workstation | 10.33.0.50 | Shell mit findscu — plus Befehlsvorlagen |
| Archiv | 10.33.0.10 | C-FIND stellen; Konfiguration gesperrt (Herstellerzugang) |

Deine Aufgabe: Finde heraus, warum die Abfrage des Kollegen scheitert,
korrigiere genau den fehlerhaften Teil, und gib die Study Instance UID
der Untersuchung als Flag ein.

Vorkenntnisse: Lektion 2.3. Rechne mit 15 Minuten.

## Hints

### h1

Die Association steht, das Archiv antwortet — das schließt Netzwerk-
und Verbindungsprobleme aus. Sieh dir stattdessen genau an, auf welcher
Ebene und mit welchen Matching-Keys die Abfrage des Kollegen gestellt
wurde, bevor du eine neue Abfrage schickst.

### h2

Eine `SERIES`-Abfrage braucht laut DICOM-Query/Retrieve-Modell zwingend
die Study Instance UID als Matching Key — unabhängig davon, welche
anderen Felder sonst angegeben sind. Prüfe, ob die Abfrage des Kollegen
diese überhaupt enthält.

### h3

Ermittle die Study Instance UID zuerst über eine Abfrage auf
`STUDY`-Ebene mit der Accession Number, und stelle die `SERIES`-Abfrage
danach mit genau dieser UID.

## Write-up

### Was beobachtet wurde

```
$ findscu -S -k QueryRetrieveLevel=SERIES -k AccessionNumber=A66210 \
          -k SeriesInstanceUID -k SeriesDescription \
          -aet DCMLAB-WS -aec NEURO-PACS 10.33.0.10 104
E: Find Failed, query keys:
E: Status: 0xa900 Identifier does not match SOP Class
```

**Was du daran abliest:** Die Association kommt zustande — das Archiv
antwortet mit einem echten, im Standard definierten Fehlerstatus, nicht
mit Schweigen oder einer Verbindungsablehnung. `0xa900` ist eine
fachliche Ablehnung der Anfrage selbst, keine Aussage über den
Datenbestand.

### Welche Hypothesen möglich waren

1. **Die Untersuchung ist nicht im Archiv.** Widerlegt: Das Archiv-Team
   bestätigt ihre Existenz, und eine `STUDY`-Ebene-Abfrage mit derselben
   Accession Number findet sie tatsächlich (siehe unten).
2. **Verbindung oder Association sind gestört.** Widerlegt: Die
   Association wird akzeptiert, das Archiv antwortet mit einem
   regulären DICOM-Statuscode — das ist bereits ein technischer Erfolg
   auf Transportebene.
3. **Die Abfrage verwendet das falsche Query/Retrieve-Level für die
   verfügbaren Matching-Keys.** Bestätigt: Eine `SERIES`-Abfrage
   verlangt zwingend die Study Instance UID als Matching Key. Die
   Accession Number allein reicht auf dieser Ebene nicht, egal wie
   eindeutig sie fachlich ist.

### Welche Evidenz die Hypothesen trennt

```
$ findscu -S -k QueryRetrieveLevel=STUDY -k AccessionNumber=A66210 \
          -k PatientID -k PatientName -k StudyInstanceUID \
          -k StudyDescription -aet DCMLAB-WS -aec NEURO-PACS 10.33.0.10 104
I: # Dicom-Data-Set
I: (0010,0020) LO [7734]  # PatientID
I: (0010,0010) PN [HOFFMANN^LARS]  # PatientName
I: (0020,000d) UI [1.2.276.0.7230010.3.1.4.660412998215]  # StudyInstanceUID
I: (0008,1030) LO [MR Kopf nativ]  # StudyDescription
I: Number of Matches: 1
```

**Was du daran abliest:** Auf `STUDY`-Ebene liefert dieselbe Accession
Number sofort einen Treffer — die Untersuchung existiert und ist über
diesen Weg auffindbar. Das beweist: Der Datenbestand war nie das
Problem, nur die Ebene der ursprünglichen Abfrage.

```
$ findscu -S -k QueryRetrieveLevel=SERIES \
          -k StudyInstanceUID=1.2.276.0.7230010.3.1.4.660412998215 \
          -k SeriesInstanceUID -k SeriesDescription \
          -aet DCMLAB-WS -aec NEURO-PACS 10.33.0.10 104
I: (0008,103e) LO [T1 sag]  # SeriesDescription
I: (0008,103e) LO [T2 tra]  # SeriesDescription
I: Number of Matches: 2
```

**Was du daran abliest:** Mit der jetzt bekannten Study Instance UID als
Matching Key liefert dieselbe `SERIES`-Abfrage genau die zwei Serien,
die der Kollege für den Viewer-Import brauchte.

### Wo die erste fehlerhafte Stelle liegt

Nicht im Archiv, nicht im Datenbestand und nicht in der Verbindung — die
ursprüngliche Abfrage selbst war für ihr gewähltes Query/Retrieve-Level
strukturell unvollständig. Im Baseline- (hierarchischen) Query/Retrieve-
Modell verlangt `SERIES` die Study Instance UID des darüberliegenden
Levels als Matching Key; ohne sie ist die Anfrage nicht auswertbar, ganz
gleich, wie eindeutig die übrigen Matching-Keys fachlich wären. Dieses
Archiv unterstützt nur das Baseline-Verhalten — DICOM kennt daneben
optional Relational Queries (per Extended Negotiation), die genau diese
Hierarchie lockern können, aber das ist eine gesondert auszuhandelnde
Fähigkeit, keine Selbstverständlichkeit.

### Saubere betriebliche Maßnahme

Erst auf `STUDY`-Ebene mit einem dort gültigen Matching-Key (hier:
Accession Number) die Study Instance UID ermitteln, dann gezielt auf
`SERIES`-Ebene mit dieser UID nachfragen — genau die Zwei-Schritt-Folge,
die Lektion 2.3 als Regel nennt: eine Abfrage arbeitet immer auf genau
einer Ebene.

### Was du mitnimmst

Ein DICOM-Fehlerstatus bei C-FIND ist keine Aussage über den
Datenbestand, sondern über die Anfrage selbst. `0xa900` unterscheidet
sich fachlich deutlich von `Number of Matches: 0` — Ersteres heißt „die
Anfrage passt nicht zum gewählten Query-Modell", Letzteres heißt „die
Anfrage war gültig, aber nichts passt". Beide sind technische Erfolge
der Verbindung und sagen nichts darüber, ob die Daten existieren.

### Verwandte Inhalte

Lektion 2.3 — C-FIND: Query-Level, Matching-Keys, Wildcards
