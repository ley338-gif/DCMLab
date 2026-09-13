# 0015 — P10.5: Node `zwillinge` vollständig — Accession Number statt neuer Engine-Logik

Status: akzeptiert
Datum: 2026-09-13

## Kontext

`docs/content-todo.md` vermerkte seit P10.1, dass `zwillinge` (Lektion
1.4, "zwei gleich aussehende Studies mit verschiedener UID unterscheiden")
durch den `records`-Mechanismus aus Feature 1 (ADR 0011) technisch lösbar
geworden ist, aber noch kein Fließtext geschrieben wurde. Diese Phase
holt das nach.

## Entscheidungen

**Neues, reales Feld `AccessionNumber` (0008,0050, VR SH) ergänzt, statt
nur PatientID/StudyDate zur Unterscheidung zu nutzen.** Zwei Studies mit
identischer Beschreibung sind im Alltag der Normalfall bei
Verlaufskontrollen — die Accession Number ist das dafür vorgesehene
DICOM-Feld, das eine Anforderung eindeutig einer Study zuordnet (üblich
in RIS/HIS-Integration). Ohne dieses Feld hätte die Node nur auf
`StudyDate` ausweichen können, was weniger realistisch und leichter zu
erraten gewesen wäre. Tag und VR sind reale, aus PS3.6 stammende Werte,
vor Verwendung wie in ADR 0011/0013 nicht blind übernommen, sondern nach
bestem Wissen als Standardwert geprüft.

**Kein neuer Engine-Code für die eigentliche Lernaufgabe.** Wie schon bei
`patient-merge-discovery` (ADR 0014) reicht der bestehende
`records`-Mechanismus: zwei Einträge mit identischem `patient_id` und
`study_description`, aber unterschiedlicher `study_uid` und
`accession_number`. Einzige Erweiterung war, `AccessionNumber` als
zusätzliches matchbares und anzeigbares Feld in `find.py`/`rules.py`
aufzunehmen — eine Zeile in einer Zuordnungstabelle, keine neue Regel.

**`lab.optional` zurück auf `false`.** Lektion 1.4 verwies im
ursprünglich mitgelieferten Content bereits mit `optional: false` auf
`zwillinge` — dieselbe Behandlung wie bei `neue-node` (P9): sobald eine
Node wirklich fertig ist, bekommt sie ihren ursprünglich vorgesehenen
Pflichtstatus zurück.

## Manuell verifiziert (gegen den echten Stack)

- Suche nach `PatientID=4711` liefert zwei Studies mit identischer
  `StudyDescription`, aber unterschiedlichem `StudyDate` und
  `AccessionNumber`.
- Gezielte Suche nach `AccessionNumber=R2026-08812` liefert genau eine
  Study mit der erwarteten `StudyInstanceUID`.
- Flag korrekt — über die echte Session/API gegen den laufenden Stack
  geprüft, 10 Punkte vergeben.
- Lektion 1.4 zeigt korrekt „Lab: Node „Zwillinge" (easy, 10 Pkt.)".
- `content:validate`: keine neuen Verstöße. `pytest`/`ruff`/`mypy` für
  `services/engine`: 70 Tests grün (2 neu: ein Unit-Test für
  Accession-Number-Matching, ein Ende-zu-Ende-Test gegen den echten
  Node-Content).

## Nicht gebaut (bewusst)

`zwei-ebenen-tiefer` (1.2) bleibt Gerüst — sie bräuchte zusätzlich eine
echte PATIENT- und INSTANCE-Ebene im C-FIND (aktuell nur STUDY/SERIES),
was über die reine Mehrfach-Bestand-Fähigkeit aus Feature 1 hinausgeht.
