# 0001 — Entscheidungen beim P0-Gerüst

Status: akzeptiert
Datum: 2026-09-12

## Kontext

Beim Aufbau des Monorepo-Gerüsts (Auftrag Abschnitt 10, Phase P0) waren mehrere
kleinere, aber nicht im Auftrag vorweggenommene technische Entscheidungen
nötig. Alle sind umkehrbar (Abschnitt 14.2).

## Entscheidungen

**PHP 8.4 statt 8.3.** Laravel 13 zieht (Stand heute) transitiv
Symfony-Komponenten, die `php >= 8.4` voraussetzen (`symfony/clock`,
`symfony/console` u.a.). `composer.json` fordert weiterhin nur `^8.3`
(kompatibel, da 8.4 in diesem Bereich liegt), aber alle Docker-Images und die
CI-Pipeline verwenden PHP 8.4, weil 8.3 beim Build tatsächlich fehlschlägt.
Erfüllt weiterhin "PHP 8.3+" aus Abschnitt 3.1.

**Getrennte Host-Ports für Postgres/Redis/App.** Auf der Entwicklungsmaschine
liefen bereits andere Projekte auf 5432, 6379 und 8080. `.env.example` setzt
deshalb `POSTGRES_HOST_PORT=5433`, `REDIS_HOST_PORT=6380`,
`APP_HOST_PORT=8090` als Standard. Innerhalb des Compose-Netzes bleiben die
Standardports (5432, 6379, 80) unverändert.

**`docker compose` braucht `--env-file .env` explizit.** Da `infra/` das
Compose-Projektverzeichnis ist (erste angegebene Compose-Datei liegt dort),
sucht Compose sein `.env` dort und nicht im Repo-Root. Das `Makefile` ruft
Compose deshalb immer mit `--env-file .env` (Pfad relativ zum Repo-Root) auf.

**Zwei getrennte `.env`-Dateien für die Web-App.** `apps/web/.env.example`
ist die Vorlage, aus der der Container beim ersten Start seine eigene `.env`
erzeugt (braucht eine leere `APP_KEY=`-Zeile, damit `artisan key:generate`
sie ersetzen kann). `apps/web/.env.docker` ist die Datei, die
`docker-compose.yml` als `env_file` einliest — sie **darf kein** `APP_KEY`
enthalten. Grund: `env_file` wird von Docker Compose auf dem Host ausgewertet
und als Prozessumgebung an den Container übergeben, bevor der Entrypoint
läuft. Ein dort gesetzter, leerer `APP_KEY`-Wert blockiert (Symfony Dotenv
ist "immutable") die vom Entrypoint frisch generierte Umgebungsvariable dauer-
haft — die Anwendung wirft dann bei jedem Request `MissingAppKeyException`.
`DB_PASSWORD`, `DB_USERNAME`, `DB_DATABASE` und `DCMLAB_INTERNAL_KEY` kommen
aus demselben Grund über `docker-compose.yml: environment:` aus dem
zentralen Root-`.env`, nicht aus einer app-eigenen Datei.

**Caddy und php-fpm teilen sich `public/` über ein Volume unter identischem
Pfad.** Caddys `php_fastcgi`-Direktive berechnet `SCRIPT_FILENAME` aus ihrem
eigenen `root` plus dem Request-Pfad. Läuft `php-fpm` in einem anderen
Container mit anderem Wurzelverzeichnis, passt der von Caddy übergebene Pfad
nicht zu dem, was `php-fpm` im eigenen Dateisystem sieht — Ergebnis:
`File not found.` Lösung: `public/` liegt im Image unter `public-image/`,
das Volume `web-public` wird bei beiden Containern exakt unter
`/var/www/html/public` gemountet, der Entrypoint befüllt es beim ersten Start.

**php-fpm startet als root, Worker als `dcmlab`.** Der Master-Prozess von
php-fpm muss seinen `error_log` öffnen können; läuft er selbst schon als
unprivilegierter Nutzer (z. B. über `su-exec` im Entrypoint), schlägt das in
diesem Setup fehl. Stattdessen bleibt der Entrypoint bis zum `exec` root,
nur `www.conf` bekommt `user = dcmlab` / `group = dcmlab` — der übliche Weg,
wie offizielle PHP-Images das lösen.

## Folgen

Keine der Entscheidungen berührt eine Leitplanke aus Abschnitt 1 oder das
Content-/Node-Schema. Alle sind bei Bedarf revidierbar, ohne dass Content
oder Datenmodell angefasst werden müssten.
