# Offene Fragen

Entscheidungen aus dem Ausbau zum Lightweight LMS (`dcm-lab-lms-agent-prompt.md`),
die der Betreiber trifft, nicht der Code-Agent. Format: Frage, Kontext,
Empfehlung. Erledigte Punkte werden hier durchgestrichen, nicht geloescht.

## `content/` ist in `infra/docker-compose.yml` fuer `app` read-only gemountet -- Freigeben schlaegt im Standard-Dev-Setup fehl

**Frage:** Wie soll ein Reviewer eine Einreichung lokal tatsaechlich
freigeben koennen (`ContentVersionController::publish()` -> `ContentWriter`
schreibt nach `content/`), wenn `../content:/var/www/html/content:ro` den
Schreibzugriff im Container grundsaetzlich verbietet?

**Kontext:** Entdeckt beim Testen des neuen Autoren-Panels (ADR 0092): ein
"Freigeben"-Klick scheitert mit einem 500er
(`file_put_contents(...): Failed to open stream: Read-only file system`).
Das betrifft nicht das Panel selbst, sondern jeden bestehenden Editor
(Lektion/Quiz/Pruefung/Achievement) gleichermassen -- der Mount ist in
`infra/docker-compose.yml` fuer `app`, `engine`, `sandbox`,
`scenario-engine` fest auf `:ro` gesetzt, `infra/docker-compose.dev.yml`
ueberschreibt das nicht. Vermutlich eine bewusste Sicherheitsmassnahme
(ein laufender Container soll den git-verfolgten Content nicht versehentlich
korrumpieren), aber `docs/betrieb.md` beschreibt keinen alternativen Weg,
wie eine echte Freigabe dann tatsaechlich content/ aendert.

**Empfehlung:** Klaeren, ob es einen separaten, schreibbaren Deploy-/
Operator-Pfad gibt (der dann dokumentiert gehoert), oder ob
`docker-compose.dev.yml` fuer lokale Entwicklung einen schreibbaren
Bind-Mount fuer `content/` braucht, analog zu anderen dev-spezifischen
Overrides dort.

## ~~`SandboxSession` fehlt noch -- `state`/`exec`/`destroy` loesen fest auf "docker" auf (CMS-2b)~~ Erledigt

**Umgesetzt (15.09.2026, ADR 0097):** `sandbox_sessions` ist jetzt eine
durable Aufzeichnung jeder Spielwiesen-Sitzung; `SandboxController::create()`
schreibt eine Zeile, `state()`/`exec()`/`destroy()` schlagen
`runtime_provider` ueber `runtime_instance_id` nach, statt ihn zu raten.
"docker" bleibt nur noch Fallback fuer sandbox_ids ohne bekannte Sitzung
(z. B. vor dieser Migration entstanden). Der Live-Zustand bleibt weiterhin
primaer in services/sandbox (Redis/Docker) -- diese Tabelle ist Historie/
Audit, keine zweite Wahrheit.

## Bestehende Pruefungsfragen auf `ref` umstellen (nach W5)

**Frage:** Sollen einzelne der 40 bestehenden Pruefungsfragen, die
inhaltlich mit einer Lektionsfrage uebereinstimmen, auf `ref` umgestellt
werden, um Doppelpflege zu vermeiden?

**Kontext:** ADR 0079 (W5) baut die technische Moeglichkeit
(`ref: {lesson, question}` in `exam.yml`), stellt aber keine reale
Pruefungsfrage automatisch um -- ob eine Pruefungsfrage wirklich wortgleich
mit einer Lektionsfrage ist (und nicht nur thematisch verwandt, mit
bewusst anderer Formulierung oder Schwierigkeit), ist eine inhaltliche
Einschaetzung.

**Empfehlung:** Nur umstellen, wo eine Pruefungsfrage tatsaechlich 1:1
eine Lektionsfrage dupliziert (identischer Text, identische Antwort) --
das laesst sich am ehesten beim naechsten redaktionellen Durchgang durch
`content/exams/` erkennen, nicht automatisiert.

## ~~Pionier-System (first_blood/track_badges) in Achievements migrieren (nach W4)~~ Erledigt

