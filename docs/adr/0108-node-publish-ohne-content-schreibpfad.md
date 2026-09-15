# 0108 — Node-Freigabe ohne content/-Schreibpfad (CMS-6d, Teil 2)

## Status

Angenommen, 15.09.2026.

## Kontext

ADR 0107 (CMS-6d Teil 1) machte den **Lesepfad** einer Node DB-basiert
(`NodeActivity::deserialize()`, `NodeController::show()`/`useHint()`/
`viewWriteUp()` bevorzugen die DB). Der **Schreibpfad** blieb
unveraendert und war der eigentliche Kern des Betreiberauftrags fuer
CMS-6d: `NodeActivity::serialize($draft)` ignorierte `$draft`
vollstaendig -- Node war der einzige Aktivitaetstyp, bei dem selbst die
Grundlage fuer einen echten Autoren-Entwurf fehlte, und
`ActivityContentApplier` liess jeden Node-Entwurf unveraendert auf
`ContentWriter::write()` (also `content/nodes/**`) fallen.

Betreiberauftrag: Node-Entwuerfe muessen am selben
`content_versions`-Kreislauf teilnehmen wie Lesson/Quiz (ADR 0101/0102/
0104), Freigabe darf nicht nach `content/nodes/**` schreiben, und
Runtime-/Sicherheitsparameter (Sandbox-Template, Engine-Konfiguration,
Flag-Validierung) bleiben ausdruecklich ausserhalb des Autorenmodells
(ADR 0107, Node Content vs. Node Runtime).

## Entscheidung

**`serialize($draft)` wertet den Entwurf jetzt tatsaechlich aus** --
analog zu `LessonActivity::serialize($draft)`. Neue Klasse
`NodeMetaGenerator` (Gegenstueck zu `LessonMetaGenerator`) ersetzt
chirurgisch nur die genannten Felder in `node.yml`
(`difficulty`/`points`/`category`/`interaction`/`estimated_minutes`/
`skills`/`related_lessons`) und der Frontmatter von `de.md`
(`title`/`scenario_title`); `regenerateHints()` ersetzt den
`hints:`-Block nach demselben Block-Muster wie `LessonQuizGenerator`s
`quiz:`-Block -- nur `id`/`cost`, der Hint-**Text** selbst steckt bereits
im Body (`### h<n>`-Abschnitte, ADR 0107) und wird ueber das `body`-Feld
mitgeaendert. `environment:`/`flag:` fasst `NodeMetaGenerator` nie an --
dieselbe Trennung wie in ADR 0107, jetzt auch im Schreibpfad
durchgesetzt, nicht nur im Lesepfad.

`validate($draft)` baut wie `LessonActivity::validate($draft)` einen
synthetischen `nodes[$slug]`-Eintrag aus `serialize($draft)` statt vom
Ist-Zustand zu lesen -- derselbe `ContentValidator`-Regelsatz sieht
keinen Unterschied zwischen einem geparsten Datei-Bestand und dem, was
der Entwurf erzeugen wuerde.

**`NodeContentPublisher`** (Gegenstueck zu `LessonContentPublisher`):
schreibt jedes Feld direkt in die `nodes`-Zeile (`title`,
`scenario_title`, `difficulty`, `points`, `category`, `interaction`,
`estimated_minutes`, `skills`, `related_lessons`, `hints`, `body`) --
kein `node.yml`/`de.md`, kein `content:sync`. Anders als bei Lesson/Quiz
gibt es keinen zweiten, an einen Schluessel im Payload gekoppelten
Publisher: Hints (nur `id`/`cost`) sind Teil desselben Entwurfs wie jedes
andere Node-Feld, nicht an eine eigene `content_versions`-Zeile
gebunden. `environment`/`flag` sind nie Teil des Payloads und werden
folgerichtig auch hier nicht angefasst. Der zugehoerige
`activities`-Verzeichniseintrag (`title`/`source_hash`) wird im selben
Schritt mitgepflegt.

`ActivityContentApplier::apply()` bekommt einen dritten Zweig:
`type=node` geht jetzt ueber `NodeContentPublisher`, nicht mehr ueber
`ContentWriter`. `NodeActivity::supports()->versionable` wechselt von
`false` auf `true` -- echte Draft-Teilnahme, nicht nur der Vertrag.

**Bewusst nicht Teil dieser ADR** (weitere CMS-6d-Teile): `/studio/nodes`
(Uebersicht, Anlegen, Bearbeiten, Draft/Review/Publish, Preview,
Archive/Restore, Duplicate) existiert noch nicht -- diese ADR liefert
nur die Publisher-/Draft-Infrastruktur, die ein solcher Editor braucht.
Hints als eigenstaendig editierbarer didaktischer Inhalt im Studio-Editor
(separates Formularfeld fuer Text + Punktabzug statt Teil des rohen
`body`) folgt mit dem Editor selbst. `themenfeld` (Platzierung, analog zu
`track_id` bei Lesson) ist bewusst kein Draft-Feld -- wie bei Lesson
bleibt die strukturelle Zuordnung ausserhalb des Content-Entwurfs.
Sichtbarkeit einer Studio-erzeugten Node im Lesson-Composer-Katalog
bleibt ebenfalls offen.

## Konsequenzen

- Ein Node-Entwurf schreibt nach Freigabe nicht mehr nach
  `content/nodes/**` -- lokal also nicht mehr vom `:ro`-Docker-Mount
  betroffen, sobald ein Editor tatsaechlich Entwuerfe erzeugt.
- `authorView()` bleibt weiterhin von keinem Controller aufgerufen
  (bestaetigt per Grep ueber `app/`) -- reiner Formular-Hinweis fuer
  einen kuenftigen Editor, wie bei jedem anderen Aktivitaetstyp.
- `docs/offene-fragen.md` unveraendert: `content:export` fehlt weiterhin;
  `/studio/nodes` ist jetzt der naechste, nicht mehr durch fehlende
  DB-Infrastruktur blockierte Schritt.

## Verifikation

- Alle 499 Tests (9 neu: draft-bewusstes `serialize()`/`validate()`/
  erweitertes `deserialize()` in `NodeActivityTest`,
  `NodeContentPublisherTest`, ein Node-Freigabe-Test in
  `ActivityContentApplierTest`), PHPStan Level 7 und `pint --test` sind
  gruen.
- `ActivityContentApplierTest` beweist explizit:
  `content/nodes/test-node/de.md` bleibt nach einer vollstaendigen
  Node-Freigabe byte-identisch zum Ausgangszustand.
