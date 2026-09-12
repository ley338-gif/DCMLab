# DCM Lab

Lernplattform für DICOM und PACS. Arbeitsstand 12.09.2026.

Dieses Archiv enthält den Content-Stand aus der Vorarbeit (Konzept,
verbindliche Schemas, Track 1, eine ausgearbeitete Node, spielbarer
Prototyp) sowie das technische Gerüst der eigentlichen Plattform
(`dcm-lab-agent-prompt.md`, Phase P0).

## Aufbau

```
apps/web/      Laravel 13 + Inertia + Vue 3 + Tailwind — die Webapp
services/      FastAPI: Node-Engine (Simulation) und Sandbox-Orchestrator
containers/    Images fuer die Spielwiese (Orthanc, Toolbox) — ab P7
infra/         docker-compose, Caddy — ein `docker compose up` fuer alles
docs/          Konzept, Schemas, Ablaufpläne, ADRs — das Denken dahinter
content/       Der Lernstoff im verbindlichen Repo-Format (content-schema.md)
prototyp/      Die spielbare Node "Silent CT" als einzelne HTML-Datei
```

## Wo anfangen

| Du willst … | Lies |
|---|---|
| die Plattform starten | Abschnitt "Starten" unten |
| verstehen, worum es geht | `docs/konzept-lernplattform.md` |
| Content schreiben | `docs/content-schema.md`, dann `docs/werkzeug-registry.md` |
| die Node testen | `prototyp/silent-ct.html` im Browser öffnen |
| die Testrunde starten | `docs/testrunde-phase-0.md` |
| eine Lektion lesen | `content/lessons/1.0/de.md` |
| den Bauauftrag lesen | `dcm-lab-agent-prompt.md` |

## Starten

Voraussetzung: Docker.

```bash
cp .env.example .env
docker compose -f infra/docker-compose.yml --env-file .env up -d --build
```

Startseite dann unter `http://localhost:8090` (Port konfigurierbar über
`APP_HOST_PORT` in `.env` — auf dieser Entwicklungsmaschine liefen 5432, 6379
und 8080 bereits belegt, siehe `docs/adr/0001-p0-scaffold-entscheidungen.md`).
`make up` macht dasselbe, sobald `make` verfügbar ist.

## Stand

**Content fertig:** Konzept · Content-Schema · Werkzeug-Registry ·
Testrunde-Ablauf · Track 1 (1.0 bis 1.8) · Node "Silent CT" inklusive
spielbarem Prototyp

**Plattform (P0 — Gerüst und Betrieb) fertig:**

- Monorepo-Layout nach Abschnitt 3.2
- Laravel 13 + Inertia + Vue 3 + Tailwind, Pest, Pint, PHPStan Level 7
- FastAPI-Gerüste für Engine und Sandbox-Orchestrator (Endpunkte nach
  Abschnitt 5.5 als `501 Not Implemented`-Stubs, internes Secret geprüft)
- `docker-compose.yml` mit App, Postgres, Redis, Engine, Orchestrator, Caddy
- CI-Pipeline (GitHub Actions): Lint, Typen, Tests, Docker-Build für alle Teile
- `Makefile` mit `up`, `down`, `test`, `lint`, `seed`, `content-validate`,
  `content-build` (die drei Content-Kommandos sind Platzhalter, siehe unten)

**Offen (nächste Phasen laut Abschnitt 10):**

- P1: `content:validate` und `content:sync` sind im Auftrag beschrieben, aber
  noch nicht implementiert — der Content wird noch nicht eingelesen
- P2 bis P9: Konten, Lektionsansicht, Node-Engine-Logik, Web-Terminal,
  Spielwiese, Punkte/Ränge — siehe Abschnitt 10 im Auftrag
- Sieben Node-Slugs werden von den Lektionen referenziert, existieren aber noch
  nicht: `first-contact`, `zwei-ebenen-tiefer`, `wo-steht-das`, `zwillinge`,
  `neue-node`, `halbe-sache`, `mitgehoert`
- `content/glossary/de.yml` ist nur begonnen — die `{{term:…}}`-Markierungen in
  den Lektionen zeigen teilweise ins Leere
- `content/datasets.yml` fehlt
- Track 2 bis 5 sind im Konzept geplant, nicht geschrieben
- Track 4 braucht echte Vorfälle aus dem Klinikbetrieb, keine erfundenen

## Prüfliste für den gemeinsamen Durchgang

Die Stellen, an denen eine zweite Meinung am meisten wert ist:

- Exakte Schreibweise der dcmtk-Optionen: `dcmconv +ti`, `dcmcjpeg +e1`,
  `dcmdump +P`, `dcmdump -f`, `storescp -od`
- Wortlaut der Werkzeugausgaben, besonders die Ergebnisse der Presentation
  Contexts in 1.8
- Ob die Fehlerbilder realistisch sind — geschrieben aus Standardwissen,
  nicht aus Vorfällen eines echten Hauses

---

DICOM ist eine eingetragene Marke der NEMA. Dieses Projekt steht in keiner
Verbindung zu NEMA.
