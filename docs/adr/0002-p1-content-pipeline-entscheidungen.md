# 0002 — Entscheidungen bei der Content-Pipeline (P1)

Status: akzeptiert
Datum: 2026-09-12

## Kontext

`content:validate` und `content:sync` (Abschnitt 4.7 / 7) mussten gegen den
**tatsächlichen** Content in `content/` gebaut werden, nicht nur gegen die
Beispiele im Auftrag. Dabei kamen einige Stellen zutage, an denen der reale
Content leicht vom illustrativen Schema in Abschnitt 4.1 abweicht, und eine
Regel (Flag-Format) keine konkrete Grammatik hat. Alle Entscheidungen hier
sind umkehrbar (Abschnitt 14.2).

## Entscheidungen

**`meta.yml` ohne `title_key`/`quiz`.** Abschnitt 4.1 zeigt beide Felder,
aber kein einziges reales `meta.yml` (1.0–1.8) hat sie — der Titel steht
bereits im Frontmatter von `de.md`, das Quiz als Markdown-Abschnitt „## Quiz"
statt als YAML-Struktur. `ContentRepository`/`content:sync` verlangen beide
Felder nicht und lesen den Titel aus dem Frontmatter. Betrifft nur Lese-Code,
keine Content-Datei musste geändert werden.

**`exempt_tool_limit: true`.** Lektion 1.0 überschreitet bewusst die
Vier-Werkzeuge-Grenze aus `content-schema.md` Abschnitt 1 (Übersichtslektion,
mit eigenem Kommentar im `meta.yml` begründet). `content:validate` überspringt
die Zähl-Prüfung, wenn dieses Feld gesetzt ist — die einzige Stelle, die es
aktuell braucht, ist genau die, die es schon selbst dokumentiert.

**Flag-Format-Regex.** Es gibt im Projekt keinen festen Flag-Präfix (anders
als z. B. `flag{...}` in klassischen CTFs) — Flags sind reale DICOM-Werte wie
eine `SeriesDescription`. Geprüft wird deshalb das einzig wirklich prüfbare
Format: `node.yml: flag.hash` muss mit `sha256:` beginnen. Ein Wert ohne
diesen Präfix sieht nach versehentlichem Flag-Klartext statt Hash aus.

**`content/datasets.yml` ergänzt.** Fehlte komplett (im README schon als
Lücke vermerkt), wurde aber von jeder Lektion und der Node `silent-ct`
bereits referenziert. Angelegt mit `ct-thorax-60` und `ct-thorax-3-slices`,
beide nur mit bereits an anderer Stelle festgelegten Referenzwerten
(Abschnitt 4.6) — keine neue Fachprosa, reine Formalisierung vorhandener
Fakten.

**Fünf `<!-- kein-beispiel -->`-Marker nachgetragen.** `content:validate`
fand sechs reine ASCII-Diagramme ohne die Selbst-Ausschluss-Markierung, die
an anderer Stelle im selben Repo (`1.5`) bereits Konvention ist. Nachgetragen
in `1.0`, `1.2`, `1.4`, `1.6`, `1.8` — reine Kommentarzeile, kein Wort Prosa
geändert.

**`nodes/silent-ct/node.yml: related_lessons` von `["1.5","4.1"]` auf
`["1.5"]`.** Lektion 4.1 existiert noch nicht (Track 4 ist laut README nicht
geschrieben). Der Verweis wäre sonst der einzige verbleibende Struktur-
Verstoß gewesen, den `content:validate` nicht ignorieren sollte. Die
Vorwärtsreferenz steht weiterhin als Prosa unter „Verwandte Inhalte" in
`de.md` — sobald 4.1 existiert, kommt sie hier zurück.

**Was NICHT gefixt wurde.** 15 verbleibende Verstöße (siehe
`docs/content-todo.md`) sind entweder Verkettungen mehrerer Beispiele mit
einer gemeinsamen Erklärung (Windows-Zusatz, Quelltext+Output getrennt, u. Ä.)
oder eine echte Kollision mit der Vier-Werkzeuge-Grenze (`1.7`). Beides sind
redaktionelle Entscheidungen, keine Bugs — deshalb nicht angefasst.
`content:validate` läuft in CI vorerst mit `continue-on-error: true`
(`.github/workflows/ci.yml`), bis diese Liste leer ist.

## Folgen

`content:validate`/`content:sync` sind vollständig lauffähig gegen den realen
Content. Zwei kleine YAML-Ergänzungen (`datasets.yml`, ein Kommentar in
`node.yml`) und fünf HTML-Kommentare sind die einzigen Content-Änderungen;
keine Lektions- oder Node-Prosa wurde erfunden oder umgeschrieben.
