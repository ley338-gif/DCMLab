# 0051 — P10.42: Lektion 3.3 (Window/Rescale) — echte Rechnung, kein neuer Generator

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 3.3 lag seit P10.39 als Gerüst vor, mit einer offenen Frage:
Trägt `ct-thorax-60` reale `RescaleSlope`/`RescaleIntercept`/
`WindowCenter`/`WindowWidth`-Werte, oder braucht die Lektion einen
neuen Generator-Zusatz?

**Vor dem Schreiben empirisch geprüft:** `datasets/build/generate.py`
enthält keinerlei Code für diese vier Attribute (`grep` liefert 0
Treffer) — ein reales Testobjekt hat sie schlicht nicht.

**Entscheidung gegen eine Änderung am geteilten Generator:** Lektion
3.1 zeigt bereits einen vollständigen, ungefilterten `dcmdump` desselben
Testobjekts (`content/lessons/3.1/de.md`, bereits gemergt). Würde der
Generator jetzt Rescale-/Window-Tags ergänzen, stünde diese bereits
veröffentlichte, reale Ausgabe im Widerspruch zu dem, was ein frisch
generiertes Objekt heute tatsächlich zeigt — ein selbst erzeugter
Verstoß gegen „nur echte Daten", nur rückwirkend. Stattdessen bleibt
der Generator unverändert; die Lektion zeigt echt, dass die vier
Attribute fehlen, und ergänzt sie live per `dcmodify -i` — mit echten,
in der CT-Praxis belegten Konventionswerten (`RescaleIntercept -1024`/
`RescaleSlope 1`, das nahezu universelle CT-Rescale-Paar; `WindowCenter
40`/`WindowWidth 400`, ein verbreitetes Weichteilfenster) statt
erfundener Zahlen.

**Zweite offene Frage — visuell bestätigen ohne Viewer:** Wie schon in
3.2 (ADR 0050) gibt es keinen Bild-Viewer im Werkzeugkasten. Die
Lektion löst das über eine echte, nachvollziehbare Rechnung (Rohwert →
HU → Fenstergrenzen) statt eines Bildes — als `<!-- kein-beispiel -->`
markiert, weil es sich um eine Herleitung handelt, keinen echten
Werkzeugaufruf.

## Entscheidung — Fließtext mit zwei echten Beispielen plus einer markierten Rechnung

**`content/lessons/3.3/de.md`**: vollständig neu geschrieben.
- Ein echter `dcmdump`, der zeigt, dass die vier Attribute im
  Testobjekt fehlen (leere Ausgabe).
- Ein echter `dcmodify -i` mit den vier oben begründeten Werten, per
  `dcmdump` bestätigt.
- Eine als `<!-- kein-beispiel -->` markierte Rechnung: Rohwert `0`
  (der reale Wert der Testdaten) → `HU -1024` → weit unterhalb des
  Weichteilfensters `[-160, 240]` → würde unabhängig vom Viewer
  schwarz dargestellt.
- Ein kurzer Abschnitt zu Modality LUT/VOI LUT Sequence als nicht-lineare
  Alternativen zu den beiden linearen Formeln (Prosa, kein Beispiel
  nötig — beide bleiben in dieser Lektion unbenutzt).

**`content/lessons/3.3/meta.yml`**: `tools` von `[]` auf `[dcmdump,
dcmodify]` gesetzt, `glossary_terms: [hounsfield-unit]`, `status: draft`
→ `fertig`.

**`content/glossary/de.yml`**: Begriff `hounsfield-unit` neu ergänzt.

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Isolierter Vortest (eigenständiges Orthanc + Toolbox, eigene
  `dcmlab/*:test`-Tags): fehlende Attribute bestätigt, `dcmodify -i`
  real erfolgreich, `storescu` des ergänzten Objekts real erfolgreich
  (`0x0000`).
- **Vollständig wiederholt über den echten Orchestrator:** Session
  über die echte Produkt-Oberfläche gestartet (Lektion 2.1, „Spielwiese
  starten"), realer Sitzungscontainer per `docker ps` gefunden. Beide
  Beispiele erneut ausgeführt — identisches Verhalten, für die Lektion
  übernommen.
- `content:validate`: 0 Verstöße (33 Lektionen, 16 Nodes, 22
  Werkzeuge, 39 Glossarbegriffe).
- `datasets/build` (5 passed) und `services/sandbox` (14 passed, ruff
  clean, mypy 0 Fehler) — unverändert, von dieser Slice nicht betroffen.
- Lektion 3.3 im Browser gegen den echten Stack aufgerufen — rendert
  vollständig fehlerfrei, inklusive `{{term:hounsfield-unit}}`-Tooltip.
- Alle Docker-Ressourcen dieser Slice entfernt — die geteilten
  `dcmlab/toolbox:latest`/`dcmlab/orthanc:latest`-Images blieben
  unangetastet.

## Nicht Teil dieser Slice

- Kein Node-Stub für Lektion 3.3 (`lab.node` bleibt `null`).
- Keine Änderung an `datasets/build/generate.py` — bewusst, um bereits
  veröffentlichte, reale Ausgaben in Lektion 3.1 nicht rückwirkend
  ungültig zu machen (siehe Kontext oben).
- Keine echte Modality-LUT- oder VOI-LUT-Sequence gebaut oder gezeigt
  — bleibt Prosa, da für diese Lektion nicht gebraucht.