**Erledigt (ADR 0091, 15.09.2026):** Der Betreiber hat die sieben dafuer
noetigen Bild-Assets geliefert (1254×1254 PNG, Stil der sechs
bestehenden Badges: ein globales "Trailblazer"-Abzeichen plus eins je
Track). `first_blood` und `track_badges` sind jetzt echte
`achievement_definitions`-Eintraege (`trailblazer`, `track-<slug>`) in
`content/achievements.yml`, ueber die bereits vorhandene, aber bis dahin
nie mit echtem Content ausgeuebte `scope`/`unlock_when`-Infrastruktur aus
ADR 0077. Dabei einen echten, seit der urspruenglichen
achievement_unlocks-Migration bestehenden Bug gefunden und behoben: eine
zu weit gefasste Unique-Constraint verhinderte, dass derselbe Nutzer ein
global-scoped Achievement auf einer zweiten Aktivitaet gewinnen konnte
(siehe ADR 0091 fuer Details).

## Alt-Tabellen `achievements`/`track_badges` droppen (nach ADR 0091)

**Frage:** Wann koennen die beiden Alt-Tabellen des migrierten
Pionier-Systems (siehe ADR 0091) physisch entfernt werden?

**Kontext:** ADR 0091 migriert die LESE-/SCHREIB-Pfade vollstaendig auf
`achievement_unlocks`, laesst die beiden Alt-Tabellen aber bewusst
bestehen: `php artisan achievements:migrate-pionier` (der Backfill-Befehl)
muss in jeder echten Umgebung mit Bestandsdaten manuell ausgefuehrt und
bestaetigt werden, bevor ihre Quelldaten geloescht werden duerfen -- ein
automatisches `migrate --force` beim Deploy darf diesem Schritt nicht
zuvorkommen.

**Empfehlung:** Sobald der Backfill in jeder relevanten Umgebung (Dev,
jede weitere reale Instanz) bestaetigt gelaufen ist, eine kleine
Folge-Migration ergaenzen, die `achievements` und `track_badges`
droppt, und `App\Models\Achievement`/`App\Models\TrackBadge` entfernen
(im Anwendungscode bereits seit ADR 0091 ungenutzt).

## ~~`status: fertig` im Bestand normalisieren (vor jeder Sichtbarkeits-Gate, W3+)~~ -- erledigt

~~**Frage:** Soll der reale Content-Bestand von `status: fertig` (25
Lektionen/Nodes) auf das dokumentierte `status: published`
(`docs/content-schema.md`) umgestellt werden?~~

**Umgesetzt (15.09.2026, ADR 0086):** Alle 59 betroffenen Dateien (25
`fertig`, 34 `draft`) einzeln geprueft (Zeilenzahl, Geruest-Marker,
Abgleich mit `docs/content-todo.md`s eigenen "✅ fertig"-Vermerken) und
auf `published` gesetzt -- keine blieb `draft`, jede der 34 Dateien war
tatsaechlich fertiggestellter, nur nie umgestellter Lernstoff.
`content:validate` bleibt frei von Verstoessen. Siehe ADR 0086 fuer die
volle Beweisfuehrung je Kategorie.

## ~~`README.md`/`docs/content-todo.md` sind an mehreren Stellen veraltet (Nebenbefund aus ADR 0086)~~ Erledigt

