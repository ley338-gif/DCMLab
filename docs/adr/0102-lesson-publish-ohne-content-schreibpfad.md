# 0102 — Lektions-Freigabe ohne content/-Schreibpfad (CMS-5b)

## Status

Angenommen, 15.09.2026.

## Kontext

ADR 0101 (CMS-5a) machte den **Lesepfad** einer Lektion DB-basiert
(`LessonController::show()` bevorzugt die DB). Der **Schreibpfad** blieb
unveraendert: `ContentVersionController::publish()` rief fuer jede
Aktivitaet unbedingt `ContentWriter::write()` auf -- eine Lektionsfeld-
Freigabe schrieb weiterhin `meta.yml`/`de.md`, rief synchron
`content:sync` und `ContentBuilder::build()` auf und war der Pfad, der
unter dem `:ro`-Docker-Mount lokal mit 500 scheitert.

Betreiberentscheidung fuer diese Phase: `content/` darf waehrend
normaler Speichern-/Review-/Freigabe-Vorgaenge nicht mehr beschrieben
werden. DB und die unveraenderlichen `content_versions` sind Quelle der
Wahrheit und Historie; `content/` bleibt nur noch ein explizites,
deterministisches Import-/Export-/Seed-Format. Zusaetzliche Vorgaben:
veroeffentlichte Revisionen sind unveraenderlich, ein "Wiederherstellen"
erzeugt eine neue Revision statt eine bestehende zu mutieren, und kein
bestehender Content darf durch die Migration verloren gehen.

## Entscheidung

**Dispatch statt Immer-Datei.** Neue Klasse
`ActivityContentApplier::apply(Activity $activity, array $payload)`:
validiert wie bisher (`ActivityContract::validate($payload)`, keine
zweite, schwaechere Pruefung), dann -- nur fuer `type=lesson` **ohne**
`quiz`-Schluessel im Payload -- `LessonContentPublisher::publish()`
statt `ContentWriter::write()`. Jeder andere Fall (Quiz-Entwurf, weiterhin
an seine Lektion gebunden, ADR 0097; Pruefung; Achievement; Node) faellt
unveraendert auf `ContentWriter` zurueck, bis auch deren Fliesstext
DB-gefuehrt ist (CMS-6/CMS-8) -- der Discriminator `isset($payload['quiz'])`
ist derselbe, den `LessonActivity::serialize()` intern schon verwendet.
`ContentVersionController::publish()` ruft nur noch `ActivityContentApplier`
auf, nicht mehr direkt `ContentWriter`/`ActivityRegistry`.

**`LessonContentPublisher`**: schreibt jedes Feld direkt in die
`lessons`-Zeile (`title`, `teaser`, `level`, `duration_minutes`, `tools`,
`requires`, `glossary_terms`, `objectives`/`objectives_count`, `sandbox`,
`lab`, `body`) -- kein `meta.yml`/`de.md`, kein `content:sync`, keine
Cache-Invalidierung. Ein bestehender Quiz-Abschnitt in `body` (samt
Fussnote danach) bleibt unangetastet erhalten, exakt wie zuvor bei
`LessonActivity::serialize()`, nur auf der DB-Spalte statt auf Dateitext
angewendet -- **einfacher**, nicht nur verschoben: kein chirurgisches
YAML-/Frontmatter-Patchen mehr noetig, ein Eloquent-`update()` aendert
ohnehin nur die uebergebenen Felder. Der zugehoerige `activities`-
Verzeichniseintrag (`title`/`teaser`/`source_hash`) wird im selben
Schritt mitgepflegt -- vorher Aufgabe von `content:sync`, das dieser Pfad
bewusst nicht mehr aufruft.

**Unveraenderlichkeit und Wiederherstellung.**
`ContentVersioningService::rollback()` erzeugt jetzt eine **neue**
`ContentVersion`-Zeile mit demselben `payload` wie die vorherige
veroeffentlichte Version, statt `is_current`/`status` auf einer
bestehenden Zeile zu mutieren -- jede einmal veroeffentlichte Version
bleibt mit ihrem `payload`, `status` und `published_at` fuer immer
unveraendert; nur `is_current` bewegt sich als reiner Zeiger (das
schreibt keine Historie um). `rollback()` bekommt einen `User $performedBy`
-Parameter fuer `reviewed_by`; `created_by` der wiederhergestellten
Version bleibt die urspruengliche Urheberschaft.

`rollback()` bleibt bewusst reine Buchfuehrung (wie schon vor dieser
ADR dokumentiert, ADR 0075) -- es gibt heute keine Route/UI, die es
aufruft. Das tatsaechliche Zurueckschreiben eines wiederhergestellten
`payload` auf die Lektion (via `ActivityContentApplier`) ist Aufgabe
eines kuenftigen Aufrufers (z. B. einer Studio-"Wiederherstellen"-
Aktion), der beide Schritte kombiniert -- kein bestehender Anwendungsfall
zwingt dazu, das jetzt schon zu verdrahten (siehe
`docs/offene-fragen.md`).

**Kein Datenverlust.** Alle 42 real existierenden Lektionen haben ihren
`body`/`objectives` bereits seit ADR 0101 (CMS-5a) in der DB, `content/`
bleibt unveraendert auf der Platte liegen -- diese ADR aendert nur den
kuenftigen Schreibpfad, keine bestehenden Daten.

## Konsequenzen

- `content:export` existiert weiterhin nicht -- `content/` ist damit
  vorerst nur noch "das, was zuletzt via `content:sync` importiert
  wurde", nicht mehr "das, was ein deterministischer Export gerade
  erzeugen wuerde". Ein echter `content:export`-Befehl ist ein eigenes,
  nicht triviales Vorhaben (muss dieselbe YAML-/Markdown-Formatierung
  reproduzieren, die bisher `ContentWriter`/die Generatoren
  uebernommen haben) und bewusst nicht Teil dieser ADR -- als offene
  Frage nachverfolgt.
- Quiz-Freigaben (weiterhin an ihre Lektion gebunden) schreiben
  weiterhin nach `content/` -- unter dem `:ro`-Docker-Mount also
  weiterhin fehlschlagend, wie schon vor dieser ADR (unveraendertes,
  separat dokumentiertes Verhalten).
- `docs/offene-fragen.md` bekommt zwei neue Eintraege: `content:export`
  fehlt noch; `rollback()`s Wiederherstellung ist noch nicht mit einer
  echten Studio-Aktion verdrahtet, die den `payload` auch tatsaechlich
  zurueckschreibt.

## Verifikation

- Alle 476 Tests (6 neu: `LessonContentPublisherTest`,
  `ActivityContentApplierTest`; `LessonEditorControllerTest`s
  Freigabe-Test und `ContentVersioningServiceTest`s Rollback-Test
  vollstaendig neu gefasst), PHPStan Level 7 und `pint --test` sind
  gruen.
- `LessonEditorControllerTest` beweist explizit: `content/lessons/1.0/
  {meta.yml,de.md}` bleiben nach einer vollstaendigen Freigabe
  byte-identisch zum Ausgangszustand.
