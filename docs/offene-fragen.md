# Offene Fragen

Entscheidungen aus dem Ausbau zum Lightweight LMS (`dcm-lab-lms-agent-prompt.md`),
die der Betreiber trifft, nicht der Code-Agent. Format: Frage, Kontext,
Empfehlung. Erledigte Punkte werden hier durchgestrichen, nicht geloescht.

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

## Pionier-System (first_blood/track_badges) in Achievements migrieren (nach W4)

**Frage:** Sollen `achievements` (global first_blood) und `track_badges` zu
echten `achievement_definitions`/`achievement_unlocks`-Einträgen werden,
statt als eigenes, bild-freies "Pionier"-Lesemodell bestehen zu bleiben?

**Kontext:** ADR 0070 hat eine Schema-Vereinheitlichung geprüft und
verworfen, u. a. weil Pionier bewusst ohne Badge-Bildsprache lebt. W4 (ADR
0077) hat das deklarative Vergabesystem (`scope`, `unlock_when`) gebaut,
aber die Pionier-Daten selbst nicht migriert — jede migrierte Zeile bräuchte
ein Bild-Asset unter `public/images/achievements/`, das es fuer "wer hat
diese Node zuerst gelöst" oder "Track X bestanden" nie gab.

**Empfehlung:** Nur migrieren, wenn jemand tatsächlich Kartenoptik für diese
beiden Mechaniken will (Bild, Rarity, Kategorie) — dann sind sechs
(fuer bestehende Tracks) plus eine globale Definition zu gestalten, danach
ist die Datenmigration selbst mechanisch (siehe `ContentVersioningService`-
und `ContentValidator`-Extraktionen dieser Session fuer das Muster: Service
zuerst, dann Daten). Ohne neuen Bildbedarf: Pionier bleibt, wie es ist,
kein technischer Nachteil dadurch.

## `status: fertig` im Bestand normalisieren (vor jeder Sichtbarkeits-Gate, W3+)

**Frage:** Soll der reale Content-Bestand von `status: fertig` (25
Lektionen/Nodes) auf das dokumentierte `status: published`
(`docs/content-schema.md`) umgestellt werden?

**Kontext:** ADR 0075 hat beim Umsetzen von W3 festgestellt: kein einziger
realer Datensatz unter `content/` traegt `status: published`. 34 tragen
`status: draft`, 25 das nirgends dokumentierte `status: fertig`. Solange das
so ist, kann `status` nicht sicher fuer Lernenden-Sichtbarkeit ausgewertet
werden -- jede Umstellung wuerde den kompletten heutigen Lernstoff
verbergen. `ContentVersioningService::isPublished()` (W3) umgeht das
Problem bewusst, indem Bestandscontent ohne Versionshistorie als sichtbar
gilt, aber das ist ein Provisorium, kein Ersatz fuer saubere Daten.

**Empfehlung:** `fertig` in den 25 betroffenen `meta.yml`/`node.yml`-Dateien
auf `published` umstellen (reine Werteaenderung, keine Strukturaenderung),
die 34 `draft`-Faelle einzeln pruefen, ob sie tatsaechlich unfertig sind
oder nur nie umgestellt wurden. Das ist eine redaktionelle Entscheidung
ueber echten Lernstoff und sollte in einem eigenen, review-baren Commit
geschehen, nicht als Nebeneffekt eines Agentenlaufs.

## Cache-Invalidierung bei Engine/Sandbox nach einer Veroeffentlichung (W2)

**Frage:** Sollen `services/engine`, `services/scenario-engine` und
`services/sandbox` einen Endpunkt bekommen, den `ContentWriter` nach jedem
Schreiben aufruft, um ihren `lru_cache`-Inhalt sofort zu invalidieren?

**Kontext:** `ContentWriter` (ADR 0071/0074) haengt hinter
`CacheInvalidatorContract` aktuell `NullCacheInvalidator` (No-op) ein. Die
drei Python-Dienste cachen `content/` pro Prozess und lesen es erst beim
naechsten Neustart neu ein -- eine frisch veroeffentlichte Lektion/Node
erscheint dort also erst nach einem Neustart/Deploy des jeweiligen Dienstes,
nicht sofort.

