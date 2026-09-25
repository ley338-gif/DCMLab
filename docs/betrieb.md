# Betrieb

Kurzanleitung für die Person, die diesen Server tatsächlich am Laufen hält
(Abschnitt 10, P9). Setzt `docs/adr/0001-p0-scaffold-entscheidungen.md`
(Compose-Layout) und `docs/adr/0007`/`0008` (Sandbox-Container) voraus.

## Backup

Die Datenbank ist der einzige veränderliche Zustand, der ein Backup braucht.
`content/` liegt im Git-Repo (Abschnitt 1.4 des Auftrags) — ein `git clone`
reicht, um es wiederherzustellen. Hochgeladene Dateien gibt es nicht
(Abschnitt 1: Lernende installieren nichts, laden nichts hoch außer den
Befehlen, die sie in die simulierte Shell tippen).

```bash
# Sichern
docker compose -f infra/docker-compose.yml --env-file .env exec postgres \
  pg_dump -U "${POSTGRES_USER:-dcmlab}" "${POSTGRES_DB:-dcmlab}" \
  | gzip > backup-$(date +%Y%m%d).sql.gz

# Wiederherstellen (auf einer frischen Instanz, VOR dem ersten `make seed`)
gunzip -c backup-20260916.sql.gz | docker compose -f infra/docker-compose.yml \
  --env-file .env exec -T postgres psql -U "${POSTGRES_USER:-dcmlab}" "${POSTGRES_DB:-dcmlab}"
```

Was **nicht** gesichert werden muss: Sandbox-Container und ihre Netze
(`services/sandbox`) sind laut Auftrag zustandslos und laufen nach einem
Neustart einfach nicht mehr — das ist Abschnitt 6 so vorgesehen, nicht ein
Datenverlust.

Empfohlene Frequenz: täglich, vor jedem `make up`/Deploy zusätzlich einmal
manuell. Es gibt noch keinen automatisierten Cron-Job dafür — das wäre eine
reine Infrastrukturaufgabe außerhalb dieses Repos (Hosting-Umgebung
abhängig) und wird hier bewusst nicht vorgetäuscht.

## Update

```bash
git pull
docker compose -f infra/docker-compose.yml --env-file .env build
docker compose -f infra/docker-compose.yml --env-file .env up -d
docker compose -f infra/docker-compose.yml --env-file .env exec app php artisan migrate --force
docker compose -f infra/docker-compose.yml --env-file .env exec app php artisan content:sync
```

`content:sync` ist idempotent — nach jedem `git pull`, der Content geändert
hat, erneut laufen lassen. `make seed` macht `migrate:fresh --seed` und ist
**nur** für eine frische Instanz gedacht — auf einem Server mit echten
Nutzerdaten löscht `migrate:fresh` diese unwiderruflich.

**`content:sync` überschreibt keinen Studio-Stand mehr** (ADR 0122). Die DB
ist die Autoren-Wahrheit für Lektionen, Nodes und Track-Einstellungen. Der
Sync lässt deshalb liegen:

- bei einer Lektion oder Node mit mindestens einer **veröffentlichten**
  `content_versions`-Zeile genau die Felder, die Studio veröffentlicht:
  - Lektion: Titel, Teaser, Lernziele, Level, Dauer, Werkzeuge,
    Voraussetzungen, Glossarbegriffe, Spielwiese, verwandte Node
  - Quiz: `quiz` und den Quiz-Abschnitt
  - Node: alle Felder aus `node.yml` außer `environment`/`flag`,
    einschließlich `status`
- **immer**, sobald die Zeile existiert: Track-`themenfeld`, `order`,
  `level`, `hours` und `status` sowie Node-`themenfeld`. Diese Felder setzt
  Studio direkt.

Weicht `content/` in einem dieser Felder ab, meldet der Sync das je
Ressource mit den Feldnamen (`… abweichende Dateiwerte für title, objectives
nicht übernommen`). Die Warnung ist kein Fehler. Sie heißt, dass Studio
neuer ist als das Repo. Richtig ist dann `content:export` (README, „Content
exportieren“), nicht eine Handänderung an `content/`.

Eine frische DB ohne `content_versions` (Seed, Demo, neue Umgebung)
synchronisiert wie bisher vollständig aus `content/`.

### Studio-Stand bewusst mit `content/` überschreiben

