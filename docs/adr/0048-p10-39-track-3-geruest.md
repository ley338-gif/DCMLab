# 0048 — P10.39: Track 3 ("Das Bild selbst") — sechs Gerüste angelegt

Status: akzeptiert
Datum: 2026-09-13

## Kontext

Mit Track 2 (ADR 0042/0044/0045) und den anschließenden Navigations-/
Plattformkorrekturen (ADR 0047, PR #45) abgeschlossen, ist Track 3
("Das Bild selbst", `docs/konzept-lernplattform.md` Abschnitt 5, ca.
5 Std.) der nächste noch nicht begonnene Track. Er behandelt das
DICOM-Objekt selbst — IOD/Module, Pixeldaten-Interpretation,
Fensterung, Multiframe/Enhanced, Structured Reports, Zeichensätze —
im Unterschied zu Track 1 (Format/Protokoll-Grundlagen), Track 2
(Netzwerkdienste) und Track 4 (Troubleshooting derselben Themen).

Wie bei Track 2 (P10.24) zuerst nur Gerüste angelegt: vollständige
`meta.yml` und `de.md` mit Titel, Teaser, drei Lernzielen und
geplanter Gliederung, aber kein Fließtext, keine Beispiele — Abschnitt
13 verbietet, Fachprosa oder Werkzeugausgaben vorab zu erfinden.

## Entscheidung

**Sechs neue Lektionen** `content/lessons/3.1` bis `3.6`, Titel und
Lab-Hinweise direkt aus der Curriculum-Tabelle übernommen, nicht
erfunden:

| # | Titel | requires |
|---|---|---|
| 3.1 | IOD und Module: woraus ein CT-Bild besteht | 1.3 |
| 3.2 | Pixeldaten, Photometric Interpretation, Bits Allocated | 3.1 |
| 3.3 | Window Center/Width, Rescale Slope/Intercept, LUTs | 3.2 |
| 3.4 | Multiframe, Enhanced IODs | 3.1 |
| 3.5 | Structured Reports, Presentation States, Key Objects | 1.3 |
| 3.6 | Specific Character Set — Umlaute und was schiefgeht | 1.3 |

Jedes Gerüst dokumentiert unter „Was zum Schreiben noch fehlt" seine
eigenen offenen Punkte. Drei davon sind — anders als bei Track 2, wo
sich am Ende jede „braucht neue Infrastruktur"-Annahme als falsch
herausstellte — **diesmal von vornherein als echte, noch ungeprüfte
Infrastrukturfragen markiert**, weil `content/datasets.yml` aktuell
ausschließlich klassische Single-Frame-CT-Objekte kennt:

- **3.4** (Multiframe/Enhanced) braucht wahrscheinlich ein echtes
  Enhanced-CT-Testobjekt — deckt sich mit dem bereits in
  `docs/content-todo.md` unter „Multiframe-Generator" vermerkten
  offenen Punkt (Roadmap-Feature, bisher `offen`).
  - noch nicht verifiziert.
- **3.5** (Structured Reports) braucht wahrscheinlich ein echtes
  SR-/GSPS-/KOS-Testobjekt — noch nicht verifiziert.
- **3.6** (Zeichensätze) braucht ein Testobjekt mit echtem
  Umlaut-Patientennamen — vermutlich ohne neuen Datensatz-Slug lösbar
  (ein kleines `pydicom`-Skript reicht ggf.), aber ebenfalls noch nicht
  verifiziert.

3.1–3.3 verwenden vorläufig `ct-thorax-60` als Platzhalter-Dataset —
plausibel ausreichend für IOD/Modul-Inspektion und
Pixel-/Fensterungs-Attribute eines gewöhnlichen CT-Bilds, aber noch
nicht empirisch bestätigt.

`content/tracks.yml: bild.status` bleibt `planned`, bis alle sechs
Lektionen tatsächlich geschrieben sind — derselbe Maßstab, der für
Track 2 erst nach Fertigstellung aller acht Lektionen zu `published`
führte (ADR 0042, PR #44).

## Manuell verifiziert (gegen den echten Stack, echten Orchestrator)

- Eigener isolierter Compose-Stack (Ports 55339/56339/58339):
  `content:sync` und `content:validate` im echten App-Container — 0
  Verstöße (33 Lektionen — 27 vorher + 6 neue Gerüste —, 16 Nodes, 22
  Werkzeuge, 36 Glossarbegriffe).
- Ein YAML-Frontmatter-Fehler in 3.6 (ungeschützte doppelte
  Anführungszeichen im Teaser-Text mit Umlauten) wurde dabei real vom
  Parser aufgedeckt und behoben (einfache Anführungszeichen außen).
- Lektion 3.6 im Browser gegen den echten Stack aufgerufen — rendert
  vollständig fehlerfrei, inklusive Umlauten und Sonderzeichen im
  Teaser.
- `datasets/build` (5 passed), `services/sandbox` (14 passed, ruff
  clean, mypy 0 Fehler) — unverändert, von dieser Slice nicht
  betroffen.
- Alle Docker-Ressourcen dieser Slice (projekt-eigene `infra-p10-39-*`
  Images) nach Abschluss vollständig entfernt — die geteilten
  `dcmlab/toolbox:latest`/`dcmlab/orthanc:latest`-Images blieben
  diesmal bewusst unangetastet (siehe Nebenfund unten).

## Nebenfund dieser Slice (kein Content-Bezug)

Zwischen P10.37 und dieser Slice wurde festgestellt, dass frühere
Ad-hoc-Vortests (P10.31/32/34) versehentlich die geteilten,
produktiv genutzten Images `dcmlab/toolbox:latest` und
`dcmlab/orthanc:latest` gebaut und am Ende der jeweiligen Slice wieder
gelöscht hatten — diese Tags sind aber nicht Wegwerf-Artefakte, sondern
die von `make sandbox-images` einmalig gebauten, dauerhaften Images,
von denen der echte Sandbox-Orchestrator jeder echten Nutzersitzung
abhängt. Das führte zu einem echten Ausfall der Spielwiese beim Nutzer
(„Die Spielwiese ist gerade nicht erreichbar"), behoben durch
Neubauen beider Images. Für alle künftigen Ad-hoc-Vortests gilt ab
sofort: eigene, klar unterscheidbare Tags (z. B. `dcmlab/toolbox:test`)
verwenden und nur diese wieder entfernen — die produktiven `:latest`
-Tags bleiben unangetastet.

## Nicht Teil dieser Slice

- Kein Fließtext, keine Beispiele, keine Nodes für 3.1–3.6 — folgt
  lektionsweise in den nächsten Slices, wie bei Track 2.
- Keine Entscheidung über neue Datensatz-Slugs/Generator-Subcommands
  für Multiframe/SR — wird erst bei den jeweiligen Lektionen (3.4, 3.5)
  empirisch geprüft, nicht vorab angenommen.
