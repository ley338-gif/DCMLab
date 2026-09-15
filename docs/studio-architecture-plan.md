# DCMLab Studio — Architektur-Audit und Implementierungsplan (CMS-0)

## Status

Entwurf, 15.09.2026. Ergebnis von Phase **CMS-0** des Studio-Auftrags
(vollstaendiger Audit vor jeder groesseren Codeaenderung, wie explizit
gefordert). Noch keine Codeaenderung auf Basis dieses Dokuments -- das
ist bewusst so vorgesehen.

## Kontext

Der Betreiber hat entschieden, die im September 2026 ausgeschlossene
CMS-Option (`content/` im Git-Repo, kein CMS) rueckgaengig zu machen.
`docs/adr/0071-content-speicherort-autorenschicht.md` hatte diese
Entscheidung im Kern bereits vorweggenommen ("Die Datenbank wird die
Autoren-Wahrheit, `content/` wird ein erzeugtes Artefakt") -- dieser
Audit zeigt, dass diese Richtung nur fuer Metadaten wirklich umgesetzt
wurde, nicht fuer Fliesstext/Rich Content. Der neue Auftrag geht
deutlich weiter: ein vollstaendiges browserbasiertes LCMS mit
Modulsystem, Sandbox-Runtime-Abstraktion, WYSIWYG-Editor und
DCMLab-Studio-Adminbereich (`/studio`), in elf nummerierten Phasen
(CMS-0 bis CMS-11).

---

## 1. Current Architecture

### 1.1 Activity-Schicht (bereits gut vorbereitet)

Die Activity-Abstraktion (ADR 0072, ausdruecklich an Moodles `mod`/Open
edX XBlocks orientiert) ist **kein** Fall von Technical Debt -- sie ist
schon fast das, was der neue Auftrag als "Modulsystem" beschreibt:

| Baustein | Datei | Zweck |
|---|---|---|
| `ActivityContract` | `app/Activities/ActivityContract.php:25-98` | `activityType()`, `key()`, `supports()`, `learnerView()`, `authorView()`, `validate()`, `serialize()`, `deserialize()`, `result()` |
| `ActivityRegistry` | `app/Activities/ActivityRegistry.php:17-50` | `type => Closure(Activity):ActivityContract`-Map, `register()`/`resolve()`; wirft bei unbekanntem Typ |
| `ActivitySupports` | `app/Activities/ActivitySupports.php:10-24` | readonly Value Object: `isGraded`, `tracksCompletion`, `needsContainer`, `authorable`, `freelyPlaceable` -- **das ist bereits die im Auftrag (Abschnitt 4) verlangte Capability-Deklaration**, nur mit fuenf statt zehn Feldern |
| `ActivityType` | `app/Activities/ActivityType.php:12-20` | `Lesson\|Quiz\|Exam\|Node\|Sandbox\|Achievement`, laut eigenem Kommentar bewusst offen -- ein siebter Typ braucht nur eine neue Registry-Registrierung, keine Enum-Aenderung |
| `ActivityResult` | `app/Activities/ActivityResult.php:13-25` | readonly: `completed, score, maxScore, skills[], completedAt` |
| `ActivityProgressRecorder` | `app/Activities/ActivityProgressRecorder.php:27-64` | schreibt `activity_progress`, loest `AchievementUnlockEvaluator` aus |

Konkrete Implementierungen (alle `final readonly class ... implements
ActivityContract`): `LessonActivity`, `QuizActivity` (**ohne eigene
`activities`-Zeile**, direkt instanziiert, nicht ueber die Registry),
`NodeActivity`, `ExamActivity`, `SandboxActivity` (**ebenfalls ohne
eigene `activities`-Zeile**, an `Lesson` gebunden), `AchievementCatalogActivity`
(Singleton, `key() === 'catalog'`).

Audit auf Typ-Switches (`type ===`, `match($activity->...)`,
`instanceof`): **keine Verstoesse im Kernpfad gefunden.** Die einzigen
Vergleiche sind deklarative Kriterien-Abgleiche
(`AchievementUnlockEvaluator.php:89,105,115`, `ContentValidator.php:806`
gegen YAML-Kriterien, kein Code-Branch pro Typ). Ein legitimes Vorbild
fuer einen kuenftigen `SandboxRuntimeResolver` existiert bereits:
`EngineClientResolver::for()` (`app/Services/EngineClientResolver.php:20-26`),
`match ($node->interaction)` -> `EngineClientContract`.

`Activity`-Modell (`app/Models/Activity.php:35-84`): `type, key,
track_id, order, status, legacy_authors, title, teaser, source_hash`.
Relationen: `track()`, `progress()` (`activity_progress`),
`authorUsers()` (`activity_authors`-Pivot), `contentVersions()`.

### 1.2 Content-Pipeline

`ContentRepository` (`app/Content/ContentRepository.php`, Singleton in
`AppServiceProvider.php:36`) liest `content/**` **live von der
Festplatte, bei jedem Request**. 27 Dateien greifen darauf zu --
Controller (`LessonController`, `NodeController`, `QuizController`,
`ExamController`, `GlossaryController`, `ReviewController`, alle
Editor-Controller), alle Activity-Implementierungen, die
`AchievementRegistry`, alle `content:*`-Console-Commands.

`content:sync` (`app/Console/Commands/ContentSync.php`) ist reines
`updateOrCreate` -- **kein Loeschpfad**, bestaetigt fuer
`Themenfeld`/`Track`/`Lesson`+`Activity`/`Node`+`Activity`/`Activity`
fuer Exam/Achievement. Synced NICHT: `glossary/de.yml`,
`tools/de.yml`, `datasets.yml`, `skills.yml`, einzelne
`achievements.yml`-Eintraege -- die bleiben reine Dateien.

`ContentVersioningService`/`ContentVersion` (Tabelle
`content_versions`: `activity_id, status(draft|review|published),
payload(json), is_current, created_by, reviewed_by, published_at`) ist
bereits generisch fuer **alle** Activity-Typen ueber einen gemeinsamen
`ContentVersionController::submit()/publish()`. **Publish schreibt
aber synchron im selben Request zurueck nach `content/**`**
(`ContentVersionController.php:34-48` -> `ContentWriter::write()`,
`app/Content/ContentWriter.php:33-74`, inkl. `Artisan::call('content:sync')`,
`ContentBuilder::build()`, Cache-Invalidierung -- alles inline in der
HTTP-Anfrage). Das ist exakt der Pfad, der unter dem
`:ro`-Docker-Mount mit 500 scheitert (bereits in
`docs/offene-fragen.md` dokumentiert).

**Reifegrad je Inhaltstyp:**

| Typ | DB-Metadaten | Body/Fliesstext | Browser-Editor |
|---|---|---|---|
| Track | ja, vollstaendig | kein Fliesstext (nur `title_key`-i18n) | nein (nur ueber Exam-Editor fuer die Pruefung) |
| Lesson | ja | **nein**, `content/lessons/<id>/de.md` live gelesen | ja (`LessonEditorController`/`LessonEditor.vue`), schreibt in Dateien |
| Node | ja (Metadaten) | **nein**, `content/nodes/<slug>/{node.yml,de.md}` live | **nein** -- keinen Editor gefunden, Autoren muessen Dateien von Hand pflegen |
| Quiz | nein (kein eigenes Modell, embedded in Lesson-Dateien) | nein | ja, schreibt in Lesson-Dateien |
| Exam | ja (`Activity`-Zeile) | nein, `content/exams/<track>/*` live | ja, schreibt in Dateien |
| Achievement | ja (`achievement_definitions`), **aber nie automatisch nach Publish neu synchronisiert** | teils (Katalog-YAML) | ja, schreibt in `achievements.yml` |
| Glossar | **nein, keine Tabelle** | nein, rein Datei | **nein** |

Artisan-Befehle: `content:sync` (Import, Upsert), `content:build`
(Node-Flag-Hashes -> `node.yml`), `content:validate` (rein lesend,
keine DB). **Kein `content:import`/`content:export` vorhanden** -- nur
der implizite Einzelschreiber `ContentWriter::write()`.

### 1.3 Sandbox

`SandboxController` (`app/Http/Controllers/SandboxController.php:18-68`)
ist **hart an `Lesson` gekoppelt**: `create()` nimmt `Lesson $lesson`
per Route-Model-Binding und liest `$lesson->sandbox['dataset']` --
eine JSON-Spalte auf `lessons`, **kein eigenes Sandbox-Modell, keine
eigene Tabelle**. `SandboxClientContract`/`SandboxClient`
(`app/Services/SandboxClient*.php`) ist ein duenner HTTP-Client zum
Python-Orchestrator -- die einzige vorhandene Abstraktionsschicht,
aber Single-Implementation, kein Resolver/Strategy-Muster.

Tatsaechliche Container-Ausfuehrung: `services/sandbox/app/docker_ops.py`
(einziger Ort mit `docker.from_env()`), baut pro Sitzung ein
Orthanc+Toolbox-Containerpaar auf einem isolierten `internal=True`-Netz.

**Bereits vorhandene Sicherheitsmassnahmen** (nichts davon muss neu
gebaut werden): `mem_limit=256m`, `nano_cpus=0.5`,
`security_opt: no-new-privileges`, `pids_limit=128`,
`cap_add` nur eng begrenzt fuer Toolbox (`NET_RAW`/`NET_ADMIN`,
begruendet in ADR 0029), Netzwerk-Isolation ohne Egress, Idle-Timeout,
Tages-Kontingent, Concurrency-Limit mit FIFO-Warteschlange
(`config.py`, `orchestrator.py:35-65`). Docker-Socket ist **nur** im
`sandbox`-Service gemountet, nicht in `app`/`engine`/`scenario-engine`.
Zwei kleinere Luecken, unabhaengig vom Studio-Umbau: Generator-Container
laufen als `root` (`docker_ops.py:168,225`), `exec_command` hat kein
eigenes Wall-Clock-Timeout.

`SandboxActivity` (`app/Activities/SandboxActivity.php:20`) und
`SandboxController` sind **zwei unabhaengige Pfade**, die beide an
`Lesson` haengen, sich aber nie beruehren -- `SandboxActivity` wird nie
ueber die Registry mit einer echten `activities`-Zeile verknuepft.

### 1.4 Rollen, Policies, Routen

`UserRole`-Enum (`app/Models/UserRole.php:10-15`): nur `Learner`,
`Author`, `Reviewer` -- **kein Administrator**. `role`-Spalte ist ein
einfaches `string` (`add_role_to_users_table.php:16`, kein
DB-Constraint) -- ein vierter Fall ist eine reine Enum-Erweiterung,
keine Migration noetig.

Zwei Policies: `UserPolicy` (`viewAny`/`update`, beide `=== Reviewer`),
`ActivityPolicy` (`update`: Reviewer immer, Author nur bei Zuordnung
ueber `activity_authors`; `publish`: nur Reviewer). Ein Ausreisser:
`AuthorPanelController.php:28,44` prueft die Rolle **inline**
(`abort_if($user->role === UserRole::Learner, 403)`), nicht ueber eine
Policy -- vor Einfuehrung von `administrator` sollte das
vereinheitlicht werden.

Alle Autoren-Routen liegen unter `de/author/*`, keine eigene
Middleware/Policy auf Routenebene (Autorisierung sitzt in den
Controllern, siehe `routes/web.php:71-74`-Kommentar). **Kein
`/admin`- oder `/studio`-Praefix existiert bisher.**

### 1.5 Vue-Autorenoberflaechen

Zwei getrennte Layout-Shells, Auswahl per Seitennamen-Switch in
`resources/js/app.ts:13-40`: `GlobalLayout`+`GlobalHeader.vue`
(Dashboard/Tracks/Nodes/Glossary/Review, Top-Nav) vs. Default
`AppLayout`+`AppSidebar.vue` (alles andere inkl. aller
`Author/*.vue`-Seiten, linke Sidebar). Sieben Author-Seiten
(`Panel`, `ReviewQueue`, `Users`, `LessonEditor`, `QuizEditor`,
`ExamEditor`, `AchievementEditor`).

Wiederverwendbare Bausteine vorhanden: `Breadcrumbs.vue`,
`PageContainer.vue`, komplette shadcn-vue-Bibliothek unter
`components/ui/*`. **Fehlt komplett**: generische Resource-Tabelle,
`StatusBadge`, `SaveButton`, `PublishMenu`, `DangerZone`,
`RevisionHistory`, `SortableList`, `ArchiveDialog`/`DeleteDialog`,
`EmptyState` -- jeder Editor baut Draft/Submit/Publish-UI derzeit
einzeln nach.

### 1.6 Docker/Deployment

`infra/docker-compose.yml`: `postgres, valkey, app, scheduler, caddy,
engine, scenario-engine, sandbox`. `content/` ist **read-only**
gemountet fuer `app` (`:56`), `scheduler` (`:90`), `engine` (`:117`),
`scenario-engine` (`:132`), `sandbox` (`:151`). Docker-Socket **nur**
in `sandbox` (`:150`). `infra/docker-compose.dev.yml` ueberschreibt
den `content`-Mount **nirgends** -- Publish schlaegt daher in jeder
lokalen Dev-Umgebung fehl (offene Frage, siehe
`docs/offene-fragen.md`).

---

## 2. Technical Debt / Coupling

1. **ADR 0071 ist nur fuer Metadaten umgesetzt, nicht fuer
   Fliesstext.** `ContentRepository` wird bei jedem Lern-/Autoren-Request
   fuer Lesson-/Node-/Exam-/Quiz-Body live gelesen -- die "Eine
   Richtung, nie zurueckgelesen"-Regel gilt faktisch nur fuer die
   bereits synchronisierten Tabellenspalten, nicht fuer den eigentlichen
   Lerninhalt. Das ist der Kern des CMS-Auftrags.
2. **Sandbox-Kopplung an Lesson**: keine eigene Tabelle, JSON-Spalte
   auf `lessons`, Route-Bindung an `{lesson}`, zwei nie vereinte Pfade
   (`SandboxController` vs. `SandboxActivity`). Blockiert direkt
   Abschnitt 8/9 des Auftrags (freie Platzierbarkeit, Runtime-Provider-
   Abstraktion).
3. **Quiz hat keine eigene `activities`-Zeile**, teilt sich die von
   `Lesson`. Ein Lesson Composer mit frei sortierbaren Elementen
   (Abschnitt 5/6 des Auftrags) kann Quiz so nicht als eigenstaendiges,
   platzierbares Element behandeln, ohne das Modell zu aendern.
4. **`achievement_definitions` veraltet stillschweigend**: `ContentWriter`
   aktualisiert die Tabelle nach einem Publish nie, nur der
   Einmal-Seeder tut das. Ein Achievement-Editor-Publish aendert
   `achievements.yml`, aber die laufzeitrelevante Tabelle nicht --
   ein bereits bestehender, bisher unbemerkter Bug.
5. **Node hat ueberhaupt keinen Browser-Editor** -- groesserer Rueckstand
   als bei Lesson/Quiz/Exam (die zumindest einen Datei-schreibenden
   Editor haben).
6. **`content:sync` loescht nie** -- jedes DB-only "Loeschen"/"Archivieren"
   wird beim naechsten Sync wiederhergestellt, solange die Quelldatei
   noch existiert; unter dem aktuellen `:ro`-Mount ist Datei-Loeschen
   im Container ohnehin unmoeglich.
7. **Kein `content:export`** -- nur der implizite Einzelschreiber
   `ContentWriter::write()`, der zugleich der zerbrochene Pfad ist.
8. **Rollenpruefung teils ausserhalb der Policy-Schicht**
   (`AuthorPanelController`) -- vor Einfuehrung von `administrator`
   sollte das vereinheitlicht werden, sonst verdoppelt sich die
   Inkonsistenz.
9. **Kein gemeinsames "Autoren-Ressource"-Muster**: jeder Editor
   (Lesson/Quiz/Exam/Achievement) baut Draft/Validate/Submit/Publish
   eigenstaendig nach, mit eigenem Controller UND eigener Vue-Seite --
   genau das, was Abschnitt 13 des Auftrags als Studio-Komponenten
   vereinheitlichen soll.

---

## 3. Target Architecture

- **Datenbank wird alleinige Laufzeitquelle** fuer alle Inhaltstypen
  (inkl. Rich Content als strukturierter Block-Baum, nicht Markdown).
- `content/` wird ausschliesslich fuer `content:import`/`content:export`,
  Demo-/Seed-/Backup-Zwecke verwendet -- **nie** mehr automatisch
  zurueckgelesen im Laufzeitpfad. Keine dauerhafte bidirektionale
  Synchronisation.
- `ContentRepository` wird in zwei Verantwortlichkeiten aufgetrennt:
  ein Laufzeit-Lesemodell, das ausschliesslich aus der DB liest (fuer
  Lesson-/Node-/Exam-Controller etc.), und ein separates
  Import/Export-Subsystem, das nur unter einer expliziten,
  admin-only, gewarnten Aktion `content/**` anfasst (genau wie der
  Auftrag in Abschnitt 2 fordert).
- Die bestehende Activity-Architektur (`ActivityContract`/`Registry`/
  `ActivitySupports`) bleibt Kern des Modulsystems, wird nur um
  weitere Capabilities erweitert (Abschnitt 5).
- Sandbox wird auf ein Drei-Ebenen-Modell umgestellt
  (`SandboxTemplate`/`SandboxActivity`/`SandboxSession`), hinter einer
  `RuntimeProviderContract`-Abstraktion, ohne die bereits guten
  Sicherheitsmassnahmen in `services/sandbox` neu zu bauen.
- Ein neuer `/studio`-Adminbereich mit gemeinsamer Layout- und
  Komponentenbibliothek ersetzt schrittweise `/author/*`.

---

## 4. Proposed Database Schema (Ausblick, wird je Phase konkretisiert)

Neue/erweiterte Tabellen (Reihenfolge grob nach Phase):

| Tabelle | Zweck | Phase |
|---|---|---|
| `users.role` (+`administrator`) | vierte Rolle, reine Enum-Erweiterung, keine Migration | CMS-3 |
| `sandbox_templates` | `slug, name, description, runtime_provider, runtime_image, resource_profile(json), timeout_default, network_policy, environment(json), status, version, created_by, updated_by` | CMS-2 |
| `sandbox_sessions` | `user_id, activity_id, sandbox_template_id, sandbox_template_version, runtime_provider, runtime_instance_id, status, started_at, last_activity_at, expires_at, finished_at, result(json)` -- Laufzeitzustand bleibt evtl. primaer in Redis (siehe Abschnitt 9, offener Punkt), diese Tabelle waere die durable Aufzeichnung | CMS-2 |
| `activities` (erweitert) | `parent_id`/`context`, `position` -- fuer frei platzierbare Elemente einer Lesson | CMS-6 |
| `lesson_elements` | geordnete Sequenz aus Content-Bloecken/Activities je Lesson: `lesson_id, element_type, position, content_block_id\|activity_id, config(json)` | CMS-6 |
| `content_blocks` | strukturierter Block-Baum (ProseMirror/TipTap-artiges JSON) je Activity/Version, ersetzt `de.md` als Quelle | CMS-7 |
| `glossary_terms` | migriert `content/glossary/de.yml` in die DB | CMS-8 |
| `pages` | `title, slug, content(json), status, template, published_at` | CMS-9 |
| `menus`, `menu_items` | `menu_id, type(page\|track\|lesson\|lab\|route\|url), target, position, parent_id` | CMS-9 |
| `media` | `filename, mime_type, size, width, height, alt_text, caption, uploaded_by` | CMS-9 |
| `audit_log` | generisches Audit-Log fuer Publish/Delete/Rollenaenderungen | CMS-10 |

Generische Zustaende/Beziehungen/Rechte bleiben in echten Spalten,
JSON nur fuer modulspezifische Konfiguration (wie vom Auftrag in
Abschnitt 7 verlangt) -- keine "alles-in-JSON"-Architektur.

---

## 5. Module Architecture

`ActivitySupports` (`app/Activities/ActivitySupports.php`) wird von
fuenf auf die im Auftrag (Abschnitt 4) genannten zehn Capabilities
erweitert: bereits vorhanden `isGraded`, `tracksCompletion`,
`needsContainer` (~`needsRuntime`), `authorable`, `freelyPlaceable`;
neu `runtimeType`, `previewable`, `duplicatable`, `versionable`,
`reusable`. Additiv, keine Neukonzeption -- `ActivityRegistry`/
`ActivityContract` bleiben unveraendert in ihrer Grundstruktur.

Fuer Phase-1 (nur interne Module, kein Drittanbieter-Upload, wie vom
Auftrag Abschnitt 4/21 gefordert) reicht eine
`ModuleProvider`-Registrierung analog zur bestehenden
`ActivityRegistry::register()` -- kein dynamisches Plugin-Laden.

---

## 6. Sandbox Runtime Architecture

Drei Ebenen (Auftrag Abschnitt 8): `SandboxTemplate` (nur von
Administratoren angelegt/freigegeben), `SandboxActivity` (konkrete
Verwendung in einer Lesson, referenziert ein Template + Konfiguration
innerhalb der vom Template erlaubten Grenzen), `SandboxSession`
(laufende Instanz, wie heute grossteils in
`services/sandbox`/Redis verwaltet).

`RuntimeProviderContract` mit `DockerRuntimeProvider` als einziger
Phase-1-Implementierung -- Vorbild ist das bereits im Code vorhandene
`EngineClientResolver`-Muster (`match`-basierter Resolver hinter
einem Contract). Autoren waehlen nur Template + Dataset + Timeout,
**nie** rohe Container-Konfiguration -- das ist heute schon so (kein
Author-Pfad erlaubt beliebige Docker-Optionen), muss also nicht neu
abgesichert werden, nur strukturell im neuen Modell nachgebildet
werden.

`SandboxController` wird schrittweise durch einen `RuntimeManager`
ersetzt, der `SandboxActivity` (ueber `ActivityRegistry`) mit
`SandboxSession`+`RuntimeProviderRegistry` verbindet -- die
Python-Seite (`docker_ops.py`, `orchestrator.py`) bleibt inhaltlich
unveraendert, nur der Aufrufpfad von Laravel aus wird generischer.

---

## 7. Studio Architecture

`/studio`-Praefix mit gemeinsamem `StudioLayout.vue`
(Sidebar/Breadcrumbs/Header/StatusBadge/SaveStatus/Preview/Publish-
Aktionen/Suche/Benutzermenue). Gemeinsame Komponenten (neu, siehe
Luecke in Abschnitt 1.5): `ResourceHeader`, `ResourceTable`,
`StatusBadge`, `SaveButton`, `PublishMenu`, `DangerZone`,
`RevisionHistory`, `SortableList`, `ArchiveDialog`, `DeleteDialog`,
`EmptyState`. Bestehende `Breadcrumbs.vue`/`PageContainer.vue`/
shadcn-`ui/*` werden wiederverwendet, nicht ersetzt.

`/author/*` bleibt waehrend der Migration funktionsfaehig (keine
Big-Bang-Ablösung); Studio-Aequivalente werden Ressource fuer
Ressource ergaenzt, alte Routen am Ende auf `/studio/*` umgeleitet.

---

## 8. Migration Strategy

- Additive Migrationen, keine Datenverluste; bestehende Tests bleiben
  gruen nach jeder Phase (bestaetigt aktuell: 414 Tests, PHPStan
  Level 7, Pint, `vue-tsc`, `npm run build`).
- `content:sync`/`ContentWriter`/bestehende Editoren funktionieren
  waehrend der gesamten Umstellung weiter -- kein Big-Bang-Rewrite.
- Der `:ro`-Mount-Fehler (bereits offene Frage) sollte **frueh**
  behoben werden, idealerweise noch vor CMS-1, weil er schon heute
  jede Veroeffentlichung lokal blockiert und jede neue Studio-Funktion
  sonst gegen denselben kaputten Pfad testen wuerde.
- `content:import`/`content:export` werden explizit eingefuehrt,
  bevor `content:sync` aus dem Laufzeitpfad entfernt wird (CMS-11) --
  kein Content geht dabei verloren.
- Rich-Content-Editor (WYSIWYG) explizit **zuletzt** (CMS-7), wie vom
  Auftrag gefordert -- DB-Wahrheit, Modulsystem, Studio, Tracks,
  Lessons, Composer muessen zuerst stehen.

---

## 9. Compatibility Risks

- **Wayfinder-Routen-Codegen**: `php artisan wayfinder:generate` MUSS
  mit `--with-form` laufen (passend zu `vite.config.ts`s
  `formVariants: true`), sonst brechen `.form`-Varianten in
  unbeteiligten Seiten -- bereits einmal in dieser Session passiert.
- `content:sync`s Upsert-only-Verhalten heisst: Archivieren/Loeschen
  braucht explizite Behandlung (DB-Flag + Datei-Entfernung im Zuge von
  CMS-11), sonst Wiederauferstehung beim naechsten Sync.
- `QuizActivity`/`SandboxActivity` haben heute keine eigene
  `activities`-Zeile -- ihre Umstellung auf echte, eigenstaendige
  Activities (fuer den Lesson Composer) muss sorgfaeltig mit
  `activity_authors`/`content_versions`-Fremdschluesseln und
  bestehenden Tests (`QuizActivityTest`, `SandboxActivityTest`)
  abgestimmt werden.
- `achievement_definitions`-Staleness (Abschnitt 2.4) sollte vor oder
  waehrend der Migration behoben werden, damit sie sich nicht mit
  neuen CMS-Schreibpfaden ueberlagert.
- Jede neue Migration/jeder neue Editor muss gegen die bestehende
  Testbasis (`tests/Unit/Activities/*`, `tests/Feature/*`) laufen,
  bevor sie als abgeschlossen gilt.

---

## 10. Security Considerations

Die Sandbox-Sicherheit ist **bereits stark** (siehe 1.3) -- Resource-
Limits, Netzwerk-Isolation, Kontingente, kein Docker-Socket im
Learner-Container, kein `privileged`. Das Drei-Ebenen-Modell
(Abschnitt 6) veraendert daran nichts, es macht nur explizit, dass nur
Administratoren Templates anlegen/freigeben duerfen (Policy-Gate, wie
heute schon faktisch der Fall, nur ohne eigenes Modell). Zwei
unabhaengige, kleinere Haertungspunkte (Generator-Container als
`root`, kein `exec_command`-Timeout) sollten separat in
`docs/offene-fragen.md` nachverfolgt werden -- sie sind nicht Teil des
Studio-Umbaus, aber wurden bei diesem Audit gefunden.

Rollen/Policies: `administrator` erhaelt volle Rechte (Templates,
Runtime-Settings, Nutzer, Seiten, Navigation, globale Einstellungen,
Loeschvorgaenge) -- weiterhin ueber Laravel Policies/Gates, keine
zusaetzliche RBAC-Engine (wie vom Auftrag Abschnitt 18 gefordert).

---

## 11. Phasen (Uebersicht)

| Phase | Kernziel | Bezug zu Audit-Befund |
|---|---|---|
| CMS-0 | Dieser Audit | -- |
| CMS-1 | `ActivitySupports` um fehlende Capabilities erweitern, `ModuleProvider`-Registrierung | additiv auf bestehender, bereits guter Architektur |
| CMS-2 | `SandboxTemplate`/`SandboxSession`/`RuntimeProviderContract`, `SandboxController` schrittweise ablösen | loest die staerkste Kopplung (1.3/2.2) |
| CMS-3 | `administrator`-Rolle, `/studio`, `StudioLayout`, gemeinsame Resource-Komponenten, Policies vereinheitlichen (inkl. `AuthorPanelController`-Ausreisser) | schliesst Luecke 2.8/2.9 |
| CMS-4 | Track-CRUD komplett inkl. Archiv/Duplizieren/Sortieren | Track hat heute keinen echten Editor |
| CMS-5 | Lesson vollstaendig DB-basiert -- **schliesst die zentrale ADR-0071-Luecke**: Body nicht mehr aus `content/` gelesen | groesster Einzelbefund (2.1) |
| CMS-6 | Lesson Composer, `lesson_elements`, Quiz als eigenstaendige Activity | loest 2.3 |
| CMS-7 | Rich-Content-Block-Editor (WYSIWYG), Lizenzpruefung vor Bibliothekswahl | bewusst zuletzt, wie gefordert |
| CMS-8 | Studio-Editoren fuer Quiz/Exam/Node/Lab/Achievement/Glossar -- **erster Node-Editor ueberhaupt**, `achievement_definitions`-Sync fixen | schliesst 2.4/2.5 |
| CMS-9 | Pages/Navigation/Media | neue CMS-Grundfunktionen |
| CMS-10 | Draft/Review/Published/Archive/Trash global vereinheitlicht, Audit Log | 2.6 |
| CMS-11 | Legacy-Migration, `content:import`/`content:export`, `content:sync` aus dem Laufzeitpfad entfernen | schliesst 2.6/2.7 |

Jede Phase bekommt bei Umsetzung eine eigene ADR (wie im Projekt
ueblich) -- dieses Dokument wird dort jeweils referenziert und bei
Bedarf aktualisiert.

## 12-15. Betroffene/neue Dateien, Migrations, Tests

Werden je Phase konkretisiert (nicht pauschal fuer alle elf Phasen im
Voraus, um nicht gegen einen sich noch aendernden Vertrag zu
entwickeln -- derselbe Grundsatz, den `docs/adr/0071-...md` bereits
fuer das Engine-Modul-Thema festhaelt). Fuer **CMS-1** (naechster
Schritt) konkret:

**Betroffene Dateien**: `app/Activities/ActivitySupports.php`,
`ActivityContract.php` (Doc-Kommentar), alle sechs
`*Activity.php`-Implementierungen (`supports()`-Aufrufe ergaenzen).

**Neue Dateien**: keine Pflicht fuer CMS-1 selbst; `ModuleProvider`-
Interface erst wenn ein zweiter interner Modultyp ansteht.

**Migrations**: keine (rein PHP-seitige Erweiterung eines Value
Objects).

**Tests**: `tests/Unit/Activities/ActivityRegistryTest.php` und die
sechs Activity-Tests um Assertions fuer die neuen Capability-Felder
ergaenzen; keine bestehenden Tests duerfen brechen.

---

## Verifikation dieses Dokuments

- Alle Aussagen stammen aus direkter Code-Inspektion (drei parallele
  Audits ueber Activity/Sandbox, Content-Pipeline, Rollen/Routen/
  Vue/Docker) am Stand von Commit 84d71c1, nicht aus Spekulation.
- Keine Codeaenderung wurde im Rahmen dieses Dokuments vorgenommen --
  CMS-1 beginnt erst nach Rueckmeldung des Betreibers zu diesem Plan.
