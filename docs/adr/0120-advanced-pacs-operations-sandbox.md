# 0120 — Advanced PACS Operations Sandbox: Routing, Jobs und Multi-Hop

## Status

Proposed, 2026-09-20. Reine Architekturentscheidung ohne Implementierung
in diesem Schritt — siehe "Entscheidungspunkte" am Ende. Wird erst nach
Betreiber-Review konkretisiert/umgesetzt.

## 1. Problem

Mehrere fortgeschrittene Nodes (`letztes-glied-fehlt`, `nur-ein-bild`,
`dosis-bleibt-liegen`) wurden zurecht als `interaction: scenario`
gebaut, weil der simulierten Terminal-Engine (`services/engine`)
konkrete Fähigkeiten fehlten (MPPS/Storage Commitment, Multiframe-
Metadaten, PACS-interne Routingregeln/Job-Queues). Das war jeweils die
richtige kurzfristige Entscheidung — kein Scope Creep, keine
Speziallogik nur für einen Node.

Langfristig besteht aber das Risiko, dass jeder neue fortgeschrittene
PACS-Betriebsfall zu "Log lesen → Multiple Choice → nächstes Log →
Multiple Choice" wird, statt zu echter Hands-on-Administration
(`$ pacs jobs --study A94421`, `$ dcmdump rdsr.dcm`, `$ pacs routes
show CT-TO-DOSE`, `$ pacs route test CT-TO-DOSE rdsr.dcm`). Dieses ADR
untersucht, ob und wie eine generische "PACS Operations"-Fähigkeit
(Routing, Job Queue, Multi-Hop) in die bestehende Engine-Architektur
passt, ohne die etablierten Prinzipien (schema-getrieben, keine
Node-spezifische Engine-Logik, ein Speicherort pro Frage) zu verletzen.

## 2. Heutige Architektur (Bestandsanalyse)

### 2.1 Terminal-Engine (`services/engine`)

FastAPI-Service, vollständig generisch aufgebaut:

- `app/main.py` kennt **keine** DICOM-Semantik. Es ist ein dünner
  REST-Adapter: `POST /v1/sessions`, `GET .../state`, `POST .../exec`,
  `.../config`, `.../action`, `.../hint`, `.../write-up`, `.../flag`,
  `DELETE /v1/sessions/{id}`, `POST /internal/cache/clear`. Jeder
  Handler lädt die `NodeDefinition` frisch (`content.load_node`, kein
  Caching außer `datasets.yml`) und delegiert an `rules.py`.
- `app/content.py`: `NodeDefinition` ist ein `@dataclass(frozen=True)`
  um den rohen `node.yml`-Dict — reine Lese-Accessors (`hosts`,
  `host()`, `known_calling_aets`, `tools`, `dataset_slug`, `hints`,
  `flag_hash`, `stuck_timeout_minutes`). Kein Schreibpfad; Schreiben
  passiert nur auf Laravel-Seite (`content:build`).
