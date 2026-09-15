# 0081 — W6.2: Lektions-Editor

## Status

Angenommen, 15.09.2026. Zweiter Editor aus Arbeitsphase W6
(`dcm-lab-lms-agent-prompt.md` Abschnitt 5), im Anschluss an ADR 0080.

## Kontext

ADR 0080 hat das Draft-Muster (`serialize($draft)`/`validate($draft)`,
chirurgische Regeneration statt vollem Re-Dump) für genau einen Ausschnitt
etabliert: den `quiz:`-Block. Der Rest einer Lektion -- Titel, Teaser,
Niveau, Dauer, Werkzeuge, Voraussetzungen, Glossarbegriffe, Fließtext --
blieb weiterhin nur über die Kommandozeile pflegbar. W6.2 schließt diese
Lücke für die Metadaten und den Fließtext, ohne den Quiz-Editor zu berühren
oder zu duplizieren.

## Entscheidung

**`LessonMetaGenerator` (neu) erweitert das chirurgische Patch-Muster von
`LessonQuizGenerator` auf einzelne YAML-Felder und Frontmatter-Felder statt
auf einen ganzen Block:**

- `regenerateMeta()` ersetzt in `meta.yml` nur die Zeilen der Felder
  `level`, `duration_minutes`, `tools`, `requires`, `glossary_terms` --
  jedes über einen eigenen `/^feld:[^\r\n]*/m`-Zeilenersatz (`\r?\n`-sicher,
  siehe ADR 0080). Alle anderen Felder (`id`, `track`, `status`,
  `tools_checked`, Kommentare) bleiben Byte für Byte erhalten.
- `regenerateFrontMatter()` tut dasselbe für `title`/`teaser` im
  YAML-Frontmatter von `de.md`.
- Werte werden einzeln über `Symfony\Component\Yaml\Yaml::dump()`
  serialisiert. Das quotet mehrwortige Skalare (`'Neuer Titel'`), obwohl
  handgeschriebener Content das unquotiert lässt -- kosmetisch, nicht
  falsch (rundet korrekt zurück), aber ein Grund, Testerwartungen gegen den
  *geparsten* Wert statt gegen den rohen String zu prüfen.

**`LessonActivity::serialize($draft)` verkettet jetzt beide Generatoren:**
zuerst `LessonMetaGenerator` für die generischen Felder, danach bedingt
`LessonQuizGenerator`, falls der Entwurf einen `quiz`-Schlüssel enthält.
Für den Fließtext gilt: der Entwurf liefert nur den Teil **vor** einem
bestehenden `## Quiz`-Abschnitt (`QuizContent::splitBody()` trennt das
bereits seit W5); der Quiz-Teil und alles danach (`**Als Nächstes:**`)
werden unverändert wieder angehängt. Der Lektions-Editor kann den
Quiz-Abschnitt also gar nicht sehen oder zerstören -- er ist strukturell
außerhalb seines Schreibbereichs.

**`ContentVersionController` (neu, generisch) wird aus
`QuizEditorController::submitForReview()`/`publish()` extrahiert.** Beide
Methoden kannten nichts Quiz-Spezifisches -- sie riefen nur
`ContentVersioningService`/`ActivityPolicy`/`ContentWriter` mit einer
`ContentVersion` auf. Die Routen `author/quiz-versions/{version}/submit`
und `.../publish` bleiben unter ihrem historischen Namen (Rückwärts-
kompatibilität für den bereits gemergten Quiz-Editor-Frontend-Code), zeigen
aber jetzt auf den generischen Controller und werden vom neuen
Lektions-Editor mitbenutzt. Jeder künftige Editor (Exam, Achievement,
W6.3/W6.4) braucht damit keine eigene Submit/Publish-Implementierung mehr.

**`LessonEditorController::currentFields()` liest den Ist-Zustand direkt
aus `ContentRepository`, nicht aus den DB-Modellen** -- exakt wie
`QuizEditorController::currentQuestions()` es in ADR 0080 vormacht. Die
erste Implementierung griff auf `ActivityContract::deserialize()` zurück,
das seinerseits `Lesson`-Modellattribute (`title`, `teaser`, `level`, ...)
liest; die stimmen nur, solange zuletzt `content:sync` gelaufen ist. Ein
Feature-Test mit einer eigenständig erzeugten `Lesson` (Faker-Titel) deckte
das sofort auf: Editor zeigte den Faker-Titel statt des Titels aus
`de.md`. Fix: Felder werden aus `$content->lessons()[$id]['meta']` und
`['frontmatter']` gebaut, nicht aus dem Modell.

## Konsequenzen

- `resources/js/pages/Author/LessonEditor.vue`: Metadaten-Formular
  (Titel, Teaser, Niveau, Dauer, Werkzeuge/Voraussetzungen/Glossarbegriffe
  als kommagetrennte Felder mit Katalog-Hinweis) plus ein Fließtext-Feld,
  das den Quiz-Abschnitt bewusst ausblendet, mit demselben
  Prüfen/Entwurf/Einreichen/Freigeben-Aktionsblock wie der Quiz-Editor.
- Route-Gruppe `de/author/lessons/{lesson}/edit` (auth+verified). Submit/
  Publish laufen über die bestehende `quiz-versions`-Route-Gruppe, jetzt
  gegen `ContentVersionController`.
- **Bewusst außerhalb des Schreibbereichs (v1):** `sandbox`/`lab`
  (verschachtelte YAML-Blöcke in `meta.yml`) und `objectives`
  (mehrzeilige Liste im Frontmatter). Beide brauchen eigene UI-Bausteine
  (Struktureditor bzw. Listeneditor) statt eines einzelnen
  Zeilenersatzes; als offene Frage in `docs/offene-fragen.md` vermerkt.

## Verifikation

- `LessonMetaGeneratorTest`: einzelne Felder werden ersetzt, alle anderen
  Felder/Kommentare bleiben erhalten, Rundtrip gegen echten Bestand
  (`content/lessons/1.1/meta.yml`, CRLF) bleibt gültig und geparst
  identisch.
- `LessonActivityTest`: `serialize($draft)` mit reinem Meta-Entwurf
  regeneriert nur die genannten Felder (Prüfung über `FrontMatter::parse()`
  des Ergebnisses, nicht über rohen String-Vergleich, wegen der
  `Yaml::dump()`-Quotierung); ein Entwurf mit `body` lässt einen
  bestehenden Quiz-Abschnitt unangetastet.
- `LessonEditorControllerTest`: eine Lernperson darf den Editor nicht
  öffnen (403); eine zugewiesene Autorenperson sieht den Ist-Zustand aus
  `content/`, nicht aus der DB; der Fließtext im Editor enthält nicht den
  Quiz-Abschnitt; der volle Kreislauf (prüfen → Entwurf → einreichen →
  freigeben) schreibt tatsächlich nach `content/` und der Quiz-Abschnitt
  bleibt danach unverändert erhalten.
- Alle 328 Tests, `phpstan analyse` (Level 7), `pint --test`,
  `npm run check` und `npm run build` sind grün.
