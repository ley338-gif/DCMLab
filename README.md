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
