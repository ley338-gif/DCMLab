# 0122 — `content:export` und ein einziger Schreibpfad für Content

Status: Entwurf (Phase 0 — Audit)
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
