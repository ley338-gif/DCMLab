# 0052 — P10.43: Lektion 3.4 (Multiframe, Enhanced IODs) — echtes Testobjekt aus pydicom, kein Generator-Zusatz nötig

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Lektion 3.4 war die erste der drei in ADR 0048 markierten Lektionen mit
einer echten, noch ungeprüften Infrastrukturfrage: `content/datasets.yml`
kennt bisher nur klassische Single-Frame-CT-Objekte, für Multiframe/
Enhanced-IODs fehlt ein Testobjekt.

**Empirisch geprüft, mehrere Wege, bevor entschieden wurde:**

1. **pydicoms externe Testdaten** (`~/.pydicom/data/`, u. a. eine echte
   `eCT_Supplemental.dcm` mit SOP-Class `Enhanced CT Image Storage`)
   sind real vorhanden, aber laden beim ersten Zugriff per Netzwerk
   nach. Die Spielwiese verbindet die Toolbox mit einem
   `internal=True`-Docker-Netz (`services/sandbox/app/docker_ops.py`)
   — **kein Egress**. Diese Option scheidet für die echte Spielwiese
   damit aus, obwohl sie in einem Vortest mit Internetzugriff (auf
   diesem Rechner, nicht im Sandbox-Netz) real funktionierte.
2. **pydicoms mitgelieferte Paket-Testdaten**
   (`dist-packages/pydicom/data/test_files/`) sind Teil der pip-Installation
   selbst, brauchen keinerlei Netzwerk — verifiziert mit `docker run
   --network none`. Darunter mehrere echte, RLE-komprimierte
   2-Frame-Objekte (`SC_rgb_rle_2frame.dcm`, SOP-Class „Secondary Capture
   Image Storage", Testpatient „Lestrade^G" — pydicoms öffentlich
   bekannte, fiktive Sherlock-Holmes-Testdaten, keine echten
   Patientendaten).
3. Diese Datei ist zwar kein „Enhanced IOD" im engeren Sinn (keine
   Functional-Groups-Sequenzen), aber ein echtes, standardkonformes
   multiframe-Objekt — geeignet für die Hälfte der Lektion, die
   `NumberOfFrames` und die Instance-vs-Frame-Unterscheidung behandelt.
   Ein echtes Enhanced-CT/-MR-Objekt mit gefüllten Functional-Groups-
   Sequenzen ließ sich ohne Netzwerkzugriff nicht beschaffen; von Hand
   ein minimales, aber standardkonformes Enhanced-Objekt zu bauen
   (mehrere verschachtelte Pflichtsequenzen) wäre unverhältnismäßig
   aufwendig für den Ertrag einer einzelnen Lektion.

**Entscheidung:** Die Lektion nutzt das echte, netzwerkfrei verfügbare
multiframe-Testobjekt für den durchgehend real verifizierten Teil
(Instance vs. Frame, `NumberOfStudyRelatedInstances` bleibt 1 trotz 2
Frames) und behandelt Enhanced IODs als eigenständige, real durch
Standard-SOP-Class-UIDs und die Struktur-Tabelle belegte, aber nicht
live erzeugte Ergänzung — mit einer echten Gegenprobe: Das reale
Testobjekt hat tatsächlich keine `PerFrameFunctionalGroupsSequence`/
`SharedFunctionalGroupsSequence` (per `dcmdump` bestätigt, leere
Ausgabe), was den Unterschied zu Enhanced sauber demonstriert, ohne
ihn zu erfinden.

## Entscheidung — Fließtext mit vier echten Beispielen, ein neues Werkzeug registriert

**`content/lessons/3.4/de.md`**: vollständig neu geschrieben.
- Reales RLE-komprimiertes 2-Frame-Testobjekt aus dem pip-installierten
  `pydicom`-Paket kopiert und mit `dcmdrle` entpackt.
- Reales `storescu` und `findscu` zeigen: Orthanc zählt die Datei als
  **eine** Instance (`NumberOfStudyRelatedInstances 1`), obwohl real
  zwei Frames darin stecken.
- Eine echte, leere `dcmdump`-Abfrage der Functional-Groups-Sequenzen
  am selben Objekt als Gegenprobe zur Enhanced-IOD-Erklärung.

**`content/lessons/3.4/meta.yml`**: `tools` von `[]` auf `[dcmdump,
dcmdrle, storescu, findscu]` gesetzt (am Vier-Werkzeuge-Limit),
`glossary_terms: [enhanced-iod]`, `status: draft` → `fertig`.

**`content/tools/de.yml`**: `dcmdrle` neu registriert (RLE-Dekompression,
Gegenstück zu `dcmcjpeg`/`dcmdjpeg` aus Lektion 1.7).

**`content/glossary/de.yml`**: Begriff `enhanced-iod` neu ergänzt.

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Isolierter Vortest (eigenständiges Orthanc + Toolbox, eigene
  `dcmlab/*:test`-Tags): Datei-Kopie, `dcmdrle`-Entpackung, `storescu`,
  `findscu`-Instanzzählung und die leere Functional-Groups-Abfrage
  alle real reproduziert. Zusätzlich mit `docker run --network none`
  bestätigt, dass die Paket-Testdaten wirklich ohne Netzwerk verfügbar
  sind — im Gegensatz zu den extern nachladenden pydicom-Testdaten.
- **Vollständig wiederholt über den echten Orchestrator:** Session
  über die echte Produkt-Oberfläche gestartet (Lektion 2.1, „Spielwiese
  starten"), realer Sitzungscontainer per `docker ps` gefunden. Alle
  Beispiele erneut ausgeführt — identisches Verhalten, für die Lektion
  übernommen.
- `content:validate`: 0 Verstöße (33 Lektionen, 16 Nodes, 23
  Werkzeuge, 40 Glossarbegriffe).
- `datasets/build` (5 passed) und `services/sandbox` (14 passed, ruff
  clean, mypy 0 Fehler) — unverändert, von dieser Slice nicht betroffen.
- Lektion 3.4 im Browser gegen den echten Stack aufgerufen — rendert
  vollständig fehlerfrei, inklusive `{{term:enhanced-iod}}`-Tooltip.
- Alle Docker-Ressourcen dieser Slice entfernt — die geteilten
  `dcmlab/toolbox:latest`/`dcmlab/orthanc:latest`-Images blieben
  unangetastet.

## Nicht Teil dieser Slice

- Kein Node-Stub für Lektion 3.4 (`lab.node` bleibt `null`).
- Kein echtes Enhanced-CT/-MR-Objekt mit gefüllten Functional-Groups-
  Sequenzen — technisch ohne Netzwerkzugriff nicht ohne
  unverhältnismäßigen Aufwand zu beschaffen, siehe Kontext oben.
- Keine Änderung an `datasets/build/generate.py` — das echte
  Testobjekt kommt aus der bereits vorhandenen `pydicom`-Installation,
  kein neuer Generator-Code nötig.
