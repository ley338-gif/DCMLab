# 0050 — P10.41: Lektion 3.2 (Pixeldaten, Photometric Interpretation) — echtes RGB-Objekt per img2dcm, Type-1-Nuance bestätigt

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 3.2 lag seit P10.39 als Gerüst vor, mit zwei offenen Fragen:
ob sich MONOCHROME1/MONOCHROME2 in der Spielwiese sichtbar machen
lässt (kein Viewer im Werkzeugkasten), und ob `img2dcm` (bereits mit
`lesson: "3.2"` in `content/tools/de.yml` registriert) für diese
Lektion gebraucht wird.

**Vor dem Schreiben empirisch geprüft:**
- **Kein Viewer vorhanden — echt bestätigt, nicht nur vermutet.**
  `content/tools/de.yml` enthält keinen Bild-Viewer; ein sichtbarer
  MONOCHROME1/2-Effekt lässt sich in der aktuellen Spielwiese nicht
  zeigen. Die Lektion sagt das jetzt ehrlich, statt es zu verschweigen
  oder zu simulieren, und beschränkt die Demonstration auf die
  Tag-Ebene (`dcmodify` ändert den Tag real, `dcmdump` bestätigt es).
- **`img2dcm` liefert echtes, nicht-triviales Pixeldaten-Material.**
  Ein von Hand konstruiertes, minimales BMP (4×4, vier verschiedene
  Graustufen als RGB-Werte) wurde per `img2dcm -i BMP` in ein echtes
  `SecondaryCaptureImageStorage`-Objekt umgewandelt. Die resultierenden
  Pixeldaten-Bytes (`0x0a`, `0x50`, `0x96`, `0xdc`) entsprechen exakt
  den eingegebenen Werten (10, 80, 150, 220 dezimal) — ein echter,
  nachvollziehbarer Beleg dafür, dass die Rohbytes lesbar sind, sobald
  man `SamplesPerPixel`/`PhotometricInterpretation`/`BitsAllocated`/
  `PlanarConfiguration` kennt.
- **Nebenfund, der Lektion 3.1 nuanciert:** `PhotometricInterpretation`
  ist wie `StudyInstanceUID` ein Type-1-Attribut — trotzdem wird ein
  Objekt ohne dieses Tag von Orthanc anstandslos angenommen (`0x0000`).
  Orthancs strenge Prüfung aus 3.1 gilt also nicht pauschal für „jedes
  Type-1-Attribut", sondern gezielt für die Attribute, die Orthanc für
  seinen eigenen Index braucht (die Hierarchie-UIDs). Diese Einschränkung
  wird in 3.2 explizit nachgetragen, damit 3.1 nicht überinterpretiert
  wird.

## Entscheidung — Fließtext mit vier echten Beispielen

**`content/lessons/3.2/de.md`**: vollständig neu geschrieben.
- Realer `dcmdump` der Pixel-Attribute eines CT-Bilds (Grauwert,
  16-Bit) neben denen eines per `img2dcm` erzeugten RGB-Objekts
  (8-Bit, `PlanarConfiguration`) — direkter, echter Kontrast.
- Reale `dcmodify`-Änderung von `PhotometricInterpretation` auf
  `MONOCHROME1`, mit ehrlicher Einschränkung, dass die visuelle
  Auswirkung mangels Viewer nicht gezeigt werden kann.
- Der Type-1-Nebenfund als eigener Abschnitt, der Lektion 3.1 präzisiert
  statt ihr zu widersprechen.

**`content/lessons/3.2/meta.yml`**: `tools` von `[]` auf `[dcmdump,
img2dcm, dcmodify, storescu]` gesetzt (genau am Vier-Werkzeuge-Limit),
`glossary_terms: [photometric-interpretation]`, `status: draft` →
`fertig`.

**`content/glossary/de.yml`**: Begriff `photometric-interpretation`
neu ergänzt.

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Isolierter Vortest (eigenständiges Orthanc + Toolbox, eigene
  `dcmlab/*:test`-Tags): `img2dcm`-Konvertierung, MONOCHROME1-Änderung,
  Type-1-Nebenfund und ein zusätzlicher `storescu` des RGB-Objekts
  alle real erfolgreich reproduziert.
- **Vollständig wiederholt über den echten Orchestrator:** Session
  über die echte Produkt-Oberfläche gestartet (Lektion 2.1, „Spielwiese
  starten"), realer Sitzungscontainer per `docker ps` gefunden. Alle
  vier Beispiele erneut darin ausgeführt — identisches Verhalten,
  identische Pixelbytes, für die Lektion übernommen.
- `content:validate`: 0 Verstöße (33 Lektionen, 16 Nodes, 22
  Werkzeuge, 38 Glossarbegriffe).
- `datasets/build` (5 passed) und `services/sandbox` (14 passed, ruff
  clean, mypy 0 Fehler) — unverändert, von dieser Slice nicht betroffen.
- Lektion 3.2 im Browser gegen den echten Stack aufgerufen — rendert
  vollständig fehlerfrei, inklusive `{{term:photometric-interpretation}}`-
  Tooltip.
- Alle Docker-Ressourcen dieser Slice (isolierter Vortest mit eigenen
  `:test`-Tags, echter Sitzungscontainer, projekt-eigene
  `infra-p10-41-*`-Images) nach Abschluss vollständig entfernt — die
  geteilten `dcmlab/toolbox:latest`/`dcmlab/orthanc:latest`-Images
  blieben unangetastet.

## Nicht Teil dieser Slice

- Kein Node-Stub für Lektion 3.2 (`lab.node` bleibt `null`).
- Kein Bild-Viewer für die Spielwiese — bleibt eine bewusste,
  dokumentierte Lücke, kein Ziel dieser Slice, sie zu schließen.
