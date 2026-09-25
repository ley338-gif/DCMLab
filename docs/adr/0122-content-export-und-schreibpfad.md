# 0122 — `content:export` und ein einziger Schreibpfad für Content

Status: umgesetzt in Phasen 0–4 (PRs #182–#186), Betreiberabnahme und offene Fragen 1–6 stehen aus
Datum: 2026-09-24

## Kontext

Seit CMS-5b/CMS-7d.3 (ADR 0101, 0102, 0108, 0118) ist die Datenbank die
Autoren-Wahrheit für Lektionen und Nodes: Studio legt `content_versions`
an, `ContentPublishingService::publish()` schreibt die freigegebene Fassung
direkt in `lessons`/`nodes`. `content/` sollte laut
`docs/studio-architecture-plan.md` Abschnitt 3 nur noch
„Import/Export/Seed/Demo“ sein, die Export-Hälfte fehlt aber
(`docs/offene-fragen.md`, „`content:export` fehlt noch“).

Gleichzeitig läuft `content:sync` weiter in beide Richtungen „mit“:
laut `docs/betrieb.md` („Update“) nach **jedem** `git pull`, und
zusätzlich als Nebenwirkung jeder Prüfungs-/Achievement-Freigabe
(`ContentWriter::write()` ruft `Artisan::call('content:sync')` auf).
Dieser ADR klärt in Phase 0, was `content:sync` dabei tatsächlich
überschreibt, und legt die Grundlage für die Phasen 1–4.

## Phase 0 — Befund

### 1. Was `content:sync` aus Dateien in die DB schreibt

Quelle: `app/Console/Commands/ContentSync.php` (Stand `main` @ 89fbc93).
„immer“ = bei jedem Lauf per `updateOrCreate()`, unabhängig vom DB-Stand;
„beim Anlegen“ = nur wenn die Zeile noch nicht existiert; „nie“ = die
Spalte wird von `content:sync` nicht angefasst.

| Entität | Feld(er) | `content:sync` schreibt |
|---|---|---|
| Lesson | `title`, `teaser`, `objectives`, `objectives_count`, `level`, `duration_minutes`, `tools`, `requires`, `glossary_terms`, `sandbox`, `related_node` | immer |
| Lesson | `status`, `legacy_authors`, `content_updated_at`, `tools_checked`, `source_hash` | immer |
| Lesson | `body` (Prosa **und** `## Quiz`-Abschnitt), `quiz` | immer |
| Lesson | `track_id`, `order` | beim Anlegen (Studio-Lessons-Umbau) |
| Lesson | `rich_content` | nie (nur Warnung bei geänderter Prosa, PR #162) |
| Activity `lesson` | `status`, `legacy_authors`, `title`, `teaser`, `source_hash` | immer |
| Activity `lesson` | `track_id`, `order` | beim Anlegen |
| Activity `sandbox`/`quiz` | `track_id`, `order`, `status`, `title`, `source_hash` | immer — **auch `track_id`/`order`**, anders als Lesson/Activity `lesson` |
| LessonElement | Slots Content/Sandbox/Lab/Quiz | nur fehlende Slots anlegen, nie ändern oder löschen |
| Node | `title`, `scenario_title`, `difficulty`, `points`, `category`, `interaction`, `estimated_minutes`, `skills`, `related_lessons`, `hints` | immer |
| Node | `status`, `themenfeld_id` | immer |
| Node | `body`, `content_updated_at`, `source_hash` | immer |
| Node | `rich_content` | nie (Warnung analog Lesson) |
| Activity `node` | `status`, `title`, `source_hash`, `track_id = null` | immer |
| Track | `themenfeld_id`, `order`, `title_key`, `level`, `hours`, `status` | immer |
| Track | `title`, `teaser` | nie |
| Themenfeld | `order`, `title_key`, `status` | immer |
| Exam | nur Activity `exam` (`track_id`, `status = published`, `title`, `source_hash`) | immer — der Inhalt selbst liegt nicht in der DB |
| Achievement | nur Activity `achievement/catalog` (`status`, `title`, `source_hash`) | immer — `achievement_definitions` füllt `AchievementSeeder` (`db:seed`), nicht `content:sync` |
| Glossar, Datasets, Worklists, Tools, Skills | — | nie — keine DB-Tabelle; Laufzeit liest `content/` direkt (`ContentRepository`, Engine/Scenario-Engine über `CONTENT_PATH`) |
| Lab | — | nie — kein `content/`-Pendant, DB-first (CMS-8a), Deploy über `labs:export`/`labs:import` |

### 2. Was Studio heute bearbeiten kann — die Schnittmenge

| Entität | Studio-Schreibpfad | von `content:sync` zurückgedreht |
|---|---|---|
| Lesson | `LessonContentPublisher` (versioniert): `title`, `teaser`, `level`, `duration_minutes`, `tools`, `requires`, `glossary_terms`, `objectives`(+`_count`), `sandbox`, `related_node`, `rich_content` | **alle außer `rich_content`** |
| Lesson/Quiz | `QuizContentPublisher` (versioniert, Payload-Schlüssel `quiz`): `quiz`, Quiz-Abschnitt in `body` | **beide** |
| Lesson | `StudioTrackController::moveLesson()/reorderLessons()` (direkt): `track_id`, `order` | Lesson/Activity `lesson`: nein. **Activity `sandbox`/`quiz`: ja** |
| LessonElement | `StudioLessonElementsController::reorder()/attachLab()` (direkt) | nein |
| Node | `NodeContentPublisher` (versioniert): alle Felder aus Zeile „Node“ oben + `rich_content`; setzt `status = published` | **alle außer `rich_content`, inklusive `status`** |
| Node | `StudioNodeController::archive()/restore()/updateThemenfeld()` (direkt): `status`, `themenfeld_id` | **ja** |
| Track | `StudioTrackController::update()/publish()/unpublish()/archive()/restore()` (direkt): `title`, `teaser`, `themenfeld_id`, `level`, `hours`, `order`, `status` | **`themenfeld_id`, `level`, `hours`, `order`, `status`** |
| Exam, Achievement | `ContentWriter` → schreibt `content/**`, dann `content:sync` | keine DB-Schnittmenge — die Datei *ist* hier die Wahrheit |

**Nicht** Studio-bearbeitbar: `Lesson.status`. Weder `LessonContentPublisher`
noch ein Controller setzt ihn; die Annahme im Auftrag („Lesson per
`ContentPublishingService` mit geändertem `status` veröffentlichen“) trifft
nicht zu. Der Status einer Lektion ist heute rein datei-geführt.

### 3. Beweis per Test

`tests/Feature/Content/ContentSyncRevertsStudioStateTest.php` (echter
`content/`-Bestand, nur lesend; SQLite `:memory:` laut `phpunit.xml`, ADR
0069). Ablauf je Fall: `content:sync` → Studio-Änderung über den echten
Pfad (`ContentVersioningService::createDraft()` → `submitForReview()` →
`ContentPublishingService::publish()`, bzw. dieselben Spalten wie der
Studio-Controller) → erneutes `content:sync` → Vergleich.

| Fall | Ergebnis nach dem zweiten `content:sync` |
|---|---|
| Lektion 1.5: `title`, `teaser`, `level`, `duration_minutes`, `tools`, `objectives` veröffentlicht | alle sechs (plus `objectives_count` und Activity-`title`) wieder auf Dateistand; `rich_content` bleibt; die `is_current`-Version behauptet weiter den Studio-Stand |
| Lektion 1.1: Quiz auf eine Frage reduziert veröffentlicht | `quiz` wieder drei Fragen, Studio-Fragetext aus `body` verschwunden |
| Node `gefiltert` (`node.yml`: `status: draft`) veröffentlicht | `status` fällt von `published` auf `draft` zurück — die Node verschwindet für Lernende; `title`/`points`/`estimated_minutes` ebenfalls zurück |
| Node `silent-ct` in Studio archiviert | wieder `published` |
| Track `fundamente`: `level`/`hours`/`order`/`status` geändert | alle vier zurück; `title` bleibt |

**Verdacht bestätigt.** Der Test ist als Charakterisierungstest angelegt
(grün auf `main`, beschreibt das heutige Verhalten); jede Stelle, die Phase
3 umdrehen muss, ist mit `PHASE 3: umdrehen` markiert.

**Bereits real eingetreten** (rein lesende Abfrage gegen die lokale
Entwicklungs-DB `dcmlab`, 2026-09-24): ContentVersion #16 (Lektion 3.3,
`published`, `is_current`) hat das dritte Lernziel auf „Ein technisch korrekt
dekodiertes Bild von einem durch ungeeignete VOI-/Fensterungswahl
unbrauchbar wirkenden Bild unterscheiden“ geändert (veröffentlicht
2026-09-19 22:06:48). `lessons.updated_at` für 3.3 ist 22:08:23; die Spalte
`objectives` enthält wieder das alte Lernziel aus `content/lessons/3.3/de.md`.
Ein `content:sync` rund 90 Sekunden nach dem Publish hat die Änderung
zurückgedreht. Alle übrigen `is_current`-Versionen (#14, #15, #17–#21, #24)
stimmen in den Metadaten mit der Datei überein, weil sie per
Reconciliation-Skript aus dem Dateistand entstanden sind (PR #162).

Zu den Annahmen im Auftrag:

- **„`make seed` überschreibt Studio-Änderungen“**: `make seed` führt
  `migrate:fresh --seed` aus und löscht damit die gesamte DB inklusive
  `content_versions`. Der eigentliche Mechanismus ist ein einzelnes
  `content:sync` gegen eine bestehende DB. Das passiert auf drei Wegen: im
  dokumentierten Produktions-Update (`docs/betrieb.md`), bei manuellen
  Läufen und als Nebenwirkung jeder Exam-/Achievement-Freigabe
  (`ContentWriter`).
- **„3.1/3.3 in Studio veröffentlicht, im Repo `status: draft`“**:
  widerlegt. Beide stehen in `meta.yml` **und** DB auf `published`. 3.7/3.8
  stehen in beiden auf `draft`. Ein Studio-Publish ändert `Lesson.status`
  ohnehin nicht (Punkt 2).

Nebenbefund, nur aus Code und `infra/docker-compose.yml` abgeleitet und
nicht live ausgelöst: `ContentWriter` schreibt nach `CONTENT_PATH`, und das
ist im `app`-Container `:ro` gemountet. Eine Exam-/Achievement-Freigabe über
Studio sollte im Container deshalb beim Schreiben scheitern, bevor sie das
`content:sync` erreicht. Gehört nicht zu diesem ADR, steht aber als offene
Frage unten.

### 4. Kanonische DB-Quelle je Entität

Entscheidend ist die **Live-Spalte**, nicht das Payload der
`is_current`-Version:

- Payloads sind nicht einheitlich. #24 (4.1, `published`, `is_current`)
  trägt ein Legacy-Payload mit `body`, nicht mit `rich_content`.
  `ContentVersioningService::publish()` normalisiert das gespeicherte
  Payload nicht nach, das tut nur der Apply-Pfad.
- Nur die Live-Spalten durchlaufen `LessonPayloadNormalizer`/
  `NodePayloadNormalizer` und die heutige Validierung.
- `draft`/`review`/`superseded` erreichen die Live-Spalten nie, weil die
  Publisher nur in `ContentPublishingService::publish()`/`restoreVersion()`
  laufen. „Nur veröffentlichter Stand“ ist damit schon strukturell
  garantiert. #25 (2.2, `review`) kann über die Live-Spalten nicht in einen
  Export gelangen.

| Entität | Kanonische Quelle für den Export |
|---|---|
| Lesson — Metadaten | `lessons.title/teaser/level/duration_minutes/tools/requires/glossary_terms/objectives/sandbox/related_node/status/track_id/order` + Track-Slug über `track_id` |
| Lesson — Prosa | `lessons.rich_content`, falls gesetzt; sonst `lessons.body` (Legacy, nicht migriert) |
| Lesson — Quiz | `lessons.quiz` (id/type/answer) + `## Quiz`-Abschnitt aus `lessons.body` (`QuizContent::splitBody()`), ADR 0118 |
| Node — Metadaten | `nodes.*` wie Tabelle oben + Themenfeld-Slug über `themenfeld_id` |
| Node — Prosa | `nodes.rich_content` (`node_content`-Umschlag: `briefing`/`hints`/`write_up`), sonst `nodes.body` |
| Node — `environment`/`flag` | **nicht aus der DB** — nie Teil des Payloads (ADR 0107), bleibt datei-geführt und muss beim Export unverändert aus der bestehenden `node.yml` übernommen werden |
| Track | `tracks.themenfeld_id/order/level/hours/status`; `title`/`teaser` haben kein Feld in `tracks.yml` (dort nur `title_key`) → offene Frage |
| Exam, Achievement, Glossar, Datasets, Worklists, Tools, Skills, Themenfelder | `content/` selbst ist die Quelle — ein Export wäre die Identität |
| `content_versions` | nur Filter/Provenienz, keine Inhaltsquelle |

Determinismus-Fallen, die der Realdaten-Vergleich schon gezeigt hat:

- **Postgres-`jsonb` sortiert Objektschlüssel um.** `json_encode($payload)`
  ≠ `json_encode($spalte)`, obwohl der Inhalt gleich ist; kanonisch
  verglichen sind alle `rich_content`-Spalten identisch zu ihren Versionen,
  außer 4.1 (Legacy-Payload, siehe oben). Die Schlüsselreihenfolge beim
  Export muss deshalb aus dem Schema kommen, nie aus der DB.
- **`sandbox.note: null`**: Studio-Payloads tragen `note: null`, die
  Dateien lassen den Schlüssel weg. Der Export braucht eine feste Regel.
- **Default-Felder**: `halbe-sache/node.yml` hat kein `interaction:`, die
  DB `terminal` (Default aus `content:sync`). Der Export darf Defaults
  nicht ungefragt ausschreiben, sonst driftet jede Datei beim ersten Lauf.
- `updated:`/`tools_checked:`/`authors:` sind menschliche Metadaten ohne
  Studio-Pendant. Der Export übernimmt sie unverändert aus der bestehenden
  Datei und erzeugt keine Zeitstempel.

### 5. Wiederverwendbare Serialisierer

| Klasse | Leistet | Grenze für den Export |
|---|---|---|
| `LessonActivity::serialize($draft)` | kombiniert die Generatoren unten zu `meta.yml` + `de.md`, leitet `objectives_count` ab | regeneriert Prosa bewusst **nicht** (seit CMS-7d.3), braucht eine bestehende Datei |
| `NodeActivity::serialize($draft)` | dasselbe für `node.yml` + `de.md`, lässt `environment`/`flag` unangetastet | dito |
| `LessonMetaGenerator`, `NodeMetaGenerator` | chirurgischer Ersatz einzelner Felder/Blöcke, Kommentare bleiben | nur Ersatz in vorhandenem Text — kein Skelett für eine neue Datei |
| `LessonQuizGenerator` | `quiz:`-Block + `## Quiz`-Abschnitt | — |
| `ExamMetaGenerator`, `ExamQuestionGenerator`, `AchievementCatalogGenerator` | Exam/Achievement-Dateien | für den Export nicht nötig (Datei ist Wahrheit) |
| `GeneratedFileMarker` | Kopfkommentar „nicht von Hand bearbeiten“ | offene Frage, ob der Export ihn setzt (einmaliger Diff über alle Dateien) |
| `ContentWriter::writeAtomically()` | Temp-Datei + Move | Vorbild; `ContentWriter` selbst nicht wiederverwendbar (ruft `content:sync`) |
| `LabDeploymentExporter` | Vorbild für Determinismus: feste Feldliste, keine IDs/Zeitstempel, `encode()` mit festen Flags und `\n` | — |

Damit reduziert sich Phase 2 im Kern auf: „Live-Spalten → `$draft` →
`*Activity::serialize($draft)` → Prosa aus Phase 1 einsetzen“.

### 6. Lücke `rich_content → Markdown`

Es gibt nur `MarkdownToRichContentConverter` (Markdown → v1), keinen
Rückweg. Zusätzlich zur fehlenden Klasse:

- **`callout`, `dicom_tag_table` und `code_block{variant: dicom_dump}`
  haben keine Markdown-Syntax.** Der Konverter erkennt sie bewusst nicht
  (ADR 0114: „reine Autoren-Konstrukte“). Ein Round-Trip
  `convert(serialize(x)) == x` ist für diese Blöcke nur möglich, wenn der
  Konverter eine neue Syntax lernt. Das berührt den Legacy-Normalisierungspfad
  (`LessonPayloadNormalizer`/`rich-content:migrate`). Stand Echt-DB:
  **0 Vorkommen** aller drei (Zählung über alle 42 Lesson- und 17
  Node-`rich_content`-Dokumente).
- `code_block{variant: diagram}` entsteht aus `<!-- kein-beispiel -->` +
  Fence, `console`/`terminal` aus einer Heuristik über `$ `/`PS> `-Zeilen.
  Ein Serialisierer muss das so ausgeben, dass die Heuristik denselben
  Variant liefert. Beispiel: ein `terminal`-Block, dessen Text zufällig mit
  `$ ` beginnt, würde als `console` zurückgelesen. Das ist ein
  dokumentierungspflichtiger Round-Trip-Fall.
- `hard_break` entsteht heute aus jedem `Newline`-Knoten, also auch aus
  weichen Umbrüchen. Textgleichheit auf Markdown-Ebene ist deshalb nicht
  erreichbar, Rich-Content-Gleichheit schon.
- Nodes brauchen zusätzlich den Rückweg `node_content` →
  `NodeSections`-Format (Briefing / `### h<n>`-Hints / Write-up).

## Empfehlung für Phase 3 (Sync-Schutzregel)

Zwei Regeln, weil Studio auf zwei verschiedenen Wegen schreibt:

**Regel A — versionsgebunden** (Lesson-Felder, Quiz, Node-Felder).
Existiert für die Activity mindestens eine `content_versions`-Zeile mit
`status = published` (Restores eingeschlossen), überspringt `content:sync`
genau die Felder, die der zugehörige Publisher schreibt. Die Granularität
folgt dem vorhandenen Diskriminator in `ActivityContentApplier`:

- veröffentlichte Lesson-Feld-Version → `LessonContentPublisher`-Felder
  überspringen
- veröffentlichte `quiz`-Version → `quiz` + Quiz-Abschnitt von `body`
  überspringen
- veröffentlichte Node-Version → `NodeContentPublisher`-Felder
  überspringen

Weicht die Datei vom DB-Stand ab, gibt es je Ressource eine `warn()`-Zeile
mit den Feldnamen, wie heute bei der Prosa. Nicht-Studio-Felder
(`legacy_authors`, `content_updated_at`, `tools_checked`, `Lesson.status`,
Legacy-`body` einer nie veröffentlichten Lektion) synchronisieren weiter.
Damit bleiben die heute noch datei-geführten Lektionen (Tracks 7/8, 3.7,
3.8 …) unverändert bearbeitbar, bis sie ihre erste Studio-Version bekommen.

**Regel B — nur beim Anlegen** (direkte Studio-Endpunkte ohne Version).
`Track.themenfeld_id/order/level/hours/status` und
`Node.status/themenfeld_id` werden nur noch beim Anlegen der Zeile aus der
Datei gesetzt. Das ist dasselbe Muster, das `syncLessons()` für
`track_id`/`order` schon hat, und dieselbe Begründung. Mitkorrigieren:
Activity `sandbox`/`quiz` bekommen `track_id`/`order` ebenfalls nur noch
beim Anlegen (heute inkonsistent zu Activity `lesson`).

**`--force-from-files`** hebt beide Regeln auf, fragt interaktiv nach und
wird in `docs/betrieb.md` dokumentiert.

Verworfen: **„`content:sync` legt nur noch an, aktualisiert nie“**. Das ist
einfacher, würde aber die heute noch datei-geführten Lektionen und Nodes
sofort einfrieren, obwohl für sie noch kein Studio-Stand existiert, der zu
schützen wäre.

## Offene Fragen an den Betreiber (vor Phase 1)

1. **Regel B für `Node.status`**: Eine neue Node aus einem Content-PR
   (`status: draft`) ließe sich danach nur noch in Studio auf `published`
   stellen, nicht mehr per `node.yml`. Ist das so gewollt?
2. **Lektion 3.3**: Das in #16 veröffentlichte Lernziel ist live verloren
   und nur noch im Versions-Payload vorhanden. Ein Export aus den
   Live-Spalten würde den *alten* Stand bestätigen. Soll #16 vor dem
   ersten echten Export per `restoreVersion()`/Neu-Publish wieder live
   gesetzt werden? Das ist eine Betreiberhandlung; dieser Auftrag verändert
   keine ContentVersion.
3. **`callout`/`dicom_tag_table`/`dicom_dump`**: Markdown-Syntax jetzt in
   Phase 1 definieren und im Konverter ergänzen, oder im Serialisierer
   vorerst eine eindeutige Exception werfen (heute 0 Vorkommen)?
4. **Track `title`/`teaser`** haben kein Feld in `tracks.yml`: ergänzen,
   oder Tracks aus `--only` herausnehmen?
5. **`--only=exams,achievements,glossary`** wäre die Identität, weil die
   Datei dort die Wahrheit ist. Streichen oder als dokumentiertes No-op
   behalten?
6. **`GeneratedFileMarker`** im Export setzen (klarer Hinweis, einmaliger
   Diff in jeder Datei) oder weglassen?
7. **`ContentWriter` gegen `:ro`-Mount** (Nebenbefund oben): als eigenes
   Thema erfassen?

## Nachtrag Phase 1 — `RichContentToMarkdownSerializer`

`app/Content/RichContent/RichContentToMarkdownSerializer.php`:

- `serialize(doc)` gibt Markdown zurück.
- `serializeNodeContent(envelope, hintOrder)` gibt den Node-Body zurück:
  `## Briefing` / `## Hints` mit `### <id>` / `## Write-up`, ohne
  `---`-Trenner wie bei 32 von 34 Nodes im Bestand. Die Hint-Reihenfolge
  kommt aus den `hints`-Metadaten, weil `jsonb` die Schlüssel umsortiert.

**Round-Trip** `convert(serialize(convert(md))) == convert(md)`, verglichen
auf Rich-Content-Ebene mit schlüsselreihenfolge-unabhängigem Vergleich:

| Quelle | Ergebnis |
|---|---|
| alle 59 Lektionen und 34 Nodes aus `content/` (datengetriebener Test) | 93/93 gleich |
| alle 59 `rich_content`-Spalten der lokalen DB `dcmlab` (42 Lektionen, 17 Nodes; rein lesend) | 58/59 gleich, 1 Abbruch mit Fundstelle (siehe unten) |

**Nie still verworfen.** Nicht darstellbare Knoten werfen
`UnrepresentableRichContentException`:

- unbekannte Block-, Inline- und Mark-Typen
- `callout`, `dicom_tag_table`, `dicom_dump`: vorläufig, bis zur
  Entscheidung über die offene Frage 3
- `console` ohne Prompt-Zeile, `code` mit Sprache `mermaid`
- Tabellen ohne genau eine Kopfzeile, Umbrüche in Zellen oder Überschriften
- wörtlicher Text `{{term:x}}`
- eine `## `-/`### `-Zeile, die Node-Abschnitte zerschneiden würde

Als letzte Sicherung liest `serialize()` jedes Ergebnis mit dem Konverter
zurück und wirft bei jeder Abweichung. Einzige bewusste Auslassung: leere
Absätze, weil sie keinen Inhalt tragen.

**Befund aus der Echt-DB, Lektion 2.1** (`doc.content[5]`): Studio hat
fett „**Was du daran abliest:**“ ohne Leerzeichen direkt vor „Fünf“
gespeichert. Nach den Flanking-Regeln von CommonMark ist `**…:**Fünf` kein
Fettdruck, das lässt sich in Markdown nicht ausdrücken. Vermutlich fehlt in
Studio schlicht ein Leerzeichen. Die Korrektur ist eine Inhaltsänderung
über Studio, keine Aufgabe dieses Auftrags. Bis dahin meldet der Export 2.1
als Fehler.

**Textgleichheit auf Markdown-Ebene**: 13/93. Die Abweichungen stammen alle
aus Informationen, die der Konverter nicht behält:

- Sprachangabe eines `<!-- kein-beispiel -->`-Blocks (` ```text `,
  ` ```mermaid ` werden zu ` ``` `)
- Startnummer geordneter Listen (`2.` wird zu `1.`)
- Zeilenumbrüche innerhalb von Code-Spans (werden zu Leerzeichen)
- Fettdruck um einen Glossarverweis (`**{{term:x}}**`, der Konverter
  verwirft die Marks am `glossary_term`)
- weiche statt harter Zeilenumbrüche sowie lockere statt enger Listen
- doppelte Leerzeilen

Alle sind semantisch neutral oder waren schon vorher nach dem Import
verloren.

`rich-content:audit`: weiterhin 0 blockierende Funde.

## Nachtrag Phase 2 — `php artisan content:export`

`content:export {--only=lessons,nodes,tracks} {--id=} {--check} {--path=}`
(`ContentExporter` + `ContentExportResult`, Befehl `ContentExport`).

- **Quelle** sind die Live-Spalten, nie `content_versions` (Punkt 4).
  `draft`/`review` können so strukturell nicht exportiert werden; ein
  Test legt beide an und prüft, dass `--check` sauber bleibt.
- **Semantischer Diff.** Jedes Feld wird mit der Datei verglichen. Nicht
  als Abweichung zählen:
  - Default-Werte und fehlende Schlüssel
  - `sandbox.note: null`, `jsonb`-Schlüsselreihenfolge, YAML-Schreibweise
    (`"1.0"`/`1.0`)
  - Prosa, die bei rich-content-Ressourcen über den Konverter inhaltsgleich
    zurückliest (`RichContentToMarkdownSerializer::equivalent()`)

  Nur abweichende Felder werden über die vorhandenen Generatoren ersetzt.
  Kommentare und alle übrigen Zeilen bleiben stehen. Folge: Ein zweiter Lauf
  findet nichts mehr und ändert kein Byte (Test). LF-Zeilenenden;
  `core.autocrlf` im Checkout wird beim Vergleich ignoriert.
- **Lektions-Body.** Stimmt nur der Quiz-Abschnitt nicht, wird nur er
  ersetzt (aus `lessons.body`, ADR 0118). Die Prosa bleibt zeichengleich.
  Weicht die Prosa ab, wird `rich_content` serialisiert. Der Fußtext nach
  dem Quiz („Als Nächstes“) kommt wieder hinter das Quiz, solange die
  letzten Blöcke von `rich_content` noch mit ihm übereinstimmen.
- **Neu an den Generatoren**, als eigene Methoden, sodass
  `LessonActivity::serialize()` unverändert bleibt:
  - `LessonMetaGenerator::regenerateIndex()` für `track`/`order`/`status`
  - `NodeMetaGenerator::regenerateIndex()` für `status`/`themenfeld`
  - `TrackCatalogGenerator` für feldweisen Ersatz in `tracks.yml`, nach dem
    Blockmuster von `AchievementCatalogGenerator`
  - `ContentRepository::tracksRaw()`
- **Vorläufig und zurückhaltend**, bis die offenen Fragen beantwortet sind:
  - `--only` kennt nur `lessons`, `nodes`, `tracks` (Fragen 4, 5). Das Quiz
    gehört zu `lessons`.
  - Track-`title`/`teaser` werden nicht exportiert.
  - Kein `GeneratedFileMarker` (Frage 6).
  - Ressourcen ohne Datei-Vorlage (nur in Studio angelegt) werden als Fehler
    gemeldet, nicht neu erzeugt.
- **Schreibziel**: `--path`, Default `config('content.path')`. Im
  `app`-Container ist `content/` `:ro`, und auf dem Host fehlt der
  pgsql-Treiber. Deshalb läuft der schreibende Lauf als Einmal-Container
  mit rw-Mount (README, „Content exportieren“). Der `--check`-Lauf über
  diesen Weg ist verifiziert; `content/` blieb unverändert.

### `content:export --check` gegen die lokale DB `dcmlab` (rein lesend, 2026-09-24)

Exit-Code 1: 7 Dateien weichen ab, 1 Ressource ist nicht exportierbar.
Nodes und Tracks sind ohne Abweichung.

| Lektion | Feld | Studio-Version | Befund |
|---|---|---|---|
| 1.7 | Prosa (`rich_content`) | keine | 1 Block: Die Datei beschreibt das Lab „Halbe Sache“ im neuen Szenario (PR #158, 2026-09-19), `rich_content` noch im alten. Die **Datei ist neuer**. |
| 2.3 | Prosa | keine | Die Datei hat einen `## Lab`-Abschnitt (PR #158), der in `rich_content` fehlt. Die **Datei ist neuer**. |
| 4.5 | Prosa | keine | wie 2.3 (PR #158), die **Datei ist neuer** |
| 2.2 | Prosa | #14 | 2 Blöcke, u. a. in einem Konsolenbeispiel der DB `daten//` statt `daten/ct-thorax-60/`. Das wirkt wie ein Editorverlust in Studio. |
| 3.1 | Prosa | #15 | 8 Blöcke ab Block 13; die DB hat einen Block mehr als die Datei |
| 3.3 | Prosa | #16 | ab Block 1 verschoben; die DB hat einen Block mehr. Das in #16 geänderte **Lernziel** ist live bereits verloren (Phase 0) und taucht deshalb hier **nicht** auf. |
| 3.4 | Prosa | #21 | Die DB hat einen `## Lab`-Abschnitt (#21, 2026-09-19), der in der Datei fehlt (letzte Dateiänderung 2026-09-13). Die **DB ist neuer**. |
| 2.1 | — | keine | nicht exportierbar: Fettdruck ohne Leerzeichen vor „Fünf“ (Nachtrag Phase 1) |

**Folgerung**: Ein schreibender Export gegen diese DB würde bei 1.7, 2.3
und 4.5 inhaltlich *neuere* Repo-Stände durch ältere DB-Stände ersetzen.
Die rich-content-Umstellung hat diese späteren Datei-Änderungen für
Lernende unsichtbar gemacht. „DB ist die Wahrheit“ heißt hier, dass die
Lernenden heute den älteren Stand sehen. Welcher Stand gewinnt, ist je
Lektion eine Inhaltsentscheidung. Deshalb bleibt der schreibende Export
gegen die echte DB ein eigener, letzter Schritt nach Freigabe (harte Regel
des Auftrags). Für 1.7, 2.3 und 4.5 wäre der richtige Weg vermutlich, den
Dateistand in Studio zu veröffentlichen (Reconciliation wie PR #162) und
erst danach zu exportieren.

## Nachtrag Phase 3 — `content:sync` dreht den Studio-Stand nicht mehr zurück

Umgesetzt ist die Empfehlung aus Phase 0. Die offene Frage 1 ist dabei
zurückhaltend beantwortet: `Node.status` bleibt ohne veröffentlichte
Version datei-geführt.

- **Regel A (versionsgebunden).** `ContentSync` lädt einmal je Lauf alle
  veröffentlichten Versionen (Restores eingeschlossen) und ordnet sie nach
  Payload-Art zu, mit demselben Diskriminator wie `ActivityContentApplier`:
  - Lektionsfeld-Version: Die `LessonContentPublisher`-Felder bleiben
    unangetastet, ebenso Activity-`title`/`teaser`.
  - Quiz-Version: `quiz` und `body` bleiben.
  - Node-Version: alle `NodeContentPublisher`-Felder einschließlich
    `status`, dazu Activity-`title`/`status`.
- **Regel B (nur beim Anlegen).**
  - Track `themenfeld_id`/`order`/`level`/`hours`/`status`
  - Node `themenfeld_id`
  - Activity `sandbox`/`quiz` `track_id`/`order`: bisher inkonsistent zur
    Activity `lesson` und deshalb mitkorrigiert
- **Warnung nur bei echter Abweichung.** Grundlage ist
  `ContentFieldComparison`, derselbe Vergleich wie beim Export (u. a. zählt
  `sandbox.note: null` nicht). Die Warnung nennt Ressource, Versionsnummern
  und Felder.
- **`--force-from-files`** hebt beide Regeln auf. Der Befehl fragt nach und
  bricht mit `--no-interaction` ab. Dokumentiert ist er in
  `docs/betrieb.md`.
- **Unverändert:**
  - Nicht-Studio-Felder (`status` der Lektion, `authors`, `updated`,
    `tools_checked`, `source_hash`) synchronisieren weiter.
  - Eine DB ohne `content_versions` synchronisiert wie bisher.
  - `ContentWriter` (Exam-/Achievement-Freigabe) ruft weiterhin
    `content:sync` auf, jetzt mit Schutz.

**Tests.** Der Phase-0-Test ist umgedreht und umbenannt
(`ContentSyncKeepsStudioStateTest`, 12 Fälle):

- Lektions-Metadaten, Quiz, Node inklusive `status`, Track-Einstellungen und
  Node-Themenfeld bleiben erhalten.
- Sandbox-/Quiz-Activities behalten eine Verschiebung.
- Nicht-Studio-Felder synchronisieren weiter.
- Keine Warnung bei formal verschiedenem, inhaltlich gleichem Stand.
- Eine DB ohne `content_versions` synchronisiert Dateiänderungen wie
  bisher.
- `--force-from-files` überschreibt nach Bestätigung und ändert nichts bei
  „nein“ oder ohne Interaktion.
- Archivierte Node ohne Version: bleibt bewusst datei-geführt (offene
  Frage 1).

**Nicht gegen die echte DB ausgeführt.** `content:sync` schreibt, und
schreibende Läufe gegen `dcmlab` schließt der Auftrag aus. Nach dem Merge
würde der nächste Sync dort für 2.2, 3.1, 3.3, 3.4 (Lektionsfelder) und
`halbe-sache` (Node) greifen. Warnungen wären nur bei tatsächlicher
Abweichung zu erwarten; laut Phase-2-`--check` gibt es in diesen Feldern
keine.

## Nachtrag Phase 4 — Workflow und Doku

- **Makefile**: `make content-check` (`content:export --check` im
  laufenden `app`-Container) und `make content-export` (Einmal-Container mit
  rw-Mount von `content/`). Den zugrunde liegenden Aufruf hat Phase 2 mit
  `--check` verifiziert; `make` selbst fehlt auf dem Windows-Host des
  Betreibers, dort gilt der Rohaufruf aus dem README.
- **Workflow** (`docs/betrieb.md`, „Content-Änderungen ins Repo bringen“;
  ein PR-Template gibt es im Repo nicht):
  1. In Studio veröffentlichen.
  2. `make content-export` ausführen.
  3. Den Export als eigenen PR einreichen, Commit-Präfix `content-export:`.

  Kein Drift-Job in CI (keine Produktions-DB), kein automatischer Commit.
- **Vermerkt** in `docs/content-schema.md` (neuer Kasten „Schreibpfad“, die
  bisherige Aussage „Metadaten immer datei-geführt“ ist korrigiert) und im
  README: Von Hand bearbeitete Content-PRs für bestehende Lektionen und
  Nodes sind nicht mehr der vorgesehene Weg.
- **`docs/offene-fragen.md`**: „`content:export` fehlt noch“ ist
  durchgestrichen und als umgesetzt markiert.
- **README**:
  - Arbeitsstand auf 24.09.2026 gesetzt
  - 9 Tracks, 6 davon veröffentlicht
  - 59 Lektionen, 42 davon veröffentlicht
  - 34 Node-Definitionen, 17 davon veröffentlicht

## Stand der offenen Fragen (nach Phase 4)

Alle Punkte sind vorläufig **zurückhaltend** umgesetzt und lassen sich ohne
Umbau ändern:

| # | Frage | vorläufig umgesetzt |
|---|---|---|
| 1 | Regel B für `Node.status`? | Nein: Ohne veröffentlichte Version bleibt der Status datei-geführt. Mit Version schützt ihn Regel A. |
| 2 | Lektion 3.3: Lernziel aus #16 wieder live setzen? | Offen, Betreiberhandlung. Kein Code ändert ContentVersions. |
| 3 | Syntax für `callout`/`dicom_tag_table`/`dicom_dump` | Keine: Der Serialisierer wirft eine Exception, der Export meldet die Ressource. Heute gibt es 0 Vorkommen. |
| 4 | Track-`title`/`teaser` | Werden nicht exportiert, `tracks.yml` bleibt bei `title_key`. |
| 5 | `--only=exams,achievements,glossary` | Nicht angeboten; der Befehl lehnt sie mit Begründung ab. |
| 6 | `GeneratedFileMarker` im Export | Wird nicht gesetzt. |
| 7 | `ContentWriter` gegen den `:ro`-Mount | Steht schon in `docs/offene-fragen.md` („`content/` ist … read-only gemountet“). Kein neuer Eintrag. |

Neu hinzugekommen:

- **Lektion 2.1**: Fettdruck ohne Leerzeichen vor „Fünf“ in Studio
  korrigieren. Danach ist 2.1 exportierbar.
- **Drift in 1.7, 2.3, 4.5**: Die Datei ist neuer (PR #158). Diese Stände
  vor dem ersten schreibenden Export in Studio veröffentlichen, sonst
  würde der Export sie im Repo zurückdrehen.
- **Erster schreibender Export gegen `dcmlab`**: eigener, letzter PR nach
  Freigabe (harte Regel des Auftrags).
- **Restore über `restoreVersion()`** (ADR 0121) ist unverändert. Nach
  einem Restore gilt dieselbe Regel A, der Export zieht den wiederhergestellten
  Stand nach.