**Erledigt (15.09.2026):** `README.md` ("Was funktioniert"/"Bekannte
Luecken") ist gegen den echten `content/`-Bestand abgeglichen: alle
sechs Tracks (nicht drei) sind vollstaendig veroeffentlicht, es gibt 17
statt 10 spielbare Node-Definitionen. `docs/content-todo.md`s
Abschnittsueberschriften fuer Track 2, 4 und 5 sind von "Geruest,
Fliesstext fehlt" auf "vollstaendig geschrieben (seit PXX)"
korrigiert -- denselben Abschluss, den ihr eigener Fliesstext schon
dokumentierte, nur die Ueberschrift war stehen geblieben.
`docs/content-schema.md` Abschnitt 10 ("Nachzuziehen") ist als erledigt
markiert: die Werkzeugbeispiele in 1.1/1.5 sind laengst vorhanden.

## ~~Cache-Invalidierung bei Engine/Sandbox nach einer Veroeffentlichung (W2)~~ -- erledigt

~~**Frage:** Sollen `services/engine`, `services/scenario-engine` und
`services/sandbox` einen Endpunkt bekommen, den `ContentWriter` nach jedem
Schreiben aufruft, um ihren `lru_cache`-Inhalt sofort zu invalidieren?~~

**Umgesetzt (15.09.2026, ADR 0085):** Alle drei Dienste haben jetzt
`POST /internal/cache/clear` (abgesichert mit `X-DCMLAB-KEY`).
`App\Content\HttpCacheInvalidator` ruft alle drei parallel auf und loggt
Fehlschlaege, statt zu werfen. Gebunden ueberall ausser in der
Testumgebung (dort weiterhin `NullCacheInvalidator`, sonst haette jeder
Editor-Publish-Test drei echte, unerreichbare HTTP-Verbindungsversuche
ausgeloest -- siehe ADR 0085 fuer die Messung). Nebenbefund: nur
engine (`datasets.yml`) und sandbox (`datasets.yml`+`worklists.yml`)
cachen ueberhaupt etwas; scenario-engine liest `content/` schon immer
frisch, bekam den Endpunkt trotzdem fuer eine einheitliche Schnittstelle.

## ~~Pruefung je Track oder freier (vor W6.3)~~ -- entschieden: eine je Track

~~**Frage:** Bleibt `exams/<track>/` bei genau einer Pruefung pro Track, oder
sollen modul-/trackuebergreifende Pruefungen moeglich werden?~~

**Entscheidung (15.09.2026):** Bei genau einer Pruefung pro Track
belassen -- der Pruefungs-Editor (W6.3, ADR 0082) ist bereits 1:1 auf
`Track` gebaut, und Konzept-Abschnitt 4 (selbstgesteuertes Lernen) nennt
keinen konkreten Bedarf fuer mehrere Pruefungen je Track. Keine
Code-Aenderung noetig.

## ~~Herkunft von `authors` beim Import (vor W2/W3)~~ -- erledigt

~~**Frage:** Loest der Bestandscontent-Freitext in `authors` (z. B. `"ley338"`)
beim einmaligen Import auf ein echtes Nutzerkonto auf, oder bleibt er als
Historie stehen?~~

**Umgesetzt (15.09.2026):** `lessons.authors`/`activities.authors` in
`legacy_authors` umbenannt (Migration, Modelle, `ContentSync`, Factories) --
strukturell unmissverstaendlich als reine Historie markiert, nie als
Berechtigungsquelle. Es gab keinen Code, der versucht hat, den Freitext auf
ein Nutzerkonto aufzuloesen; die echte Rechtepruefung lief immer schon
ausschliesslich ueber `activity_authors`/`Activity::authorUsers()` (ADR
0071, W3). Kein automatisches Matching hinzugefuegt, wie empfohlen.

## Engine-Modul je Themenfeld (nicht Teil dieses Auftrags)

**Frage:** Wann bekommt ein Themenfeld ausserhalb DICOM (z. B. das
Datenschutz-PoC-Modul aus P10.69) einen eigenen `services/*-engine`-Dienst?

**Kontext:** `EngineClientContract`/`EngineClientResolver` sind seit P10.68
dafuer vorbereitet (siehe `docs/adr/0071-...md` Abschnitt "Offene Punkte").

**Empfehlung:** Erst nach W0-W7, wenn der Aktivitaetsvertrag und die
Autorenschicht stehen -- ein zweites Engine-Modul jetzt zu bauen wuerde
gegen einen sich noch aendernden Vertrag entwickeln.

## Lizenzmodell, Betriebskosten und Kontingente der Spielwiese

**Frage:** Unveraendert offen, siehe `dcm-lab-agent-prompt.md` und
`dcm-lab-lms-agent-prompt.md` Abschnitt 8. Kein neuer Befund in diesem
Ausbau.

## ~~Scheduler-Betrieb fuer review:send-reminders (nach W7)~~ -- erledigt

~~**Frage:** Wie soll `php artisan review:send-reminders` (und jeder
kuenftige geplante Befehl) im Produktivbetrieb tatsaechlich taeglich
ausgefuehrt werden?~~

**Umgesetzt (15.09.2026, ADR 0087):** `APP_KEY` kommt jetzt zentral aus
der Root-`.env` (Pflichtvariable, `docker-compose.yml` bricht mit
klarer Meldung ab, falls sie fehlt) statt pro Container generiert zu
werden. `entrypoint.sh` fuehrt die `web-public`-Neubefuellung nur noch
fuer das Kommando `php-fpm` aus, Migrationen laufen mit `--isolated`
(Sperre ueber `CACHE_STORE=redis`). Ein neuer `scheduler`-Service
(`php artisan schedule:work`, kein `web-public`-Mount) startet mit
`make up` automatisch mit. Smoke-getestet gegen die laufende lokale
Compose-Umgebung, siehe ADR 0087.

## ~~Bild-Upload im Achievement-Editor (nach W6.4)~~ -- erledigt

~~**Frage:** Wann bekommt der Achievement-Editor einen echten Datei-Upload
fuer `image`, statt eines Freitextfelds fuer einen bereits vorhandenen
Dateinamen unter `public/images/achievements/`?~~

**Umgesetzt (15.09.2026, ADR 0088):** `POST author/achievements/{slug}/
edit/image` schreibt sofort und direkt nach `public/images/achievements/`,
ausserhalb der `content_versions`-Transaktion (wie empfohlen). Der
Dateiname wird immer aus dem URL-Slug erzeugt, nie aus dem
Client-Dateinamen (Schutz vor Pfad-Traversal); `image`-Validierung
(echter Bildinhalt, `mimes:png,jpg,jpeg,webp`, `max:512` KB) plus
`throttle:10,1`.

## ~~Fragenpool-Verwaltung im Pruefungs-Editor (nach W6.3)~~ Umgesetzt

**Umgesetzt (ADR 0090, 15.09.2026):** Der Pruefungs-Editor kann Fragen im
Pool jetzt vollstaendig hinzufuegen, bearbeiten und entfernen, inklusive
`ref`-Erstellung mit Lektion/Frage-Dropdown und einem Anker-Dropdown aus
den Ueberschriften der Ziellektion. `ExamQuestionGenerator` ersetzt bei
jeder Aenderung den GESAMTEN `questions:`-Block in `exam.yml` und alle
`### fNN`-Abschnitte in `de.md` als Einheit (die Poolstatistik ist eine
Eigenschaft der ganzen Liste, siehe ADR 0090/0082); `coverage()` rechnet
jetzt live gegen den Entwurf im Formular, nicht nur gegen den
gespeicherten Bestand. Ein neuer Pruefungspool ist damit vollstaendig ueber
den Editor pflegbar, ohne Kommandozeile.

## ~~`sandbox`/`lab`/`objectives` im Lektions-Editor (nach W6.2)~~ -- erledigt

~~**Frage:** Wann bekommen die Sandbox-/Lab-Verknuepfung (verschachtelte
YAML-Bloecke in `meta.yml`) und die Lernziele (`objectives`, mehrzeilige
Liste im Frontmatter von `de.md`) einen eigenen Bearbeitungsbaustein im
Lektions-Editor?~~

**Umgesetzt (15.09.2026, ADR 0089):** `LessonMetaGenerator::
regenerateSandbox()`/`regenerateLab()` und ein dritter Zweig in
`regenerateFrontMatter()` fuer `objectives` -- dasselbe chirurgische
Block-Muster wie `LessonQuizGenerator`s `quiz:`-Block, verallgemeinert.
`objectives_count` wird nie eigenstaendig entgegengenommen, sondern
immer aus der Listenlaenge von `objectives` abgeleitet (schliesst einen
Mismatch strukturell aus). `sandbox.dataset`/`lab.node` sind im Editor
Dropdowns aus dem echten Bestand, keine Freitextfelder.
