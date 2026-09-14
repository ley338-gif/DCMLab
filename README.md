# DCM Lab

Lernplattform für DICOM und PACS. Arbeitsstand 16.09.2026 — alle Phasen P0
bis P9 aus `dcm-lab-agent-prompt.md` (Abschnitt 10) umgesetzt.

## Aufbau

```
apps/web/      Laravel 13 + Inertia + Vue 3 + Tailwind — die Webapp
services/      FastAPI: Node-Engine (Simulation) und Sandbox-Orchestrator
containers/    Images fuer die Spielwiese (Orthanc, Toolbox)
infra/         docker-compose, Caddy — ein `docker compose up` fuer alles
docs/          Konzept, Schemas, Ablaufpläne, Betrieb, ADRs
content/       Der Lernstoff im verbindlichen Repo-Format (content-schema.md)
datasets/build/ Erzeugt synthetische CT-Testobjekte fuer die Spielwiese
prototyp/      Die spielbare Node "Silent CT" als einzelne HTML-Datei (P0)
```

## Wo anfangen

| Du willst … | Lies |
|---|---|
| die Plattform starten | Abschnitt "Starten" unten |
| verstehen, worum es geht | `docs/konzept-lernplattform.md` |
| Content schreiben | `docs/content-schema.md`, dann `docs/werkzeug-registry.md` |
| den Betrieb verstehen (Backup, Update, Logs) | `docs/betrieb.md` |
| bekannte Content-Lücken sehen | `docs/content-todo.md` |
| Entscheidungen je Phase nachvollziehen | `docs/adr/` |
| den Bauauftrag lesen | `dcm-lab-agent-prompt.md` |

## Starten

Voraussetzung: Docker.

```bash
cp .env.example .env
make up
make seed
```

Startseite dann unter `http://localhost:8090` (Port konfigurierbar über
`APP_HOST_PORT` in `.env`). `make seed` legt einen Testnutzer an
(`test@example.com` / siehe `database/seeders/DatabaseSeeder.php`) und
synchronisiert `content/` in die Datenbank (`content:sync`).

`make up` baut auch die Spielwiese-Images (`sandbox-images`) — ohne die
scheitert das Starten einer Spielwiese-Sitzung aus einer Lektion heraus.

## Tests sicher ausführen

> **Warnung:** Die Testsuite darf niemals gegen die Entwicklungsdatenbank
> (`dcmlab`) laufen. `./vendor/bin/pest` bzw. `php artisan test` NIEMALS mit
> `--env=local` oder innerhalb eines Containers erzwingen, dessen
> `DB_CONNECTION` schon auf Postgres zeigt — siehe
> `docs/adr/0069-test-datenbank-isolation.md` für den Vorfall, der zu den
> Sperren unten geführt hat.

Datenbanknamen:

| Zweck | Name |
|---|---|
| Laravel, Entwicklung | `dcmlab` |
| Laravel, Tests (Standard) | SQLite `:memory:` — kann die Dev-DB per Bauart nie berühren |
| Laravel, Tests (optional, Postgres-Parität) | `dcmlab_test` |
| Engine, Entwicklung | `dcmlab` (Tabelle `engine_sessions`, geteilte DB mit Laravel) |
| Engine, Tests (Standard) | SQLite `:memory:` |

Benötigte Umgebungsvariable: keine manuell zu setzende — `phpunit.xml` und
`services/engine/tests/conftest.py` erzwingen `APP_ENV=testing` bzw. die
SQLite-Testkonfiguration selbst (mit `force="true"` bzw. per Zuweisung statt
`setdefault`), unabhängig davon, was im aufrufenden Prozess/Container schon
gesetzt ist. Eine zusätzliche Laufzeitsperre
(`tests/Support/DatabaseSafety.php`, `tests/database_safety.py`) bricht jeden
Testlauf hart ab, falls die Umgebung trotzdem nicht eindeutig nach „Test"
aussieht.

**Lokal** (Standard, empfohlen — läuft direkt auf dem Host, nicht im
Container):

```bash
cd apps/web && ./vendor/bin/pest
cd services/engine && python -m pytest
cd services/sandbox && python -m pytest
```

oder gebündelt: `make test`.

**Optionale Postgres-Paritätssuite** (fängt Postgres-spezifisches Verhalten,
z. B. strikte `uuid`-Spalten, die SQLite nicht durchsetzt):

```bash
# einmalig: Testdatenbank anlegen
make test-db-pgsql

cd apps/web && ./vendor/bin/pest --configuration phpunit.pgsql.xml
```