Nur für eine gezielte Rücksetzung, z. B. wenn ein Studio-Stand verworfen
werden soll und `content/` der gewünschte Stand ist:

```bash
docker compose -f infra/docker-compose.yml --env-file .env exec app php artisan content:sync --force-from-files
```

Der Befehl fragt interaktiv nach. Mit `--no-interaction` bricht er ab, ohne
etwas zu schreiben. Er hebt **beide** Schutzregeln für **alle** Ressourcen
auf, das ist das Verhalten von vor ADR 0122. Die Versionshistorie
(`content_versions`) bleibt unverändert. Danach steht in ihr ein Stand als
aktuell, der nicht mehr live ist. Vorher also mit `content:export --check`
prüfen, was sich ändern würde.

Die Sandbox-Images (`containers/orthanc`, `containers/toolbox`) werden
separat gebaut, weil sie keine Compose-Services sind, sondern zur Laufzeit
per Docker-Socket gestartete Container-Paare (Abschnitt 6):

```bash
make sandbox-images
```

## Logs

```bash
make logs                                   # alle Services
docker compose -f infra/docker-compose.yml --env-file .env logs -f app
docker compose -f infra/docker-compose.yml --env-file .env logs -f engine
docker compose -f infra/docker-compose.yml --env-file .env logs -f sandbox
```

Laravel schreibt zusätzlich strukturiert nach `storage/logs/laravel.log` im
`app`-Container (`docker compose exec app tail -f storage/logs/laravel.log`).
Die FastAPI-Dienste (`engine`, `sandbox`) loggen nach stdout — reicht für
`docker compose logs`, es gibt keine separate Log-Datei.

Sandbox-Container selbst (Orthanc, Toolbox) sind pro Lernsitzung kurzlebig;
ihre Logs sind mit dem Container weg, sobald die Sitzung endet (Idle-TTL
oder manuelles Beenden, Abschnitt 6). Das ist beabsichtigt — sie enthalten
nichts, was über die Sitzung hinaus relevant wäre, und Lernende führen
darin beliebigen DICOM-Verkehr aus (siehe `docs/nutzungsbedingungen.md`).

## Metriken

**Ehrlicher Stand: es gibt noch keine eingebaute Metrik-Erfassung.** Was
aktuell beobachtbar ist, ohne dass etwas Neues gebaut wurde:

- `docker stats` für CPU/RAM je Container.
- `docker compose ps` für den Zustand der Compose-Services.
- Postgres selbst: `SELECT count(*) FROM node_attempts WHERE status='solved'`
  u. Ä. für grobe Nutzungszahlen, manuell per `psql`.
- Der Orchestrator (`services/sandbox`) hat kein Metrik-Endpoint, nur die
  Zustands-Endpunkte, die die Web-UI selbst benutzt (Warteschlangenlänge,
  aktive Sitzungen je Nutzer — sichtbar über die normalen API-Antworten,
  nicht über ein Dashboard).

Was für einen echten Betrieb fehlt und **nicht** im Rahmen von P9 erfunden
wurde (Abschnitt 13: keine unbelegten Fakten vortäuschen): ein
Prometheus-/StatsD-Exporter für Anfragedauer und Fehlerraten, ein Dashboard
für Tageskontingent-Auslastung der Spielwiese, Alerting bei vollem
Postgres-Volume. Das ist eine eigene, spätere Aufgabe — diese Datei
dokumentiert bewusst nur, was heute tatsächlich da ist.

## Rate Limits

Siehe `docs/adr/0010-p9-content-flaeche-und-haertung-entscheidungen.md` für
die Begründung der konkreten Werte. Kurzfassung, wo sie greifen:

| Route | Limit |
|---|---|
| Login, Zwei-Faktor (Fortify) | 5–10 Versuche/Minute, bestehend seit P2 |
| `nodes/{node}/exec`, `.../hint`, `.../flag`, `.../write-up`, `.../action`, `.../config` | 60 Anfragen/Minute je Nutzer |
| `sandbox/*` (Erzeugen, Ausführen, Beenden) | 30 Anfragen/Minute je Nutzer |
| `lessons/{lesson}/sandbox` (Sandbox aus einer Lektion starten) | 10 Anfragen/Minute je Nutzer |

Alle über Laravels `throttle`-Middleware, keine externe Komponente nötig.
