# 0021 — P10.11: Node „Worklist leer" — Feature 6 (Modality Worklist)

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Die P10-Roadmap nennt "Worklist-Query" als eines von sieben
Engine-Features, mit dem Node `worklist-query-empty` (Lektion 4.7,
"Worklist ist leer") als Deliverable — bislang offen, da die Engine
kein eigenes Query/Retrieve Information Model für Modality Worklist
kannte. Wie bei jedem P10-Feature vorab zu verifizieren statt der
Roadmap zu vertrauen: die vom Nutzer explizit genannte SOP Class UID
`1.2.840.10008.5.1.4.31` wurde gegen `pydicom`s eigenes UID-Verzeichnis
geprüft — `UID(...).name` liefert real "Modality Worklist Information
Model - FIND", Typ "SOP Class". Bestätigt, keine Korrektur nötig.

Lektion 4.7s eigener Gerüst-Kommentar benennt zusätzlich einen
Infrastruktur-Blocker für ihren eigenen Fließtext: "Die Spielwiese hat
bisher keinen Worklist-Dienst" (`wlmscpfs` fehlt im Toolbox-Container).
Das betrifft nur den *Lektionstext* (der laut Abschnitt 8 echte
Sandbox-Beispiele bräuchte) — die *Node* nutzt wie immer die simulierte
Engine, nicht die Spielwiese, und ist davon unberührt.

## Entscheidung — Feature 6: Modality Worklist

**Neuer Engine-Mechanismus:** Modality Worklist ist real ein eigenes
Information Model, kein `QueryRetrieveLevel` wie STUDY/SERIES — deshalb
kein neues `records`-artiges Feld, sondern eine eigene Liste
`worklist` auf dem Archiv-Host, ausgelöst über das reale
`findscu`-Flag `-W` (`_parse_dcmtk_args` erkennt es jetzt als
`query_root = "WORKLIST"`, analog zu `-S`/`-P`). `app/find.py` bekommt
`WORKLIST_FIELD_MAP` und `find_worklist` — dieselbe
`dicom_wildcard_match`-Funktion aus Feature 1 (P10.1), nur gegen
geplante Verfahren statt vorhandener Studies. `rules.py` bekommt
`_exec_findscu_against_worklist`, analog zu
`_exec_findscu_against_records`. `-S`/`-P` bleiben unverändert auf
`records` beschränkt — beide Informationsmodelle sind und bleiben
getrennt, wie im echten Standard.

**Reale Tags, per pydicom verifiziert** (`dictionary_VR`/
`tag_for_keyword`): `ScheduledStationAETitle` (0040,0001) AE,
`ScheduledProcedureStepStartDate` (0040,0002) DA, `Modality` (0008,0060)
CS, `AccessionNumber` (0008,0050) SH — dieselbe Vereinfachung wie bei
STUDY/SERIES (flache Felder statt echter Sequenz-Verschachtelung in
`(0040,0100)`).

**Node-Design:** Ein CT-Gerät (`CT-5`) fragt die Worklist mit seinem
eigenen Calling AE Title als `ScheduledStationAETitle`-Filter ab —
liefert null Treffer, obwohl der RIS-Broker fünf Verfahren für heute
plant. Ursache: Calling AE Title (wie sich das Gerät selbst meldet) und
Scheduled Station AE Title (wie derselbe Raum in der RIS-Terminplanung
geführt wird) sind zwei unabhängig gepflegte Werte — ein reales,
alltägliches IT-Integrationsproblem. Genau das benennt Lektion 4.7s
eigener Plan als einen der beiden häufigsten Ursachen ("Modality-Filter
und Zeitfenster"). Die leere Antwort selbst ist technisch fehlerfrei
(`Number of Matches: 0`, kein Ablehnungscode) — der Lernpunkt ist zu
erkennen, dass eine leere Worklist-Antwort nichts über den tatsächlichen
Terminplan aussagt.

**`content/lessons/4.7/meta.yml`**: `lab.node` von `null` auf
`worklist-query-empty`, `optional: false` — die Node ist vollständig.
Der Lektionstext selbst bleibt Gerüst, bis die Spielwiese einen
Worklist-Dienst bekommt (separates, größeres Infrastrukturthema, nicht
Teil dieser Node).

## Manuell verifiziert

- `services/engine/tests/test_worklist.py` (neu, 6 Tests):
  Wildcard-Matching auf Worklist-Ebene, `-W` fragt die Worklist ab
  (nicht `records`), ein falscher Stations-Filter liefert eine gültige
  leere Antwort, `-S` bleibt von `worklist` unberührt, Nodes ohne
  `worklist` unverändert.
- `services/engine/tests/test_real_content.py`: neuer Test lädt die
  echte Node — bestätigt den falschen Filter (0 Treffer), die
  ungefilterte Abfrage (5 Treffer, zeigt den echten
  `ScheduledStationAETitle`), die korrigierte Abfrage (5 Treffer),
  Flag korrekt.
- Vollständige Engine-Testsuite (91 Tests), `ruff check .`: grün.
  `mypy app`: ein vorbestehender, unveränderter Fehler (fehlende
  `types-PyYAML`-Stubs).
- Alle drei Kommando-Ausgaben im Node-`de.md` wurden tatsächlich gegen
  die echte, ausgelieferte Node ausgeführt (nicht von Hand geschrieben)
  und wörtlich übernommen — inklusive aller fünf vollständigen
  `Dicom-Data-Set`-Blöcke, keine Kürzung.
- **Fehler beim Erstverifizieren gefunden und behoben:** Der Flag-Hash
  war zunächst gegen den unveränderten Groß-/Kleinschreibungs-Wert
  `"CT5-RAUM3"` berechnet, aber `case_sensitive: false` normalisiert vor
  dem Hashen auf Kleinschreibung (`app/flag.py: normalize`) — der reale
  `check_flag`-Aufruf in `test_real_content.py` schlug deshalb zunächst
  fehl. Behoben durch Neuberechnung des Hashs über `"ct5-raum3"`. Bei
  allen bisherigen P10-Nodes war das nicht aufgefallen, weil ihre
  Flags reine UIDs waren (nur Ziffern und Punkte, kein Fall
  unterscheidbar) — diese Node ist die erste mit einem alphabetischen
  Flag-Wert.

## Nicht gebaut / offen (siehe `docs/content-todo.md`)

- Lektion 4.7s eigener Fließtext bleibt Gerüst, bis die Spielwiese
  einen Worklist-Dienst (`wlmscpfs`) und einen Worklist-Testdatensatz
  bereitstellt — ein separates Infrastrukturthema.
- Der zweite in der Lektion genannte Ursachen-Typ (zu enges
  Zeitfenster) ist mit dieser Node nicht abgedeckt — ein möglicher,
  eigenständiger Folge-Node.