**Empfehlung:** Einen einfachen `POST /internal/cache/clear`-Endpunkt in
allen drei Diensten ergaenzen (loescht den `lru_cache`), abgesichert mit
demselben `X-DCMLAB-KEY`-Header wie die bestehende Engine-Anbindung.
`HttpCacheInvalidator implements CacheInvalidatorContract` ruft alle drei
parallel auf und loggt, statt zu werfen, wenn einer nicht erreichbar ist --
eine kurzzeitig veraltete Engine ist kein Grund, eine Veroeffentlichung
abzubrechen. Aufwand: klein, aber dienstuebergreifend (drei FastAPI-Apps),
deshalb bewusst nicht im selben Schritt wie `ContentWriter` erledigt.

## Pruefung je Track oder freier (vor W6.3)

**Frage:** Bleibt `exams/<track>/` bei genau einer Pruefung pro Track, oder
sollen modul-/trackuebergreifende Pruefungen moeglich werden?

**Kontext:** Der heutige Aufbau (ein `exam.yml` je Track-Slug) ist im
Aktivitaetsvertrag als `ExamActivity` 1:1 auf `Track` abgebildet. Eine
Erweiterung auf mehrere Pruefungen pro Track oder trackuebergreifende
Pruefungen ist nach dem Pruefungs-Editor (W6.3) teurer als davor.

**Empfehlung:** Bei genau einer Pruefung pro Track bleiben, solange kein
konkreter fachlicher Bedarf fuer mehrere vorliegt -- Konzept-Abschnitt 4
(selbstgesteuertes Lernen) nennt keinen.

## Herkunft von `authors` beim Import (vor W2/W3)

**Frage:** Loest der Bestandscontent-Freitext in `authors` (z. B. `"ley338"`)
beim einmaligen Import auf ein echtes Nutzerkonto auf, oder bleibt er als
Historie stehen?

**Kontext:** ADR 0071 macht `authors` zu einer echten Beziehung auf `users`
(W3). Der heutige Bestand traegt dort Freitext ohne Kontobezug.

**Empfehlung:** Freitext als Historie in einem zusaetzlichen Feld
(`legacy_authors` o. ae.) belassen und `authors` beim Import leer lassen,
statt zu raten, welches Konto gemeint ist -- eine falsche automatische
Zuordnung waere schlechter als keine.

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

## Scheduler-Betrieb fuer review:send-reminders (nach W7)

**Frage:** Wie soll `php artisan review:send-reminders` (und jeder
kuenftige geplante Befehl) im Produktivbetrieb tatsaechlich taeglich
ausgefuehrt werden?

**Kontext:** ADR 0084 (W7) registriert den Befehl in
`bootstrap/app.php` (`withSchedule()`), aber `infra/docker-compose.yml`
fuehrt aktuell keinen Scheduler- oder Queue-Worker-Prozess aus -- der
`app`-Container startet ausschliesslich `php-fpm`. Ein zweiter Container
auf demselben Image (`php artisan schedule:work`) ist der naheliegende
Weg, wuerde aber zwei bestehende Annahmen von `docker/entrypoint.sh`
sichtbar machen: `APP_KEY` wird derzeit PRO CONTAINER beim ersten Start
lokal generiert (`.env.docker` laesst ihn absichtlich leer) statt zentral
gesetzt -- ein zweiter Container bekaeme einen anderen Schluessel als
`app`; ausserdem leert/befuellt der Entrypoint bei jedem Start das
geteilte `web-public`-Volume neu, was zwei gleichzeitig startende
Container in einen Race liefe.

**Empfehlung:** Vor dem produktiven Einsatz von `review:send-reminders`
entscheiden: (a) `APP_KEY` zentral setzen (z. B. ueber die zentrale
`.env`, nicht `.env.docker`) statt ihn pro Container generieren zu
lassen, (b) `entrypoint.sh` um eine Bedingung erweitern, die die
Volume-Befuellung nur fuer den Web-Container ausfuehrt, oder (c) einen
eigenen, schlankeren Entrypoint fuer einen Scheduler-Container schreiben.
Bis dahin laesst sich der Befehl von Hand oder ueber Host-Cron
(`docker compose exec app php artisan review:send-reminders`) anstossen.

## Bild-Upload im Achievement-Editor (nach W6.4)

