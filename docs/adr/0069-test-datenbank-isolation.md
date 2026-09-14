# ADR 0069: Test-Datenbank-Isolation

## Status

Angenommen (16.09.2026).

## Kontext

Waehrend der Verifikation einer nicht damit zusammenhaengenden Aenderung
(P10.67, Lesson-/Track-/Node-AppShell) lief `./vendor/bin/pest` versehentlich
innerhalb des `app`-Containers statt auf dem Host. `phpunit.xml` setzt zwar
`DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:`, aber PHPUnits `<env>`-Eintraege
ohne `force="true"` ueberschreiben eine bereits im Prozess gesetzte
Umgebungsvariable nicht -- und `DB_CONNECTION=pgsql` (Zeigt auf die echte
Dev-Datenbank `dcmlab`) ist im `app`-Container immer schon gesetzt
(`infra/docker-compose.yml` exportiert es via `env_file`, bevor PHP ueberhaupt
startet). `RefreshDatabase` hat daraufhin `migrate:fresh` gegen die echte
Datenbank ausgefuehrt und sie geleert.

Dieselbe Vorfallklasse existierte latent auch bei der Node-Engine
(`services/engine`): `tests/conftest.py` setzte `ENGINE_DATABASE_URL` nur per
`os.environ.setdefault(...)` -- ebenfalls wirkungslos, wenn die Variable schon
(vom `engine`-Container) gesetzt ist. `tests/test_api.py::setup_function()`
ruft vor jedem Test `Base.metadata.drop_all(bind=engine)` -- waere das gegen
die echte Datenbank gelaufen, waere der Effekt derselbe gewesen.

## Root Cause (Detail)

Zwei unabhaengige Luecken mussten beide geschlossen werden:

1. **PHPUnit `<env>` ohne `force="true"`** ist ein No-Op, wenn die Variable
   bereits eine echte Prozessvariable ist (`PHPUnit\TextUI\Configuration\PhpHandler::handleEnvVariables()`).
2. **`force="true"` allein reicht nicht**: PHPUnits `PhpHandler` schreibt bei
   `force` zwar `putenv()` und `$_ENV`, aber **nie `$_SERVER`** -- und
   Laravels `env()`-Aufloesung liest auch `$_SERVER`, das die PHP-CLI-SAPI
   schon beim Prozessstart aus der echten Umgebung befuellt hat. Ohne
   zusaetzlichen Abgleich haette `$_SERVER['DB_CONNECTION'] = 'pgsql'`
   weiterhin durchgeschlagen, selbst mit `force="true"`. Siehe
   `tests/TestCase::syncForcedEnvIntoServerSuperglobal()`.

## Entscheidung

Mehrschichtige Verteidigung, keine einzelne Massnahme allein:

1. **`apps/web/phpunit.xml`**: jeder `<env>`-Eintrag traegt `force="true"`.
   Bleibt bei SQLite `:memory:` als Default -- schnell, keine Infrastruktur,
   kann per Definition nie die echte Postgres-DB sein.
2. **`Tests\TestCase::createApplication()`** (fruehester Punkt vor jedem
   Test, noch vor `RefreshDatabase`): gleicht `$_ENV` nach `$_SERVER` ab
   (behebt Luecke 2), dann `Tests\Support\DatabaseSafety::assertTestingEnvironment()`
   und `::assertDatabaseIsTestDatabase()` -- harter Abbruch (fail closed),
   wenn `APP_ENV` nicht `testing` ist oder der Datenbankname kein `_test`
   enthaelt bzw. der bekannten Dev-Datenbank (`dcmlab`) entspricht. Laeuft
   vor jedem einzelnen Test, unabhaengig davon, ob RefreshDatabase benutzt
   wird.
3. **`apps/web/phpunit.pgsql.xml`** (optional, explizit): fuer Postgres-
   Paritaetstests (z. B. `uuid`-Spaltentypen, die SQLite nicht durchsetzt --
   genau das hat die urspruenglichen NodeController-Tests im Docker-Vorfall
   unsichtbar gemacht) gegen eine eigene Datenbank `dcmlab_test`. Aufruf nur
   explizit per `--configuration`, nie automatisch. Kein `.env.testing`,
   weil `phpunit.xml`s `force="true"` es ohnehin unerreichbar machen wuerde
   (dieselbe Praezedenz gilt fuer beide Konfigurationsdateien).
4. **`services/engine/tests/conftest.py`**: `ENGINE_DATABASE_URL` wird per
   Zuweisung erzwungen (nicht `setdefault`), unmittelbar gefolgt von
   `tests/database_safety.py::assert_database_is_test_database()` als
   zweiter, unabhaengiger Sperre.
5. **Datenbank-Namenskonvention** (siehe README "Tests sicher ausfuehren"):

   | Zweck | Name |
   |---|---|
   | Laravel, Entwicklung | `dcmlab` |
   | Laravel, Tests (optional, Postgres-Paritaet) | `dcmlab_test` |
   | Laravel, Tests (Standard) | SQLite `:memory:` |
   | Engine, Entwicklung | `dcmlab` (dieselbe DB, andere Tabelle: `engine_sessions`) |
   | Engine, Tests (Standard) | SQLite `:memory:` |

   Die Engine hat aktuell **keine eigene** Entwicklungsdatenbank -- sie
   teilt sich `dcmlab` mit Laravel (siehe `app/db.py`-Kommentar: "lebt in
   derselben Datenbank wie Laravel"). `dcmlab_engine`/`dcmlab_engine_test`
   sind deshalb als *zukuenftige* Namen in `DatabaseSafety`/`database_safety.py`
   auf der Sperrliste vorgemerkt, falls die Engine je eine eigene Datenbank
   bekommt -- nicht, weil sie heute existieren.

## Konsequenzen

- Ein Test-Lauf innerhalb eines laufenden Dev-Containers (versehentlich oder
  absichtlich) kann die echte Datenbank nicht mehr veraendern: entweder
  greift `force="true"` + `$_SERVER`-Abgleich und die Suite laeuft gegen
  SQLite, oder -- falls das je durch eine zukuenftige Aenderung umgangen wird
  -- `DatabaseSafety` bricht hart ab, bevor `RefreshDatabase`/`drop_all`
  irgendetwas anfasst.
- Regressionstests fuer die Sperre selbst existieren auf beiden Seiten
  (`tests/Unit/Support/DatabaseSafetyTest.php`, `tests/test_database_safety.py`)
  und laufen bei jedem `pest`/`pytest`-Aufruf mit.
- Verifiziert (16.09.2026): `./vendor/bin/pest` und `python -m pytest`
  jeweils *mit unveraendert geerbten* Container-Umgebungsvariablen
  (`DB_CONNECTION=pgsql` bzw. `ENGINE_DATABASE_URL` auf die echte DB
  zeigend) ausgefuehrt -- beide Suiten liefen vollstaendig gegen
  SQLite `:memory:`, die echte Dev-Datenbank (Track-/Node-/User-Zahlen,
  `engine_sessions`-Zeilenzahl, ein platzierter Canary-Datensatz) blieb
  unveraendert.
