# ADR 0066 — P10.60: Track-Abschlussprüfung (Fundamente)

## Status

Angenommen.

## Kontext

Jeder Track soll am Ende eine gewertete Abschlussprüfung bekommen, die den
Stoff aller Lektionen inklusive ihrer Zusammenhänge prüft und bei jeder
falschen Antwort auf den Abschnitt zurückverweist, in dem das Wissen steht.
Die drei Wissenskarten je Lektion (`quiz:` in `meta.yml`, PR #66/ADR 0065)
bleiben unverändert — sie sind die Sofortrückmeldung beim Lesen und
gleichzeitig der Rohstoff für den Prüfungspool.

## Bestandsaufnahme (vor der Implementierung, empirisch verifiziert)

- **Implementierte Quiz-Typen:** nur `single`, `multi`, `input`
  (`QuizContent`, `QuizController::isCorrect()`, jetzt `AnswerGrader`).
  Kein `truefalse` vorhanden.
- **Indexbasis: 0-basiert**, für `single` *und* `multi` — bestätigt durch
  `ContentValidate::checkQuizIndexAnswer()`s Bereichsprüfung (`>= 0`), die
  Frontend-Nutzung des rohen JS-Array-Index in `QuizSection.vue`, und
  empirisch an `content/lessons/1.1/meta.yml` (`answer: 2` selektiert
  faktisch die dritte, richtige Option "In derselben Datei wie die
  Pixeldaten").
- **`input`-Normalisierung:** `mb_strtolower()` + `trim()` beidseitig,
  keine Kollabierung innerer Leerzeichen.
- **Anker-Auflösung existierte nicht.** `grep -rn anchor apps/web/app`
  lieferte nichts; `docs/werkzeug-registry.md` beschreibt das Konzept nur
  in Prosa, keine Klasse validierte oder löste es auf.
  `MarkdownRenderer` erzeugte keine Heading-IDs.
- **Kein Skill-Vokabular als Datei.** Die fünf Kategorien
  (`netzwerk`, `datenmodell`, `bildgebung`, `integration`, `security`)
  waren nur als PHP-Konstante `ProfileService::SKILL_CATEGORIES` kodiert.
- **Kein Badge-Modell.** `achievements` existiert, aber sein
  `unique(node_id, type)` ist auf „wer war global zuerst" zugeschnitten
  (`first_blood`) — passt nicht für „jeder bestehende Nutzer bekommt ein
  eigenes Abzeichen für Track X".
- **`lesson_progress`** ist die Grundlage für die (weiche) Freischaltung.
- **Kein Attempt-Modell für mehrere Fragen in Folge.** `Review/Index.vue`
  zeigt alle fälligen Karten gleichzeitig, keine Ein-Frage-pro-Bildschirm-
  Sequenz mit Fortsetzung.
- Der im Auftrag vermutete Sonderfall „dieselbe C-ECHO-Aussage steht in
  1.5 und 1.6 zweimal als Karte" wurde geprüft: 1.5.q3 und 1.6 testen
  verwandte, aber nicht wortgleiche Aussagen (1.5: „was ein C-ECHO
  beweist", 1.6: eine Aussagenliste zu AE-Titeln, in der C-ECHO nur als
  eine von vier Optionen vorkommt). Es gab keinen wörtlich doppelten
  Kartentext zu entfernen; beide Lektionskarten sind unverändert in den
  Pool übernommen worden.

## Entscheidungen

### 1. `truefalse` wird ein echter vierter Typ

Nicht auf `single` mit zwei Optionen abgebildet — eine echte Erweiterung
von `AnswerGrader` (bisher `QuizController::isCorrect()`, jetzt in
`app/Content/AnswerGrader.php` extrahiert und von `QuizController` *und*
dem neuen `ExamAttemptService` genutzt), `ContentValidate` und dem
Frontend (`ExamQuestion`-Teil in `Exams/Show.vue`, Richtig/Falsch-Radio
statt Optionsliste). Auswirkung: `QuizController` selbst ist reines
Refactoring ohne Verhaltensänderung für die bestehenden drei Typen — die
Quiz-Tests aus PR #66 sind unverändert grün geblieben.

### 2. Neue, zweite Punktequelle „Track bestanden"

`ProfileService`s bisherige Regel „Node-Punkte sind die einzige Quelle"
(ADR 0009) wird bewusst erweitert, nicht stillschweigend gebrochen:
`totalPoints()` summiert künftig Node-Punkte **plus**
`TrackBadge`-Anzahl × 50 Punkte (Konstante `TRACK_PASS_POINTS`, passend
zur bestehenden Rang-Schwelle novice → operator). Ein Badge wird pro
Nutzer und Track höchstens einmal vergeben (`unique(user_id, track_id)`),
auch bei mehrfachem erneutem Bestehen. Richtig beantwortete
Prüfungsfragen speisen zusätzlich additiv das Skill-Radar (`+1` Punkt je
Tag, max. zwei Tags je Frage) — dieselbe rein additive Logik wie bei
Node-`skills`, bewusst ohne Straf-/Abzugsmechanik für falsche Antworten,
weil es die im gesamten Code nirgends sonst gibt.

### 3. Neue, geteilte Anker-Slug-Klasse statt eines CommonMark-Plugins

`app/Content/HeadingSlug.php` erzeugt Slugs aus Überschriftentext (Umlaute
transliteriert, Sonderzeichen entfernt, Kollisionen innerhalb eines
Dokuments über `-2`/`-3` aufgelöst) und wird von zwei Seiten benutzt:
`MarkdownRenderer::addHeadingAnchors()` setzt `id="<slug>"` auf jedes
gerenderte `##`/`###`-Heading (auch innerhalb eines Blockquotes — die
"Stolperfallen"-Kästen aller neun Lektionen sind als `> ### Stolperfallen`
geschrieben, das musste die Extraktion in `HeadingSlug::headingsIn()`
ausdrücklich mit abdecken), `ContentValidate::checkExamReview()` prüft
`review.anchor` gegen dieselbe Slug-Liste der Ziel-Lektion. Eine
zusätzliche CommonMark-Extension (`HeadingPermalinkExtension`) wäre
möglich gewesen, war aber unnötig für eine reine `id`-Injektion.

### 4. Neues `content/skills.yml` als kontrolliertes Vokabular

Deckungsgleich mit den fünf bestehenden `ProfileService::SKILL_CATEGORIES`
-Werten, damit beide Quellen nie auseinanderlaufen. Aktuell nur die
Validierungsgrundlage für `exam.yml`s `tags:`; ein Umbau der bestehenden
Node-`skills:`-Validierung auf dieselbe Datei ist bewusst außerhalb dieser
Slice geblieben (kein bestehendes Verhalten geändert).

## Architektur

- **Content bleibt Datei-Wahrheit:** `content/exams/<track>/{exam.yml,
  de.md}`, live gelesen über `ContentRepository::exams()` und
  `App\Content\ExamContent` — analog zu `QuizContent`, aber mit echten
  `###`-Überschriften statt der `**qN —**`-Inline-Karten (bewusst
  unterschiedliches Format, weil unterschiedliche Parser und weil eine
  Prüfungsfrage eine echte Anker-Ziel-Überschrift für Rückverweise
  braucht, eine Lektions-Quiz-Karte nicht).
- **Kein zweiter Kartenstapel:** Jede beantwortete Prüfungsfrage, die
  einer einzelnen Lektion zugeordnet ist, ruft
  `QuizSchedulerService::recordAnswer()` mit ihrer `f`-ID als
  `question_id` auf derselben `quiz_reviews`-Tabelle auf. Für
  `cross`-Fragen (keine einzelne Lektion) trägt pragmatisch die erste
  `review`-Lektion die Karte — eine bewusste, dokumentierte Vereinfachung
  statt einer zweiten Tabelle nur für diesen Randfall.
- **Neue Tabellen:** `exam_attempts` (Versuchszustand: gezogene
  Fragen-IDs, `current_index`, `answers`-JSON, Score, `passed`) und
  `track_badges` (Abzeichen je Nutzer/Track). Beide rein zustandshaltend,
  keine Inhalte.
- **Ziehung** (`ExamAttemptService::draw()`): mindestens zwei Fragen je
  Lektion, vier bis sechs `cross`-Fragen, danach zufällige Auffüllung bis
  `draw` erreicht ist — reine PHP-Logik über den in `exam.yml` geladenen
  Pool, keine DB-Abfrage.
- **Kein harter Zugangsschutz:** `TrackController::show()` liefert
  `exam.all_lessons_completed` nur als Hinweis-Flag, keine Sperre.

## Validierung (`content:validate`)

Neue `checkExamStructure()` prüft: `### f<nn> —`-Abschnitt ↔ `id`
beidseitig vollständig, keine doppelten IDs, genau eine
`**Erklärung:**`-Zeile je Frage, `type` ∈ `{single,multi,truefalse,input}`,
`answer` passt zu Typ und Indexbereich (0-basiert), Optionsanzahl stimmt,
`review` ist Pflicht und jeder `anchor` löst über `HeadingSlug` auf eine
echte Überschrift der Ziel-Lektion auf, `lesson` ist `cross` oder eine
Lektions-ID des Tracks, pro Lektion ≥ 4 Poolfragen, ≥ 4 `cross`-Fragen,
`draw` ≤ Poolgröße, Typmischung (35–40/20–25/20–25/10–15 %), ≥ 25 % der
Poolfragen mit `difficulty: 3`, `tags` ⊆ `skills.yml` (max. zwei),
`pass_percent` zwischen 50 und 100.

## Inhalt Track 1 „Fundamente"

40 Fragen (16 single, 9 multi, 9 truefalse, 6 input; 10 mit
`difficulty: 3`, davon alle 4 `cross`-Fragen), je 4 aus jeder der neun
Lektionen 1.0–1.8. Die 27 bestehenden Lektions-Karten sind inhaltlich
eingeflossen (keine erfundenen Fakten), wegen der Typmischungs-Vorgabe des
Prüfungspools teils mit anderem Fragetyp neu formuliert als im Original
(z. B. eine Freitext-Frage zur Byte-128-Signatur wurde zu einer
Single-Choice-Frage mit denselben vier Zeichen als richtiger Option) — der
geprüfte Fakt selbst ist in jedem Fall unverändert aus der jeweiligen
Lektion übernommen. Alle Distraktoren stammen aus den
Stolperfallen-Kästen oder Symptomtabellen der jeweiligen Lektion.

## Verifikation

- `content:validate` grün gegen den echten Content (41 Lektionen, 16
  Nodes, 23 Werkzeuge, 52 Glossarbegriffe, 1 Prüfung).
- PHPStan (Larastan): keine Fehler.
- Pint: 132 Dateien, keine Verstöße.
- Neue/angepasste automatisierte Tests: `AnswerGraderTest` (alle vier
  Typen), `HeadingSlugTest` (Umlaute, Blockquote-Headings, Kollisionen),
  `ExamControllerTest` (voller Ablauf: starten, Fortsetzung nach
  „Reload" über den gespeicherten `current_index`, Bestehen mit
  Badge/Punkte-Vergabe, Nichtbestehen ohne Badge, fremder Versuch
  liefert 403), sieben neue Regeln in `ContentValidateTest`.
- Restliche Suite: 154 von 166 Tests grün; die 12 verbleibenden
  Fehlschläge sind derselbe vorbestehende, dokumentierte Umgebungs-Gap wie
  in ADR 0065 (Vite-Manifest fehlt im minimalen Testcontainer ohne
  `npm run build`), unabhängig von dieser Änderung.