- `app/rules.py` (rund 900 Zeilen) enthält **die gesamte** DICOM-/
  Simulationslogik als reine Funktionen über einen `state`-Dict:
  `initial_state`, `check_association`, `exec_command` (Dispatch auf
  `echoscu|storescu|findscu|dcmdump|dcmftest|ping|ls|cat|echo|help`),
  `trigger_action` (aktuell nur `send_study` auf einem
  `modality-simulator`-Host), `set_config`, `use_hint`,
  `view_write_up`, `check_flag`. Kein `if node.slug == "..."` an
  irgendeiner Stelle — bestätigt durch `tests/test_real_content.py`,
  das die echten, ausgelieferten Nodes gegen genau dieses Regelwerk
  laufen lässt ("wenn dieser Test nur mit Node-spezifischem Engine-Code
  grün wird, ist das ein Schema-Mangel", Zitat aus dem Testdocstring).
- `app/find.py`: eigenständiges, zustandsloses C-FIND-/Wildcard-
  Matching-Modul (PS3.4 C.2.2.2.4), seit P10.1 (ADR 0011) bewusst als
  eigene Datei statt als Erweiterung von `rules.py` — **das ist bereits
  ein Präzedenzfall für "neue Fähigkeit = eigenes Modul, vom
  Dispatcher aus `rules.py` aufgerufen"**, dieselbe Struktur, die
  dieses ADR für PACS Operations vorschlägt.
- Sitzungszustand: `EngineSession.state`, eine `JSONB`-Spalte
  (`JSON` unter SQLite für Tests) — **schemalos**. Neue Top-Level-Keys
  im State-Dict brauchen keine Migration, weder in Postgres noch in
  den Tests.
- `environment.objects[]` trägt heute flache, benannte Felder
  (`filename`, `bytes`, `sop_class`, `transfer_syntax`, `modality`,
  `study_uid`, `series_uid`, `patient_id`, `slice_thickness`,
  `convolution_kernel`, `study_description`,
  `lossy_image_compression`) statt eines generischen Tag-Dicts.
  `DCMDUMP_FIELD_ORDER` in `rules.py` ist die kanonische Liste
  "welches benannte Feld zeigt `dcmdump` unter welchem Tag/Keyword an".
- `bestand[host]` ist **nur ein Aggregat-Zähler**
  (`{studies, series, instances}`), keine adressierbare Liste "welche
  Objekte liegen gerade an diesem Host". Das ist eine echte Lücke für
  objektgenaues Routing (siehe 8.3).
- `ExecResult.events` / `ActionResult.events` existieren bereits
  (`association_accepted`, `association_rejected`,
  `presentation_context_rejected`, `store_completed`, ...), werden
  aber **nicht** in `state` persistiert — nur einmalig in der
  API-Antwort zurückgegeben. Eine spätere `pacs events`-Abfrage kann
  sie heute nicht sehen.

### 2.2 Scenario-Engine (`services/scenario-engine`)

Eigenständiger FastAPI-Service, **API-formgleich** zu `services/engine`
(identische Routen, identische `schemas.py`), aber strukturell viel
einfacher und **komplett DICOM-frei**:

- `exec`/`config` sind fest verdrahtete No-Ops ("Auf dieser Node gibt
  es keine Shell." / "Diese Node hat keine Konfiguration.") — es gibt
  keinen Host-, Shell- oder Netzwerkbegriff.
- Der gesamte Zustand ist `current_step_id` + `log[]` (Verlauf) plus
  dieselben generischen Felder wie die DICOM-Engine
  (`hints_used`, `solved`, `points`, `stuck`, `created_at`).
- `trigger_action` bewegt bei jedem Aufruf genau eine Kante im
  Entscheidungsbaum (`scenario.steps[current].options[].next`) — kein
  serverseitiges Mehrschritt-Auto-Advance.
- Flag-Prüfung ist identisch zur DICOM-Engine: ein Hash auf
  Node-Ebene (`flag.hash`), unabhängig vom aktuellen Schritt. `reveal`
  wird vom Python-Service **nie gelesen** — es ist ein reines
  Build-Artefakt (`ContentBuilder::resolveScenarioReveal()` liest den
  `outcome: correct`-Schritt zur Build-Zeit und hasht dessen `reveal`
  in `flag.hash`).
- Eigene Datenbank-Tabelle `scenario_sessions` (gleiche Postgres-
  Instanz, eigener Tabellenname), eigenes `init_db()`.

**Schlussfolgerung**: die Scenario-Engine ist kein Kandidat, um Hands-on-
PACS-Administration zu tragen — sie hat strukturell keinen Shell-/Host-
Begriff und ihre `exec`/`config`-Endpunkte sind bewusst als No-Ops
gebaut. Sie bleibt, was sie ist: ein generischer Entscheidungsbaum-
Walker für Kommunikations-/Prozess-/Rechts-Fälle ohne Kommandozeile.

### 2.3 Laravel-Integrationsschicht

- `EngineClientResolver::for(Node $node)` routet **ausschließlich**
  über die DB-Spalte `node.interaction`
  (`'scenario' => ScenarioEngineClient, default => EngineClient`).
  Jeder andere Wert fällt auf die Terminal-Engine.
- `EngineClient` und `ScenarioEngineClient` implementieren dasselbe
  `EngineClientContract` (8 Methoden: createSession, state, exec,
  setConfig, triggerAction, useHint, viewWriteUp, submitFlag).
- **Zentraler Befund**: Laravel **persistiert `environment`/`scenario`
  nirgendwo** und **leitet den Blob nie über HTTP weiter**.
  `EngineClient::createSession()` sendet nur `{node_slug}`. Die
  `nodes`-Tabelle hat keine `environment`- oder `scenario`-Spalte
  (weder strukturiert noch als JSON) — bestätigt über
  `Node::$fillable`/`casts()` und `ContentSync::syncNodes()`. Beide
  Python-Dienste lesen `content/nodes/<slug>/node.yml` **direkt vom
  Dateisystem**, unabhängig von Laravel/der DB (ADR 0071 bestätigt das
  explizit: "Die Python-Dienste lesen `content/` direkt vom
  Dateisystem ... Sie hängen genauso am Dateiformat wie Laravel").
  **Das bedeutet: eine neue `environment`/`routes`-Struktur in
  `node.yml` braucht keine Laravel-Migration und keine
  Laravel-API-Änderung, um zu funktionieren.**
- `ContentValidator.php` validiert heute **nicht** die Struktur von
  `environment.hosts[].services[]`/`records`/`worklist`/`objects` —
  nur `dataset`-Referenz, `tools`-Referenz, `templates`/`placeholders`-
  Konsistenz. Der `scenario.steps`-Graph dagegen ist vollständig
  validiert (jedes `next` muss existieren, jeder Terminalschritt
  braucht `outcome`, jedes `outcome: correct` braucht `reveal`,
  mindestens ein gewinnbarer Pfad). **Host-/Service-/Routing-
  Validierung ist komplettes Neuland, kein bestehendes Verhalten wird
  gebrochen.**
- `ContentBuilder::resolveTagValue()` ist ein hartcodiertes `match` auf
  `flag.source_tag` (`'scenario'`, `'0008,103E'`) — jeder neue
  Flag-Mechanismus (z. B. ein routing-basiertes Flag) bräuchte hier
  eine explizite neue Klausel; das Fehlen einer Klausel führt zu einem
  lauten `RuntimeException`, nie zu stillem Fallback.

### 2.4 Frontend

- `Nodes/Show.vue` ist **eine** Komponente mit `v-else-if`-Zweig:
  `interaction === 'scenario'` → `ScenarioPlayer.vue`, sonst → Tabs mit
  `EngineTerminal.vue` (xterm.js) + optionalen Konsole-/Archiv-Tabs.
- `EngineTerminal.vue` hat **keine Host-Auswahl** (immer der erste
  Host mit `role: shell`), **keine Kommando-Historie** (kein
  Pfeiltasten-Recall), aber Template-Einfügung + Tab-Sprung zwischen
  bekannten Platzhaltern. Es sendet `{host, command}` an `/exec` und
  schreibt `stdout`/`stderr` zurück; `exit_code` wird nie angezeigt.
- `public_state` wird **roh durchgereicht** (kein Whitelisting, kein
  Transform) — neue State-Keys (`routes`, `jobs`, `events`) würden im
  JSON ankommen, aber von der heutigen UI **stillschweigend
  ignoriert**, weil `Show.vue` nur explizit benannte Properties liest
  (`state.hosts`, `state.scenario`, `state.solved`, ...). Es gibt
  keinen generischen Renderer.
- Präzedenzfall für ein neues Info-Panel existiert bereits: die
  "Konsole"- und "Archiv"-Tabs sind schon heute bespoke, Host-Shape-
  getriebene Panels neben dem reinen Terminal — ein zukünftiges
  "Jobs"/"Events"-Tab würde demselben Muster folgen, ist aber für
  Phase 1 **nicht nötig** (siehe 9.6).

### 2.5 Content-Schema & Validierungs-ADRs

`docs/content-schema.md` §6/§6a–j deckt den vollständigen heutigen
`node.yml`-Vertrag ab (siehe Abschnitt "Capability-Matrix"). Die
tragende, wörtlich zitierbare Leitlinie stammt aus **ADR 0005**
(P4, Grundlagen der Engine):

> "Eine neue Node mit demselben Schema ... braucht keine
> Engine-Änderung, sofern sie mit `environment.hosts`,
> `known_calling_aets`, `tools`, `dataset`, `flag`, `hints` auskommt.
> Sollte [ein neuer Node] doch Engine-Code brauchen, ist das ...
> selbst ein Schema-Mangel und wird dann als eigene ADR dokumentiert,
> nicht stillschweigend verbaut."

Genau das passiert hier: `letztes-glied-fehlt`, `nur-ein-bild` und
`dosis-bleibt-liegen` haben je einen echten Schema-Mangel aufgedeckt
(MPPS/Storage Commitment, Multiframe-Metadaten, PACS-Routing/Job-
Queue). Dieses ADR ist die "eigene ADR" für den **Routing/Job**-Teil
davon — bewusst nicht rückwirkend für die schon gemergten Scenario-
Nodes, sondern für künftige Nodes.

**Wichtiger Nebenbefund**: für die Einführung von `interaction:
scenario` selbst existiert **kein** dediziertes ADR — die
Content-Schema-Doku zitiert fälschlich ADR 0071 (das tatsächlich die
DB-als-Autorenschicht-Entscheidung behandelt, nichts mit Scenario zu
tun hat). Die einzige dokumentierte Begründung ist eine Commit-Message
(`ca57ba7`, "P10.69"): "Beweist das Engine-Plugin-Modell ... vollständig:
services/scenario-engine ist ein komplett eigenständiger Microservice
... an den Laravel über einen neuen EngineClientResolver pro Node
routet." Dieses ADR füllt also auch eine reale Dokumentationslücke,
statt einer bestehenden, durchdachten Entscheidung zu widersprechen.

**Prinzip aus ADR 0071** (Autorenschicht), direkt relevant: *"Es gibt
zu jeder Frage genau ein System. ... Entsteht bei einer Umsetzung eine
zweite Variante davon, ist die Umsetzung falsch."* — PACS Operations
darf keinen zweiten Speicher-/Validierungspfad neben `content/` +
`ContentValidator` aufbauen.

**Prinzip aus ADR 0072** (Aktivitätsvertrag): *"Neue Inhaltsarten sind
neue Module, keine neuen Sonderfälle."* `ActivityRegistry` ist
ausdrücklich nach dem Vorbild von `EngineClientResolver` gebaut — ein
weiterer Beleg, dass "ein Dispatcher, mehrere austauschbare Module" das
etablierte Muster dieses Repos ist, nicht nur eine Engine-Idee.

## 3. Warum Scenario allein langfristig nicht reicht

Scenario ist hervorragend für echte Entscheidungs-/Kommunikations-
/Rechtsfälle (`anruf-am-empfang`: Schweigepflicht, `fhir-is-not-wado`:
Spezifikationsverständnis, `hl7-ack-trap`: ACK-Semantik) — dort gibt es
keine "fehlende Engine-Fähigkeit", sondern echtes Fachwissen, das durch
Multiple-Choice geprüft werden soll. Für **operative PACS-Diagnostik**
(Routing, Jobs, Multi-Hop-Transfer) ist Scenario aber strukturell eine
Krücke: der Lernende sammelt keine eigene Evidenz, er bekommt sie
portionsweise vorgelesen. Genau die im Auftrag beschriebene Ziel-
Erfahrung (`$ pacs jobs --study A94421`, dann `$ dcmdump`, dann
`$ pacs routes show`) ist mit einem Entscheidungsbaum nicht abbildbar,
weil die Scenario-Engine keinen Shell-/Objektbegriff hat (siehe 2.2).

## 4. Ziele / Nicht-Ziele

**Ziele** (siehe Auftrag, Abschnitt 4): generisch (keine
Node-spezifische Engine-Logik), deklarativ (`node.yml` beschreibt die
Umgebung, Engine interpretiert), deterministisch, lernorientiert (nicht
produktgetreu), DICOM-technisch korrekt, produktneutral, abwärts-
kompatibel, erweiterbar (Routing darf MPPS/Storage Commitment später
nicht blockieren).

**Nicht-Ziele** (siehe Auftrag, Abschnitt 23): kein echter PACS-Server,
keine `pynetdicom`-Vollimplementierung, kein Orthanc-Ersatz, keine
echte persistente Queue/Message-Broker, keine Hersteller-Syntax, keine
grafische Routing-Oberfläche, kein Event Sourcing, keine
Microservice-Zersplitterung, keine Ausdruckssprache/`eval()` für
Regeln.

## 5. Betrachtete Architekturvarianten

**Option A — `services/engine` direkt erweitern (alles in `rules.py`).**
Einfachste Variante, aber `rules.py` ist bereits ~900 Zeilen; Routing +
Jobs + Events würde die Datei weiter aufblähen und Testbarkeit/Review
erschweren. Kein neuer Service, keine neue Datenbank, kein neuer
`EngineClientResolver`-Zweig.

**Option B — eigener `services/pacs-engine`.** Nach dem Vorbild von
`services/scenario-engine`. Nachteil, konkret belegt durch die
Bestandsanalyse: `services/scenario-engine` **dupliziert** `schemas.py`,
`db.py`, `config.py`, `security.py`, `flag.py` fast wörtlich aus
`services/engine` (eigene Aussage im Code: mehrere Funktionen sind
"verbatim aus services/engine/app/rules.py übernommen"). Ein dritter
Service würde dieselbe Boilerplate ein drittes Mal duplizieren, eine
dritte Datenbanktabelle, ein drittes Docker-Image, einen dritten
`EngineClientResolver`-Zweig und eine neue `interaction`-Ausprägung
brauchen — für eine Fähigkeit, die inhaltlich eng mit der *bestehenden*
DICOM-Simulation zusammenhängt (Routing setzt auf denselben Hosts/
Objekten/Assoziationen auf, die `services/engine` bereits kennt). Das
wäre der teuerste, am wenigsten wiederverwendende Weg.

**Option C — Composable Subsystems innerhalb `services/engine`
(empfohlen).** Neue, eigene Module (`app/operations/routing.py`,
`app/operations/jobs.py`, `app/operations/events.py` oder eine
vergleichbare Aufteilung) neben dem bestehenden `app/find.py` —
`find.py` ist bereits der lebende Präzedenzfall genau für dieses
Muster (ADR 0011: eigenes, zustandsloses Modul statt Erweiterung von
`rules.py`, vom Dispatcher aus `rules.py` aufgerufen). `rules.py`
bekommt einen neuen Tool-Eintrag `pacs` in seinem bestehenden
`exec_command`-Dispatch (exakt wie `dcmdump`/`dcmftest` heute), der an
die neuen Module delegiert. Ein Service, eine Datenbank
(`engine_sessions`, weiterhin JSONB, keine Migration), ein Test-Setup,
keine neue `interaction`-Ausprägung, kein neuer
`EngineClientResolver`-Zweig.

**Option D — andere Architektur.** Geprüft und verworfen: (a) eine
generische Rule-Engine/Skriptsprache für Routing (verstößt gegen
Abschnitt 24, Sicherheitsmodell, und gegen KISS); (b) Event Sourcing
mit echtem Broker (verstößt gegen Abschnitt 21/23, unnötige
Infrastruktur für eine Lernplattform mit 10–100 simulierten Objekten
pro Node); (c) PACS Operations als Teil der Scenario-Engine (verworfen,
siehe 2.2 — dort gibt es keinen Shell-/Objektbegriff, der Umbau wäre
größer als eine Neuentwicklung in `services/engine`).

### Bewertung

| Kriterium | A (rules.py direkt) | B (neuer Service) | C (Module in engine) |
|---|---|---|---|
| Komplexität | niedrig kurzfristig, hoch langfristig (eine Datei wächst unbegrenzt) | hoch (3. Service, 3. DB-Tabelle, 3. Docker-Image, Boilerplate-Duplikat) | niedrig-mittel, klar geschnitten |
| Testbarkeit | sinkt mit Dateigröße | isoliert, aber redundant zu testender Code (Session-CRUD, Cache-Endpunkt, Flag-Hashing) erneut | isoliert pro Modul, teilt bestehende Test-Infrastruktur |
| State-Sharing | trivial (ein `state`-Dict) | keins — Routing bräuchte eigene Objektkenntnis, die schon in `services/engine` liegt | trivial (ein `state`-Dict, wie heute) |
| API-Kompatibilität | unverändert | neuer `EngineClientContract`-Client + Resolver-Zweig nötig | unverändert |
| Frontend-Auswirkung | keine (Terminal-first) | keine | keine (Terminal-first) |
| Node-Schema | additiv | additiv, aber zusätzlich neue `interaction`-Ausprägung nötig | additiv |
| Zukunftsfähigkeit (MPPS/Storage Commitment/IOCM) | ja, aber Datei wird unübersichtlich | ja, aber jede neue Fähigkeit dupliziert wieder Boilerplate | ja — weitere Module (`mpps.py`, `storage_commitment.py`) neben `routing.py`/`jobs.py` |
| Overengineering-Risiko | niedrig | hoch (Infrastruktur für eine noch unbewiesene Anforderung) | niedrig |

**Empfehlung: Option C.** Sie liefert dieselben Vorteile wie A (ein
Service, ein State-Modell, keine neue Infrastruktur), vermeidet aber
das mittelfristige Risiko einer unübersichtlichen `rules.py`, indem sie
demselben, bereits etablierten Muster folgt, das `find.py` seit P10.1
vorgibt. Sie vermeidet zugleich die reale, im Code sichtbare
Boilerplate-Verdopplung von Option B.

## 6. Capability-Matrix

| Capability | Heute vorhanden? | Wo implementiert? | State-changing? | Wiederverwendbar? |
|---|---|---|---|---|
| Host | Ja | `node.yml environment.hosts`, `content.py` | Nein (Definition) | Ja |
| DICOM Service (SCP) | Ja | `hosts[].services[]`, `rules.check_association` | Nein (Definition) | Ja |
| Association | Ja | `rules.check_association` | Ja (Counter) | Ja |
| C-ECHO | Ja | `rules._exec_echoscu` | Ja (Progress) | Ja |
| C-STORE | Ja | `rules._exec_storescu`/`trigger_action` | Ja (`bestand`) | Ja |
| C-FIND | Ja | `rules._exec_findscu`, `find.py` | Nein (read-only) | Ja |
| MWL | Ja | `find.find_worklist`, `environment.worklist` | Nein | Ja |
| Object Store | **Teilweise** | nur Aggregat-Zähler `bestand[host]`, keine adressierbare Objektliste pro Host | Ja | Teilweise — Kategorie A (siehe 8.3) |
| Query Records | Ja | `environment.records`, `find.find_studies/find_series` | Nein | Ja |
| Transfer Syntax Negotiation | Ja | `accepted_transfer_syntaxes`, `trigger_action` | Ja | Ja |
| SOP Class Negotiation | Ja | `accepted_sop_classes`, Result 3 vs. 4 | Ja | Ja |
| Editable Config | Ja | `hosts[].config` + `config_editable`, `set_config` | Ja | Ja — generischer Mechanismus (ADR 0013) |
| Job Queue | **Nein** | — | — | Kategorie B |
| Routing Rules | **Nein** | — | — | Kategorie B |
| Multi-Hop | **Nein** | Engine kennt nur Lernender/Modalität → ein Ziel | — | Kategorie B |
| MPPS | **Nein** | nur Prosa in `letztes-glied-fehlt` | — | Kategorie B, eigenes Service-Primitive |
| Storage Commitment | **Nein** | nur Prosa in `letztes-glied-fehlt` | — | Kategorie B |
| IOCM | **Nein** | — | — | Kategorie C vorerst (kein Node braucht es aktuell) |
| Multiframe-Metadaten | **Teilweise** | `objects[]` hat flache Felder, aber kein `NumberOfFrames`/Functional Groups | Nein | Kategorie A falls je gebraucht — bewusst nicht gebaut, nur ein Node (`nur-ein-bild`) bräuchte es |
| Generic Logs/Events | **Teilweise** | `ExecResult.events`/`ActionResult.events` existieren, werden aber nicht persistiert | Ja (transient) | Kategorie A — persistieren + erweitern |

## 7. Analyse bestehender Scenario-Nodes

Vollständige Inventur ergab **14 Nodes** mit `interaction: scenario`
(mehr als die drei namentlich genannten): `letztes-glied-fehlt`,
`nur-ein-bild`, `dosis-bleibt-liegen`, `falsch-gelesen`,
`name-ohne-schluessel`, `stored-but-invisible`, `anruf-am-empfang`,
`fhir-is-not-wado`, `hl7-ack-trap`, `hl7-order-gap`,
`modality-go-live`, `move-destination-unknown`, `oru-status-gap`,
`restore-or-retrieve`.

Nur `letztes-glied-fehlt` dokumentiert seine Scenario-Entscheidung im
`node.yml`-Kommentar selbst; die übrigen 13 tun das nicht (in `de.md`/
PR-Beschreibungen, nicht im YAML). Das ist selbst ein kleiner, separat
zu behebender Dokumentationsmangel (siehe "Risiken"), aber kein
Blocker für dieses ADR.

Klassifikation nach tatsächlichem Grund für `interaction: scenario`:

| Kategorie | Nodes | PACS-Operations würde helfen? |
|---|---|---|
| Fehlende DICOM-Metadaten-Inspektion (Photometric, Charset) | `falsch-gelesen`, `name-ohne-schluessel` | Nein — anderes Fähigkeitsfeld (Pixel-/Encoding-Inspektion, nicht Routing) |
| Fehlende Multiframe-Metadaten + Downstream-App-Simulation | `nur-ein-bild` | Nein — Multiframe/Functional-Groups ist ein eigenes Primitiv (Kategorie A, siehe Matrix), Downstream-App-Logik bleibt ohnehin Scenario-Territorium |
| Fehlende MPPS/Storage-Commitment-Primitive | `letztes-glied-fehlt` | Teilweise — Job/Event-Modell ist eine sinnvolle Grundlage, aber MPPS/N-ACTION/N-EVENT-REPORT selbst fehlen weiterhin (Kategorie B, eigenes Folge-ADR) |
| Fehlendes PACS-Routing/Job-Queue-Primitiv | `dosis-bleibt-liegen`, `move-destination-unknown` | **Ja, direkt** |
| Reine Entscheidungs-/Kommunikations-/Rechtsfälle | `anruf-am-empfang`, `fhir-is-not-wado`, `hl7-ack-trap`, `restore-or-retrieve` | Nein — kein fehlendes Engine-Primitiv, richtig bei Scenario |
| Fehlende HL7-/Interface-Engine-Routing-Simulation | `hl7-order-gap`, `oru-status-gap` | Nein direkt (anderes Protokoll/Domäne), aber **strukturell dasselbe Muster** (Regel-basierte Nachrichtenselektion) — mögliche spätere Analogie für ein HL7-Pendant, explizit außerhalb dieses ADRs |
| Multi-System-Akzeptanztest ohne einzelne fehlende Fähigkeit | `modality-go-live` | Teilweise — Routing/Query/MWL-Kombination könnte helfen, aber kein einzelnes fehlendes Primitiv |
| Fehlende SOP-Class-/Viewer-Capability-Simulation | `stored-but-invisible` | Nein — anderes Fähigkeitsfeld (Display-Capability, nicht Routing) |

**Wichtig**: nicht jede Scenario-Node soll migriert werden. Die
Klassifikation zeigt, dass ein generisches Routing/Job-Primitiv gezielt
zwei bis drei Nodes verbessern würde (`dosis-bleibt-liegen`,
`move-destination-unknown`, teilweise `modality-go-live`), während die
Mehrheit der 14 Nodes aus fachlich anderen Gründen bei Scenario bleiben
sollte.

## 8. Zielarchitektur / Domain Model

### 8.1 Minimal sinnvoller erster Scope (Phase 1)

Arbeitshypothese aus dem Auftrag bestätigt, mit einer Präzisierung:
Routing Rules + Routing Evaluation + Job Queue + Event-/Audit-Log +
Multi-Hop-Storage sind zusammen der kleinste Scope, der einen
deutlichen didaktischen Gewinn erzeugt — **aber** "Multi-Hop-Storage"
ist kein zusätzliches Primitiv, sondern die **automatische Folge**
davon, dass eine erfolgreiche Objektspeicherung an einem Host dessen
Routen auswertet (siehe 8.4). Es gibt also technisch nur **drei** neue
Bausteine, nicht fünf: Route-Definition + Matcher, Job-Modell,
Event-Log — Multi-Hop und "Routing Evaluation" ergeben sich aus deren
Zusammenspiel.

### 8.2 Match-Semantik (Abschnitt 7/8 des Auftrags)

**Empfehlung: Hybrid, benannte Attribute statt generischer Tags.**

```yaml
match:
  modality: CT                     # Kurzform, implizit "all", implizit "equals"
```

Für mehrere Bedingungen oder OR-Semantik:

```yaml
match:
  all:                             # UND-Verknüpfung
    - modality: CT
      not_equals: true             # (Beispielschreibweise, siehe unten)
match:
  any:                             # ODER-Verknüpfung
    - modality: CT
    - modality: MR
```

Erlaubte Operatoren (Abschnitt 24, kein `eval()`): `equals`,
`not_equals`, `in` (Liste), `exists`. Kein `matches`/Regex in Phase 1 —
nur nachziehen, falls ein konkreter Node es zwingend braucht.

Erlaubtes Attribut-Vokabular in Phase 1: `modality`, `sop_class`,
`study_description`, `series_description`, `source_ae` — bewusst
dieselben benannten Felder, die `environment.objects[]` und
`DCMDUMP_FIELD_ORDER` bereits kennen (siehe 8.5), **kein** paralleles
generisches Tag-Modell (`"0008,0060"`). Begründung: bessere Lesbarkeit
für Content-Autoren, keine zweite Repräsentation derselben Daten,
konsistent mit der bisherigen Repo-Konvention (ADR 0011/12/13 haben
wiederholt die einfachere, stilkonsistente Variante der roadmap-
vorgeschlagenen generischeren Variante vorgezogen). Ein generischer
`tag: "0008,0060"`-Fallback bleibt eine spätere, klar abgegrenzte
Erweiterung (Kategorie A), falls ein Node ein Attribut braucht, das
nicht in der Namensliste steht — nicht vorab bauen (YAGN
I).

Route-Reihenfolge/Priorität: Routen werden in Datei-Reihenfolge
ausgewertet; **mehrere Routen können gleichzeitig matchen** (ein
Objekt kann an mehrere Ziele gehen, wie in echten PACS-Setups) — es
gibt keinen "erster Treffer gewinnt"-Kurzschluss. `enabled: true|false`
(Standard `true`) erlaubt eine deaktivierte, aber sichtbare Route als
Distraktor. `priority` ist **kein** Phase-1-Feld — es gibt keinen
Konflikt zu lösen, solange mehrere Treffer erlaubt sind. Kein `retry`
in Phase 1 (siehe 8.6, Anti-Hintergrundverarbeitung).

Objekt- vs. Study-/Series-Routing: **Phase 1 routet pro Objekt**
(Instance-Ebene), ausgewertet exakt in dem Moment, in dem das Objekt
am Host ankommt. Study-/Series-weites Batch-Routing ("warte bis die
Study vollständig ist, dann route") ist eine spätere, bewusst
zurückgestellte Erweiterung (Kategorie B) — sie würde einen
"Vollständigkeits"-Zustand brauchen, der über die reine Job-/
Event-Frage hinausgeht.

### 8.3 Objektidentität & Object Store (Abschnitt 16)

**Neue notwendige Grundlage, die im Auftrag nicht explizit benannt,
aber für Routing zwingend ist**: `bestand[host]` ist heute nur ein
Zähler, keine adressierbare Liste. Routing muss aber wissen, *welches*
Objekt gerade an einem Host ankam, um Routen dagegen zu prüfen.

Empfehlung: ein neuer, additiver Session-State-Key
`stored_objects[host_name]: list[str]` (Liste von `filename`-Werten,
Referenz zurück in `environment.objects[]` für die vollständigen
Metadaten) wird bei jeder erfolgreichen Speicherung ergänzt. `bestand`
bleibt unverändert bestehen (Abwärtskompatibilität, nichts Bestehendes
liest `stored_objects`).

`filename` bleibt der primäre Identitätsschlüssel (wie heute überall
in `rules.py`) — **kein** Wechsel auf `SOPInstanceUID` als
Primärschlüssel, das wäre ein größerer, durch den didaktischen Bedarf
nicht gerechtfertigter Umbau. Empfehlung: `environment.objects[]`
bekommt ein neues, optionales Feld `sop_instance_uid`, damit Jobs/
Events echte SOP-Instance-UIDs in ihrer Ausgabe zeigen können, auch
wenn intern weiter per Dateiname referenziert wird.

### 8.4 Job-Queue-Domänenmodell (Abschnitt 9)

Zustände, bewusst minimal: `queued` → `sent` **oder** `failed`. Kein
`sending` (keine echte Nebenläufigkeit, siehe 8.6), kein `skipped` —
das ist der entscheidende Punkt aus Abschnitt 9 des Auftrags:

> Route matcht nicht → **kein Job entsteht**, nicht `status: skipped`.

Stattdessen entsteht für eine Nicht-Übereinstimmung ein reiner
Auswertungs-Eintrag im Event-Log (siehe 8.5), kein Job-Datensatz. Das
ist exakt die Semantik, die `dosis-bleibt-liegen` heute in Prosa
nachstellt ("0 queued" ≠ "fehlgeschlagen").

Job-Felder: `id` (`j-001`, sitzungslokal fortlaufend), `route_id`,
`source` (Host-Name), `destination` (Host-Name), `object` (Dateiname),
`attempt` (Ganzzahl, Default 1 — Wiederholung ist Phase 2), `status`,
`reason` (Freitext bei `failed`, z. B. `"abstract-syntax-not-supported"`
— **wiederverwendet dieselben echten PS3.8-Ablehnungsgründe**, die
`check_association`/`trigger_action` heute schon für lernenden-
initiierte Sendungen produzieren), `created_at`.

**Zentraler Architekturgewinn**: ein Job wird ausgeführt, indem exakt
dieselbe Assoziations-/Verhandlungslogik (`check_association`,
`accepted_sop_classes`, `accepted_transfer_syntaxes`) wiederverwendet
wird, die heute schon für lernenden-initiierte `storescu`/`send_study`-
Aufrufe existiert — nur dass der "Sender" diesmal der PACS-Host selbst
ist, nicht die Lernenden-Shell. Kein neuer Verhandlungscode nötig, nur
ein neuer Aufrufer.

### 8.5 Event-/Audit-Log (Abschnitt 10)

**Kein Event Sourcing.** Ein einfaches, deterministisches, anhängendes
Audit-Log in `state["events"]`, das dieselben Ereignistypen aufnimmt,
die `ExecResult`/`ActionResult` heute schon transient erzeugen
(`association_accepted`, `store_completed`, ...), **plus** neue Typen:
`route.evaluated` (mit `matched: bool` und bei `false` einem
Freitext-`reason`, z. B. `"Modality expected CT, actual SR"`),
`job.created`, `job.sent`, `job.failed`.

Notwendige, bisher fehlende Änderung: die bereits vorhandenen
`events`-Listen aus `ExecResult`/`ActionResult` werden erstmals **in
`state["events"]` persistiert** (heute gehen sie nach der einmaligen
API-Antwort verloren) — das ist eine kleine, additive Änderung an
`main.py`s Save-Pfad, kein Rewrite.

### 8.6 Keine Hintergrundverarbeitung (Abschnitt 21)

Routing-Auswertung und Job-Erzeugung/-Ausführung laufen **synchron
innerhalb desselben Requests**, der ein Objekt an einem Host ankommen
lässt (heute: `trigger_action`'s `send_study`-Pfad bzw. der direkte
`storescu`-Pfad in `exec_command`). Es gibt keinen Timer, keinen
Worker, keine Warteschlange im Sinne von Nebenläufigkeit — "Job Queue"
ist hier ein **Datenmodell** (eine Liste im `state`-Dict), kein
Laufzeit-Scheduler. `pacs route test <route> <objekt>` ist zusätzlich
ein **seiteneffektfreier** Trockenlauf (kein Job, kein Event außer
optional einem `route.evaluated`-Eintrag), damit Lernende Hypothesen
prüfen können, ohne den Sitzungszustand zu verändern.

### 8.7 State-Modell (Abschnitt 20)

Klare Grenze, wie im Auftrag gefordert:

- **Node-Definition** (unveränderlich, aus `node.yml`): `hosts[].routes[]`
  (Route-ID, Ziel, Match, `enabled`) — die Ausgangskonfiguration,
  exakt wie `accepted_sop_classes` heute.
- **Session-State** (veränderlich, JSONB): `stored_objects[host]`,
  `jobs[]`, `events[]` — alles, was während der Sitzung *entsteht*,
  nichts davon existiert vor dem ersten Store-Vorgang.
- Phase 1 hat **keine** lernenden-editierbaren Routen. Sollte Phase 2
  (siehe 8.8) Routen-Editing einführen, wird dafür der **bereits
  bestehende** `config`/`config_editable`-Mechanismus wiederverwendet
  (derselbe Ort, an dem heute `local_ae`/`transfer_syntax`/`sop_class`
  editierbar sind) statt eines neuen Mechanismus.

### 8.8 Editierbare Konfiguration & Lösungs-Mechanik (Abschnitt 13/14)

**Empfehlung für Phase 1: Variante 1 (Read-only-Diagnose, klassisches
Flag).** Variante 2 (Diagnose + tatsächliche Korrektur + automatischer
Solve-Zustand) ist didaktisch die interessantere Langfrist-Richtung,
aber sie berührt zwei Dinge gleichzeitig — Routen-Editing *und* die
Solve-/Flag-Architektur — und sollte deshalb eine eigene, spätere
Phase/ein eigenes Folge-ADR sein, kein Nebenprodukt dieses Audits.

Empfehlung für die spätere Solve-Erweiterung (nicht in diesem Auftrag
umzusetzen): ein optionales, additives `solve_condition:` auf
Node-Ebene, das **dieselben** Match-Operatoren wie das Routing
wiederverwendet (`all`/`any`/`equals`/`exists`/...), gegen den
aktuellen `state` ausgewertet nach jeder mutierenden Aktion. Ein Node
kann `flag` **oder** `solve_condition` **oder** beides deklarieren;
bestehende Nodes (nur `flag`) sind komplett unberührt. Das ist eine
naheliegende, aber bewusst nicht in diesem Schritt gebaute Erweiterung.

### 8.9 Multi-Hop (Abschnitt 15)

Heute kennt die Engine nur "Lernender/Modalität → ein Ziel". Der
vorgeschlagene Mechanismus (8.4) verallgemeinert das elegant: sobald
ein Host ein Objekt speichert, wertet er **automatisch** seine eigenen
`routes[]` gegen das neue Objekt aus. Empfängt ein zweiter Host
(z. B. "PACS B" oder ein Dose-System) das Objekt über einen
entstandenen Job, kann **derselbe Host** wiederum eigene `routes[]`
haben — Mehrfach-Hops komponieren rekursiv, ohne neues Konzept. Phase 1
begrenzt sich bewusst auf zwei Hops (Modalität/Workstation → PACS →
ein Downstream-Ziel) zur Scope-Kontrolle; 3+-Hop-Ketten sind mit
demselben Modell möglich, aber noch nicht mit Content hinterlegt.

### 8.10 DICOM-Metadaten-Modell (Abschnitt 17)

**Keine Big-Bang-Migration auf ein generisches Tag-Dict.** Der Matcher
liest dieselben benannten Felder, die `DCMDUMP_FIELD_ORDER` bereits als
kanonisches Vokabular führt (`modality`, `sop_class`, ...) — eine
Erweiterung dieser einen Liste, keine zweite parallele Repräsentation.

## 9. Content-Schema-Konzept

### 9.1 `node.yml`-Erweiterung (Skizze, keine finale Syntax)

```yaml
environment:
  hosts:
    - name: pacs
      ip: 10.20.0.10
      services:
        - port: 104
          type: scp
          ae_title: RAD-ARCHIV
          accepted_sop_classes: [...]
      routes:
        - id: CT-TO-DOSE
          destination: dose-scp     # muss ein Host im selben environment sein
          enabled: true
          match:
            modality: CT

    - name: dose-scp
      ip: 10.20.0.30
      services:
        - port: 104
          type: scp
          ae_title: DOSE-SCP
          accepted_sop_classes: ["1.2.840.10008.5.1.4.1.1.88.67"]
```

`routes[]` gehört bewusst zum Host, der sie ausführt (Eigentümerschaft
folgt der realen PACS-Rolle), nicht zu einem neuen Top-Level-Key.

### 9.2 CLI-Konzept (Abschnitt 11/12)

Ein Namespace, `pacs`, deklariert wie jedes andere Werkzeug in
`environment.tools: [pacs, dcmdump, storescu]` — **keine** neue
Sonderprüfung "ist dieses Tool erlaubt", der bestehende
`tool not in node.tools`-Mechanismus in `exec_command` greift
unverändert.

```text
$ pacs studies
$ pacs study show A94421
$ pacs objects --study A94421
$ pacs routes list
$ pacs routes show CT-TO-DOSE
$ pacs route test CT-TO-DOSE rdsr-001.dcm
$ pacs jobs [--study A94421] [--route CT-TO-DOSE]
$ pacs events [--study A94421]
```

Parser: kein neues Framework — derselbe `shlex.split`-Ansatz wie
heute, ein kleiner handgeschriebener Dispatcher auf `args[0]`
(Unterbefehl) analog zu `_parse_dcmtk_args`. `dcmdump`/`echoscu`/
`storescu`/`findscu` bleiben unverändert die "echten" DICOM-Werkzeuge;
`pacs` ist **ausdrücklich** ein DCMLab-eigenes Simulationswerkzeug —
diese Klarstellung gehört in die `tools/de.yml`-Beschreibung von
`pacs` (dort, wo Tool-Beschreibungen ohnehin schon für die
Lektions-Werkzeugleiste gepflegt werden) und in jede Lesson/jeden Node,
der `pacs` erstmals einführt.

### 9.3 Validator-Erweiterung (Abschnitt 19)

Neue `ContentValidator`-Prüfungen (heute komplett fehlend für
`hosts`/`services`, siehe 2.3) — Schema-/Referenzfehler, **nie**
fachliche Korrektheit:

- `route.destination` muss ein existierender Host-Name im selben
  `environment.hosts` sein.
- Route-IDs eindeutig innerhalb eines Nodes.
- `match`-Schlüssel müssen aus dem erlaubten Attribut-Vokabular
  stammen (Tippfehler-Schutz, z. B. `moddality` wird abgelehnt).
- `match`-Operator muss aus `equals|not_equals|in|exists` stammen.
- Ziel-Host muss mindestens einen `services`-Eintrag besitzen
  (Unerreichbarkeit wird über falschen Port/AE simuliert, nicht über
  einen Host ohne jeden Service).

**Ausdrücklich nicht geprüft**: ob eine Regel fachlich sinnvoll ist.
`match: {modality: CT}` bleibt syntaktisch gültig, selbst wenn genau
das die eingebaute Root Cause eines Nodes ist — der Validator repariert
die Lernaufgabe nicht.

## 10. Migrations-/Kompatibilitätsstrategie

- Bestehende Nodes ohne `routes:`/`jobs:`/`events:` verhalten sich
  exakt wie heute — `stored_objects`/`jobs`/`events` bleiben leer,
  `bestand`/Zähler-Semantik unverändert, keine Verhaltensänderung an
  C-ECHO/storescu/findscu/SOP-Class-/Transfer-Syntax-Verhandlung.
- Keine Laravel-Migration nötig (siehe 2.3 — `environment`/`scenario`
  waren nie in der DB).
- Kein neuer `interaction`-Wert, kein neuer `EngineClientResolver`-
  Zweig — Option C bleibt vollständig innerhalb `interaction: terminal`.
- Regressionsstrategie: `tests/test_real_content.py`-Muster
  (Bestandscontent gegen das Regelwerk, keine Sonderfälle) bleibt die
  Richtschnur — jeder neue Test für `pacs`/Routing läuft zusätzlich
  gegen echte, ausgelieferte Nodes, sobald welche existieren.

## 11. Teststrategie

- **Unit** (`services/engine/tests/test_routing.py`,
  `test_jobs.py`, neu, nach demselben Muster wie
  `test_transfer_syntax.py`/`test_abstract_syntax.py`): Matcher pro
  Operator, `all`/`any`, Kein-Match-erzeugt-keinen-Job, Job-Erzeugung,
  Zielauflösung, Zustandsübergänge `queued→sent`/`queued→failed`.
- **Engine-API**: `pacs`-Unterbefehle deterministisch (gleicher State +
  gleicher Command → gleiche Ausgabe), Sitzungspersistenz über
  `state["jobs"]`/`state["events"]`.
- **Content**: Schema-Validierung (fehlendes Ziel, doppelte Route-ID,
  unbekanntes Attribut, unbekanntes Objekt in einer Job-Seed-Angabe,
  falls es sowas geben sollte).
- **Regression**: komplette bestehende Engine-Testsuite unverändert
  grün (heute 18 Testdateien, siehe Bestandsanalyse) — kein Verhalten
  an C-ECHO/storescu/findscu/SOP-Class-Negotiation/Config darf sich
  ändern.
- **E2E**: ein Beispiel-Node über
  `Laravel → EngineClientResolver → services/engine → pacs-Kommando →
  Session-State → Node-UI`, analog zum bereits etablierten Muster
  (siehe `dosis-bleibt-liegen`s Live-Verifikation in diesem Repo).

## 12. Ausbaupfad (Abschnitt 22)

Keine Sackgasse für die genannten künftigen Fähigkeiten:

- **MPPS**: eigenes Geschwister-Modul (`app/operations/mpps.py`),
  eigener State-Key (`state["mpps"]`), neue `action`-Werte
  (`n_create`, `n_set`) im bestehenden `trigger_action`-Dispatch.
- **Storage Commitment**: analog, `state["commitments"]`,
  `n_action`/`n_event_report` als neue `action`-Werte; das Job-/
  Event-Modell aus diesem ADR ist bereits die richtige Grundlage für
  "Anfrage gestellt, Antwort ausstehend/da".
  `letztes-glied-fehlt` wäre der natürliche spätere Migrationskandidat,
  **aber erst**, wenn dieses Folge-Primitiv existiert — Phase 1 dieses
  ADRs bringt diesem Node noch keinen Mehrwert (siehe 13.3).
- **IOCM**: Objekt-Status-Übergänge (`active|rejected|replaced`) als
  Erweiterung des Objekt-Dicts + neuer Event-Typ — passt ins Modell.
- **Multiframe**: orthogonale Metadaten-Erweiterung der Objekt-Felder
  (Kategorie A, siehe Matrix), unabhängig von Routing.
- **AI/Downstream-Verarbeitung**: strukturell **identisch** zum
  bereits vorgeschlagenen Job-Modell (ein Job zu einem KI-System ist
  technisch derselbe Fall wie einer zu einem Dose-System) — keine
  weitere Architekturarbeit nötig, nur neue Content.

## 13. Beispiel-Redesigns (hypothetisch, keine Implementierung)

### 13.1 `dosis-bleibt-liegen`

```text
$ pacs study show A94421
Study A94421
  CT Image Storage                  60
  X-Ray Radiation Dose SR Storage    1

$ pacs jobs --study A94421
ROUTE        OBJECT-TYPE                    STATUS
CT-TO-DOSE   CT Image Storage (60 objects)  60 sent

$ pacs objects --study A94421
...
rdsr-001.dcm

$ dcmdump rdsr-001.dcm
(0008,0016) UI =XRayRadiationDoseSRStorage
(0008,0060) CS [SR]

$ pacs routes show CT-TO-DOSE
destination: DOSE-SCP
match:
  modality: CT

$ pacs route test CT-TO-DOSE rdsr-001.dcm
matched: false
condition: modality
expected: CT
actual: SR
```

Würde den didaktischen Wert dieses Nodes deutlich steigern (echte
Evidenzsammlung statt vorgelesener Logs) — ein plausibler
Migrationskandidat für Phase E, **nicht** in diesem Auftrag umgesetzt.

### 13.2 `nur-ein-bild`

Multiframe-Metadaten (`NumberOfFrames`, Functional Groups) sind ein
eigenes, in diesem ADR bewusst nicht gebautes Primitiv (Kategorie A,
aber nur für einen einzigen Node bisher gebraucht). Ohne dieses
Primitiv würde eine Migration nur den Routing-Teil (nicht vorhanden)
verbessern, aber den eigentlichen Kern des Nodes (Frame-Zählung)
unverändert lassen. **Empfehlung: bleibt vorerst Scenario.**

### 13.3 `letztes-glied-fehlt`

Routing/Jobs allein lösen nicht das Kernproblem (MPPS + Storage
Commitment fehlen als Primitive vollständig). **Phase 1 dieses ADRs
bringt diesem Node noch keinen ausreichenden Mehrwert** — kein Scope
Creep in Richtung MPPS in diesem Schritt.

## 14. Sicherheitsmodell (Abschnitt 24)

Keine beliebige Code-Ausführung über Content. Erlaubte
Match-Operatoren sind eine feste, kleine Enum (`equals`, `not_equals`,
`in`, `exists`), niemals ein ausgewerteter Ausdruck. Kein `eval()`,
keine Python-/JS-Fragmente aus YAML. Ein `matches`-Regex-Operator wäre
höchstens eine spätere, sorgfältig geprüfte Ergänzung, kein
Phase-1-Bestandteil.

## 15. Risiken / offene Fragen

- 13 von 14 bestehenden Scenario-Nodes dokumentieren ihre
  Scenario-Entscheidung nicht im `node.yml`-Kommentar (nur
  `letztes-glied-fehlt` tut das) — unabhängig von diesem ADR
  empfehlenswert nachzuziehen, damit künftige Audits nicht wieder auf
  Inferenz angewiesen sind.
- Die HL7-Routing-Nodes (`hl7-order-gap`, `oru-status-gap`) zeigen ein
  strukturell verwandtes Muster (regelbasierte Nachrichtenselektion)
  in einer anderen Domäne — ob sich das vorgeschlagene Match-/Job-
  Modell später generisch genug für ein HL7-Pendant gestalten lässt,
  ist eine bewusst offene, nicht in diesem ADR zu klärende Frage.
- Mehrfach-Treffer-Semantik (mehrere Routen matchen dasselbe Objekt)
  ist fachlich plausibel, aber noch nicht gegen ein reales
  Lab-Szenario mit mehreren Zielen durchgespielt — sollte im ersten
  Prototyp-Node explizit getestet werden.
- Die genaue Platzierung von `routes[]` (am Host vs. eigener
  Top-Level-Schlüssel) ist eine Content-Autoren-Ergonomie-Frage, die
  im ersten Implementierungs-Prototyp final entschieden werden sollte,
  nicht rein architektonisch.

## 16. Finale Bewertung

**A — Lohnt sich die Advanced PACS Operations Sandbox jetzt?**
**Teilweise.** Der Bedarf ist real und durch drei unabhängig
entstandene Scenario-Nodes belegt (kein hypothetisches Problem). Die
Architektur passt sauber in die bestehende Engine, ohne Big Bang
(Option C, additiv, keine Migration). Aber: es gibt aktuell nur **zwei**
klare, sofort profitierende Kandidaten (`dosis-bleibt-liegen`,
`move-destination-unknown`), nicht ein ganzes Bündel — der
Implementierungsaufwand (Phase A–C) sollte deshalb gegen einen
konkreten neuen Prototyp-Node (Phase D) gerechtfertigt werden, nicht
gegen eine spekulative Menge künftiger Nodes. Empfehlung: **Phase A–D
jetzt freigeben, Phase E (Migration) separat und pro Node einzeln
entscheiden.**

**B — Kleinstes sinnvolles MVP?**
Routing (benannte Attribute, `all`/`any`, vier Operatoren) + Job-
Erzeugung bei Objekt-Ankunft (Wiederverwendung der bestehenden
Assoziations-/Verhandlungslogik) + persistiertes Event-Log + drei
`pacs`-Unterbefehle (`routes`, `jobs`, `events`) plus `route test`.
Kein Editing, kein automatischer Solve, kein `objects`/`studies`-
Komfortbefehl über das hinaus, was zur Diagnose eines Routing-Falls
nötig ist.

**C — Welche bestehenden Scenario-Nodes profitieren unmittelbar?**
`dosis-bleibt-liegen` (Routing-Selektionsfehler, exakt der
Zielfall) und `move-destination-unknown` (C-MOVE-Zweitassoziation zu
falschem Port — strukturell dieselbe "Ziel-Registrierung stimmt nicht"-
Familie). `modality-go-live` profitiert teilweise (Routing/Query/MWL-
Kombination), hat aber keinen einzelnen fehlenden Kern-Primitiv.

**D — Welche bleiben besser Scenario?**
Alle reinen Entscheidungs-/Kommunikations-/Rechtsfälle
(`anruf-am-empfang`, `fhir-is-not-wado`, `hl7-ack-trap`,
`restore-or-retrieve`), alle Fälle mit fehlenden, hier nicht gebauten
Primitiven (`letztes-glied-fehlt`: MPPS/Storage Commitment,
`nur-ein-bild`: Multiframe-Metadaten, `falsch-gelesen`/
`name-ohne-schluessel`: Pixel-/Encoding-Inspektion,
`stored-but-invisible`: Display-Capability) und die HL7-Routing-Fälle
(`hl7-order-gap`, `oru-status-gap` — andere Domäne, kein DICOM-Objekt-
Routing).

**E — Welche neuen PACS-Labs werden dadurch möglich?** (Ideen, keine
fertigen Nodes)
- Route matcht falsche SOP Class (Admin filtert auf `sop_class` einer
  veralteten UID nach einem IOD-Wechsel, z. B. klassisch → Enhanced).
- Route zeigt auf ein falsch konfiguriertes Ziel (falscher AE-Title
  oder Port an der Destination selbst, nicht am Router).
- Fehlende Route (ein neuer Objekttyp/eine neue Modalität wurde nie in
  eine bestehende Route aufgenommen — "niemand hat daran gedacht").
- Failed Job durch abgelehnte SOP Class/Transfer Syntax am zweiten Hop
  (Job entsteht, `route.evaluated: true`, aber `job.status: failed`
  mit echtem PS3.8-Result-Code) — deutlich von "kein Job" abgegrenzt.
- Objekt vorhanden, aber nie weitergeleitet, weil die Route
  `enabled: false` ist (menschlicher Fehler bei einer Wartungsarbeit,
  nie reaktiviert).
- Mehrere Destinations für dieselbe Route-Bedingung, eine davon
  fehlkonfiguriert (Teilausfall: Ziel A bekommt alles, Ziel B nichts).
- Drei-Hop-Kette (Modalität → PACS → Router → KI-System), bei der der
  zweite Hop funktioniert, der dritte aber an einer eigenen,
  unabhängigen Route scheitert — bewusste Erweiterung über Phase-1s
  Zwei-Hop-Grenze hinaus, sobald sie gebraucht wird.

## 17. Entscheidungspunkte

1. Zustimmung zu **Option C** (Composable Subsystems in
   `services/engine`) als Zielarchitektur.
2. Zustimmung zum Phase-1-Scope (Routing + Jobs + Events, **kein**
   Editing, **kein** automatischer State-based Solve).
3. Zustimmung zur Match-Semantik (benannte Attribute, `all`/`any`,
   vier Operatoren, kein generisches Tag-Matching in Phase 1).
4. Zustimmung, `dosis-bleibt-liegen` und `move-destination-unknown`
   als Migrationskandidaten für eine spätere Phase E vorzumerken, ohne
   sie jetzt anzufassen.
5. Freigabe für ein erstes Implementierungs-ADR/PR (Phase A gemäß
   Abschnitt 17 des Berichts), sobald gewünscht — **nicht Teil dieses
   Auftrags**.

## 18. Implementierungsphasen (Vorschlag, nach Repo-Audit verfeinert)

- **Phase A — Domain Foundation**: `app/operations/routing.py`
  (Match-Evaluator, reine Funktionen), `stored_objects`-State-Key,
  Unit-Tests. Kein CLI, kein Job-Modell.
- **Phase B — Job/Event-State**: Job-Erzeugung bei Objekt-Ankunft
  (Wiederverwendung von `check_association`), `state["events"]`-
  Persistenz (inkl. bestehender Event-Typen), Unit-/API-Tests.
- **Phase C — CLI**: `pacs`-Tool-Dispatch in `rules.exec_command`,
  `studies|objects|routes|jobs|events`-Unterbefehle,
  Parser-/Format-Tests.
- **Phase D — Erster Prototyp-Node**: ein **neuer** Terminal-Lab, das
  Routing/Jobs demonstriert — ausdrücklich **kein** Migrieren
  bestehender Nodes in dieser Phase.
- **Phase E — Migrationsbewertung**: `dosis-bleibt-liegen`,
  `move-destination-unknown` erneut prüfen, ob eine Migration jetzt
  echten didaktischen Mehrwert bringt; Entscheidung pro Node einzeln,
  kein automatisches "alles migrieren".

Kein Big Bang: Scenario bleibt für alle 14 heutigen Nodes vollständig
nutzbar, unabhängig vom Fortschritt dieser Phasen.