**Im Docker-Container** (z. B. zum Reproduzieren eines CI-Problems): die
Sperren oben machen das unabhängig vom Container sicher, trotzdem der
empfohlene Weg (kein Composer/pytest-Setup im Runtime-Image nötig):

```bash
docker compose -f infra/docker-compose.yml --env-file .env \
  -f infra/docker-compose.dev.yml exec app ./vendor/bin/pest
```

**CI** (`.github/workflows/ci.yml`, Job `web`/`engine`): läuft auf einem
frischen Runner ohne vorbelegte `DB_*`/`ENGINE_DATABASE_URL`-Variablen — die
Sperren greifen dort also ebenso, sind dort aber ohnehin nie das Problem
gewesen (das Risiko besteht ausschließlich, wenn die Suite in einer Umgebung
läuft, die schon reale Datenbank-Zugangsdaten im Prozess hat, also lokal
oder im Dev-Container).

## Was funktioniert

- **Track 1 „Fundamente"** (1.0–1.8, veröffentlicht): vollständiger
  Fließtext, Werkzeugleiste, Glossar, Fortschritt.
- **Track 4 „Troubleshooting"** (4.1–4.10, Gerüste): Metadaten und
  Gliederung stehen, Fließtext fehlt noch — `docs/content-todo.md`.
- **10 Node-Definitionen**, davon drei spielbar (`silent-ct`, `wrong-door`,
  `neue-node`) und sieben als dokumentierte Gerüste, die auf ein konkretes
  fehlendes Engine-Feature verweisen (`docs/content-todo.md`).
- **Node-Engine** (simuliert, `services/engine`): Association-Prüfung
  (Host/Port/Called-AE/Calling-AE), Hints mit Punktabzug, Write-up-Strafe
  nur vorab, Flag-Hashing.
- **Die Spielwiese** (`services/sandbox`, `containers/`): echter Orthanc +
  Toolbox-Container pro Sitzung, Warteschlange, Tageskontingent, Idle-TTL,
  Egress-Sperre, dasselbe Web-Terminal wie bei den Nodes.
- **Punkte, Ränge, Skill-Radar, First Blood, öffentliches Profil** unter
  `public_slug` mit PDF-Export, opt-in-Bestenliste (Standard: aus).
- **Konten**: Registrierung, Login, 2FA, Passkeys, E-Mail-Bestätigung,
  Passwort-Reset — vollständig lokalisiert (`lang/de.json`).
- **Härtung (P9)**: Rate Limits auf allen Endpunkten, die Nutzercode
  ausführen lassen; eigene, gebrandete Fehlerseiten (403/404/419/429/
  500/503); Datenschutz-/Impressum-Platzhalter; Nutzungsbedingungen mit
  explizitem Hinweis, dass die Spielwiese Nutzercode ausführt;
  Betriebsdokumentation (`docs/betrieb.md`).

## Bekannte Lücken

- Track 2, 3, 5 sind im Konzept geplant, aber nicht angelegt.
- Sieben Node-Gerüste (siehe oben) warten auf konkrete Engine-Features
  (lokale `.dcm`-Datei lesen, Mehrfach-Bestand pro Archiv,
  Presentation-Context-/Transfer-Syntax-Aushandlung) — Details und
  Blockaden je Node: `docs/content-todo.md`.
- Redaktionelle Content-Lücken (Beispielregel-Grenzfälle, zwei
  Node-Assetdateien ohne Text): `docs/content-todo.md`. Deshalb läuft
  `content:validate` in CI weiterhin mit `continue-on-error: true`.
- Metriken/Monitoring: nur das, was Docker und Postgres selbst hergeben
  (`docs/betrieb.md`) — kein eigenes Dashboard.
- Rechtliche Seiten sind bewusste Platzhalter, keine echte Rechtsperson
  betreibt die Plattform.

## Prüfliste für den gemeinsamen Durchgang

Die Stellen, an denen eine zweite Meinung am meisten wert ist:

- Exakte Schreibweise der dcmtk-Optionen: `dcmconv +ti`, `dcmcjpeg +e1`,
  `dcmdump +P`, `dcmdump -f`, `storescp -od`
- Wortlaut der Werkzeugausgaben, besonders die Ergebnisse der Presentation
  Contexts in 1.8
- Ob die Fehlerbilder realistisch sind — geschrieben aus Standardwissen,
  nicht aus Vorfällen eines echten Hauses
- Die Rang-Punktschwellen und First-Blood-Ausgestaltung (ADR 0009) sind
  erfundene, aber bewusst reversible Balance-Entscheidungen

---

DICOM ist eine eingetragene Marke der NEMA. Dieses Projekt steht in keiner
Verbindung zu NEMA.
