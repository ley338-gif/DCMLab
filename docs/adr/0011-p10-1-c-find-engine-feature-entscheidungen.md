# 0011 — P10.1: Echtes C-FIND-Matching (Engine-Feature 1) + Node `c-find-mismatch`

Status: akzeptiert
Datum: 2026-09-13

## Kontext

`P10-Roadmap-DCMLab.md` verlangt sieben Engine-Features, um die in P9
dokumentierten Node-Gerüste spielbar zu machen. Dieses ADR deckt das erste
Feature ab: echtes C-FIND-Matching (Query-Level, Wildcards) statt der
bisherigen Ja/Nein-Prüfung "wurde vorher etwas gesendet". Die Roadmap ist
ein extern geliefertes Dokument, kein Teil des ursprünglichen Auftrags —
wo sie ihm widerspricht oder unbelegte Fakten verlangt, gilt weiterhin
Abschnitt 13 des Auftrags ("nie Prosa oder Werkzeugausgaben erfinden").

## Entscheidungen

**Neues Modul `app/find.py` statt Erweiterung von `rules.py`.** Die
Roadmap schlägt eine Klasse `FindQuery` vor; die bestehende Codebasis
kennt aber kein Klassen-Regelwerk, sondern reine Funktionen auf einem
`state`-Dict (siehe `rules.py`-Docstring). `find.py` übernimmt dieses
Muster: zwei reine, ungetypt-generische Funktionen (`find_studies`,
`find_series`) plus das eigentliche Wildcard-Matching
(`dicom_wildcard_match`) — vollständig isoliert testbar ohne Session-
oder DB-Zustand, wie von der Roadmap selbst gefordert ("isolierte,
testbare Erweiterung").

**Archiv-Hosts bekommen optionale `records` statt eines globalen
Zustandsmodells.** Statt das bestehende `bestand`-Modell (ein einziger
Sende-Zähler pro Archiv-Host, siehe P4-P9) zu ersetzen, ergänzt `records`
es: ein Host hat entweder vordefinierte Studies (neue Nodes, bei denen
das Archiv von Anfang an etwas enthält) oder das alte Sende-Modell
(Silent CT, Wrong Door, Neue Node bleiben unverändert lauffähig — siehe
`content-schema.md` Abschnitt 6a). Kein Host hat je beides.

**DICOM-Wildcards echt implementiert (PS3.4 C.2.2.2.4), kein Fantasie-
Matching.** `*` für eine beliebige Zeichenfolge (auch leer), `?` für
genau ein Zeichen, sonst zeichengenauer Vergleich — dieselbe Disziplin
wie AE Titles in Abschnitt 5.3. Die Feld-Tags (PatientID `0010,0020`,
PatientName `0010,0010`, StudyInstanceUID `0020,000D`, StudyDescription
`0008,1030`, StudyDate `0008,0020`, SeriesInstanceUID `0020,000E`,
SeriesDescription `0008,103E`) sind reale DICOM-PS3.6-Tags, keine
erfundenen Platzhalter.

**Von der Roadmap abgewichen: kein neuer Hint-„Engine-Typ", keine neue
Flag-Bauart.** Die Roadmap nennt `engine: find` als neuen Hint-Typ ohne
diesen zu spezifizieren. Da C-FIND bereits ein regulärer, seit P4
existierender Befehl ist (`findscu` über `exec`), war dafür kein neuer
API-Mechanismus nötig — die Erweiterung sitzt vollständig in
`_exec_findscu`. Der Flag-Typ `exact` (ein literaler String statt eines
DICOM-Tag-Werts) existierte bereits als dokumentierte Option in
`content-schema.md`, nur bisher ungenutzt.

**Node `c-find-mismatch` statt `c-find-basics` (Roadmap-Name).** Die
Roadmap nennt in Abschnitt III eine Tabelle mit `c-find-basics`, im
Node-Abschnitt IV dann aber `c-find-mismatch` mit demselben Szenario —
ein interner Widerspruch im gelieferten Dokument. Übernommen wurde der
im Detail beschriebene Name und das dort beschriebene Szenario
(Patient-ID-Tippfehler, Wildcard-Aufklärung).

**Nicht übernommen: der in der Roadmap genannte Statuscode "122 =
Instance Not Stored".** Das ist kein realer DICOM-Statuscode (C-STORE-
Ablehnungen tragen andere, in PS3.7 definierte Codes) und betrifft ohnehin
Feature 2 (Größenlimit), nicht dieses ADR — wird dort behandelt, sobald
umgesetzt, mit echtem, geprüftem Code statt der Roadmap-Angabe.

## Manuell verifiziert (gegen den echten Stack)

- Falsch abgetippte Patient ID (`MEYER,HANS`, ohne Leerzeichen) liefert
  `I: Number of Matches: 0`.
- Wildcard-Anfrage (`MEYER*`) liefert genau einen Treffer mit der echten
  Patient ID `MEYER, HANS` (mit Leerzeichen) sowie PatientName,
  StudyInstanceUID, StudyDescription, StudyDate — Wortlaut identisch mit
  dem, was im Write-up steht (direkt aus der Engine kopiert, nicht
  nachträglich angepasst).
- Flag `MEYER, HANS` korrekt, `MEYER,HANS` (Tippfehler-Variante) falsch —
  über die echte Session/API gegen den laufenden Stack geprüft, 15 Punkte
  vergeben.
- `content:validate`: keine neuen Verstöße.
- `pytest`/`ruff`/`mypy` für `services/engine`: alle grün (55 Tests,
  davon 8 neu für `app/find.py` plus ein Ende-zu-Ende-Test gegen den
  echten Node-Content).

## Nicht gebaut (bewusst, diese Phase)

Die restlichen sechs Engine-Features aus der Roadmap (Größenlimit,
Transfer-Syntax-Aushandlung, Multiframe-Generator, Worklist-Query,
Patient-Merge/Study-Split) folgen in eigenen, separat committeten
Schritten — siehe `docs/content-todo.md` für den laufenden Fortschritt.