**Frage:** Wann bekommt der Achievement-Editor einen echten Datei-Upload
fuer `image`, statt eines Freitextfelds fuer einen bereits vorhandenen
Dateinamen unter `public/images/achievements/`?

**Kontext:** ADR 0083 (W6.4) haelt `image` bewusst als Freitextfeld --
ein Upload braucht einen eigenen, sicherheitsgeprueften Endpunkt
(Mime-/Groessenpruefung, Bildverarbeitung) ausserhalb der
`content_versions`-Transaktion, weil Bilder unter `public/` liegen, nicht
unter `content/`, und `ContentWriter` ausschliesslich Text unter
`content/` schreibt. Ein Achievement-Bild ist damit auch nie Teil des
versionierten Review/Freigabe-Kreislaufs gewesen (kein Rollback fuer
Bilder).

**Empfehlung:** Als eigene, sicherheitsfokussierte Erweiterung angehen,
mit eigener Validierung (erlaubte Formate, maximale Groesse) statt sie an
`ContentVersioningService::createDraft()`/`ContentWriter::write()`
anzuhaengen, die fuer Text-Content ausgelegt sind.

## Fragenpool-Verwaltung im Pruefungs-Editor (nach W6.3)

**Frage:** Wann bekommt der Pruefungs-Editor die Faehigkeit, einzelne
Fragen im Pool hinzuzufuegen, zu bearbeiten oder zu entfernen (inklusive
`ref`-Erstellung und einem Anker-Dropdown aus den Ueberschriften der
Ziellektion), statt nur die Einstellungen zu pflegen?

**Kontext:** ADR 0082 (W6.3) liefert bewusst nur die Einstellungen
(Titel, Bestehensgrenze, Fragenzahl je Versuch, Dauer, Mischen,
`min_per_lesson`) plus eine live berechnete Pool-Uebersicht
(Lektionsabdeckung, Cross-Anzahl, Typmischung, Schwierigkeitsanteil) als
Fortschrittsanzeige. Der eigentliche Fragenpool bleibt Kommandozeilen-
Sache, weil `ContentValidator::checkExamStructure()` globale Quoten ueber
den GESAMTEN Pool durchsetzt (Typmischung 35-40/20-25/20-25/10-15%,
mindestens 25% difficulty 3, mindestens 4 Fragen je Lektion, mindestens 4
Cross-Fragen) -- eine einzelne Frage hinzuzufuegen oder zu entfernen
verschiebt fast immer mindestens eine dieser Quoten, weshalb ein
Einzelformular wie bei Quiz-/Lektionsfragen hier nicht passt. Ein echter
Pool-Editor braucht ein UI, das dieselbe Statistik *waehrend* der
Bearbeitung gegen den Entwurf nachfuehrt, nicht nur gegen den Ist-Zustand.

**Empfehlung:** Als eigene, sorgfaeltig getestete Erweiterung angehen,
nicht nachtraeglich in W6.3 hineingezogen, mit derselben
`coverage()`-Berechnung aus `ExamEditorController`, aber gegen den
laufenden Entwurf statt gegen den bestehenden Pool.

## `sandbox`/`lab`/`objectives` im Lektions-Editor (nach W6.2)

**Frage:** Wann bekommen die Sandbox-/Lab-Verknuepfung (verschachtelte
YAML-Bloecke in `meta.yml`) und die Lernziele (`objectives`, mehrzeilige
Liste im Frontmatter von `de.md`) einen eigenen Bearbeitungsbaustein im
Lektions-Editor?

**Kontext:** ADR 0081 (W6.2) beschraenkt den Lektions-Editor bewusst auf
Felder, die als einzelne Zeile ersetzbar sind (`LessonMetaGenerator`).
`sandbox`/`lab` sind verschachtelte Strukturen, `objectives` ist eine
mehrzeilige Liste -- beide brauchen einen eigenen UI-Baustein (Struktur-
bzw. Listeneditor) statt eines Zeilenersatzes.

**Empfehlung:** Erst angehen, wenn ein konkreter Autoren-Workflow das
braucht -- bis dahin bleibt die Kommandozeile der Weg fuer diese drei
Felder, alles andere an einer Lektion ist ueber den Editor pflegbar.
