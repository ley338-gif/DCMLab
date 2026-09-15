# 0089 — Lektions-Editor: `sandbox`/`lab`/`objectives`

## Status

Angenommen, 15.09.2026. Loest die offene Frage "`sandbox`/`lab`/
`objectives` im Lektions-Editor" (`docs/offene-fragen.md`, ADR 0081,
W6.2).

## Kontext

ADR 0081 (W6.2) beschraenkte den Lektions-Editor bewusst auf Felder,
die als einzelne Zeile ersetzbar sind. `sandbox`/`lab` sind
verschachtelte YAML-Bloecke in `meta.yml`, `objectives` eine
mehrzeilige Liste im Frontmatter von `de.md` -- alle drei brauchten
einen Block-Ersatz statt eines Zeilenersatzes.

## Entscheidung

**`LessonMetaGenerator::regenerateSandbox()`/`regenerateLab()`**
wenden dasselbe chirurgische Block-Muster an, das `LessonQuizGenerator`
schon fuer den `quiz:`-Block etabliert hat (Regex `/^key:\r?\n(?:[ \t]
.*\r?\n|\r?\n)*/m`, jetzt als private `replaceBlock()`-Hilfsmethode
verallgemeinert). `regenerateFrontMatter()` bekommt einen dritten
Zweig fuer `objectives` nach demselben Muster, angewandt auf den
bereits isolierten Frontmatter-Textblock.

**Feldformen, aus dem echten Bestand abgeleitet** (nicht angenommen):

- `sandbox: {required: bool, dataset?: string, note?: string}` --
  `dataset`/`note` werden nur geschrieben, wenn ein Wert vorhanden ist
  (im Bestand fehlt `dataset` z. B. komplett bei `required: false`,
  siehe `content/lessons/5.3/meta.yml`).
- `lab: {node: string|null, optional: bool}` -- `node` steht im
  Bestand immer als Zeile da, auch als `node: null`.
- `objectives: string[]` im Frontmatter.

**`objectives_count` (meta.yml) wird nie direkt vom Entwurf
uebernommen, sondern von `LessonActivity::serialize()` aus der
tatsaechlichen Laenge von `objectives` abgeleitet**, sobald der
Entwurf `objectives` enthaelt. `ContentValidator::
checkLessonStructure()` verlangt, dass beide Werte uebereinstimmen --
ein eigenstaendig editierbares `objectives_count`-Feld haette einen
Mismatch strukturell moeglich gemacht, den diese Ableitung von
vornherein ausschliesst.

**Frontend:** `LessonEditor.vue` bekommt eine Lernziele-Textarea (eine
Zeile je Ziel) und eine neue Karte "Spielwiese und Lab" -- Checkbox
fuer `sandbox.required` (Datensatz-Auswahl nur sichtbar, wenn aktiv),
Dropdown fuer `lab.node` (aus `content->nodes()`) und Checkbox fuer
`lab.optional`. `LessonEditorController` liefert dafuer die Kataloge
`datasets`/`nodes` zusaetzlich zu den bestehenden `tools`/
`glossary_terms`.

## Konsequenzen

- Der Lektions-Typ ist jetzt vollstaendig ohne Kommandozeile pflegbar
  (Abnahmekriterium aus Abschnitt 5) -- alle `meta.yml`/Frontmatter-Felder
  ausser den bereits ueber den Quiz-Editor gepflegten sind ueber diesen
  Editor erreichbar.
- `sandbox.dataset`/`lab.node` werden ueber Dropdowns aus dem echten
  Bestand befuellt, keine Freitextfelder -- ein Tippfehler kann keinen
  nicht existierenden Datensatz/Node mehr referenzieren.

## Verifikation

- `LessonMetaGeneratorTest`: `regenerateSandbox()` laesst `dataset`
  weg, wenn kein Wert vorhanden ist, und uebernimmt `lab` unangetastet
  (und umgekehrt); `regenerateLab()` schreibt `node: null` als
  echtes YAML-Null, nicht als leeren String; `objectives`-Block in der
  Frontmatter wird ersetzt, andere Inhalte bleiben erhalten; Rundtrip
  gegen echten Bestand (`content/lessons/1.0`) bleibt gueltig und
  geparst identisch.
- `LessonActivityTest`: `serialize($draft)` mit `sandbox`/`lab`/
  `objectives` regeneriert jeweils nur den genannten Block, laesst die
  anderen unangetastet; `objectives_count` bleibt nach einem
  `objectives`-Entwurf immer mit der Listenlaenge synchron.
- `LessonEditorControllerTest`: der volle Kreislauf (pruefen → Entwurf
  → einreichen → freigeben) schreibt `sandbox`/`lab`/`objectives`/
  `objectives_count` korrekt nach `content/`, mit einem realistischen
  `datasets.yml`-Fixture fuer die `sandbox.dataset`-Validierung.
- `content:validate` gegen den echten Bestand bleibt frei von
  Verstoessen (42 Lektionen, 17 Nodes, 5 Pruefungen).
- Alle 379 Tests, `phpstan analyse` (Level 7), `pint --test`,
  `npm run check` und `npm run build` sind gruen.
