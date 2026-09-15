# 0087 — Scheduler-Betrieb fuer `review:send-reminders`

## Status

Angenommen, 15.09.2026. Loest die offene Frage "Scheduler-Betrieb fuer
review:send-reminders" (`docs/offene-fragen.md`, ADR 0084, W7).

## Kontext

ADR 0084 (W7) registrierte `review:send-reminders` in
`bootstrap/app.php` (`withSchedule()`), liess aber bewusst offen, WIE
`php artisan schedule:run`/`schedule:work` im Deployment tatsaechlich
ausgefuehrt wird -- ein zweiter Container auf demselben Image haette
zwei bestehende Annahmen von `docker/entrypoint.sh` sichtbar gemacht:

1. **`APP_KEY` wurde PRO CONTAINER lokal generiert**
   (`.env.docker` liess ihn absichtlich leer, `entrypoint.sh` rief bei
   fehlendem Schluessel `key:generate --force` auf und schrieb das
   Ergebnis in die eigene, nicht geteilte `.env`-Datei des Containers).
   Ein zweiter Container haette einen ANDEREN Schluessel bekommen als
   `app` -- Sessions, signierte URLs und verschluesselte Werte waeren
   zwischen beiden nicht kompatibel gewesen.
2. **Das geteilte `web-public`-Volume wurde bei JEDEM Containerstart
   geleert und neu befuellt** (Caddy und `app` teilen sich dieses
   Volume). Zwei gleichzeitig startende Container haetten sich einen
   Loesch-/Kopier-Race auf demselben Volume geliefert.

## Entscheidung

**`APP_KEY` kommt jetzt zentral aus der Root-`.env`** (neue Pflichtvariable,
`.env.example` dokumentiert die Erzeugung per `openssl rand -base64 32`).
`docker-compose.yml` uebergibt sie ueber `${APP_KEY:?...}` (harter Fehler
mit klarer Meldung, falls sie fehlt) an `app` UND den neuen
`scheduler`-Service -- beide benutzen ab sofort denselben Schluessel.
`entrypoint.sh` generiert nur noch einen Fallback-Wert, wenn `APP_KEY`
in der Prozessumgebung leer UND noch keiner in der lokalen `.env`-Datei
steht (z. B. bei einem Start ausserhalb von Compose).

**`docker/entrypoint.sh` fuehrt die `web-public`-Neubefuellung nur noch
aus, wenn das tatsaechlich ausgefuehrte Kommando `php-fpm` ist**
(`if [ "$1" = "php-fpm" ] && ...`) -- der neue `scheduler`-Service
fuehrt stattdessen `php artisan schedule:work` aus, `$1` ist dann
`php`, die Neubefuellung wird uebersprungen. Kein neuer Env-Flag noetig,
die Entscheidung haengt direkt an der Rolle des Containers.

**`php artisan migrate --force --isolated`** statt nur `--force`:
`app` und `scheduler` starten oft gleichzeitig und wuerden sonst
versuchen, dieselbe Migration parallel auszufuehren. `--isolated`
nutzt eine Cache-Sperre (`CACHE_STORE=redis`, bereits ueberall
konfiguriert, Valkey ist in jeder Compose-Umgebung vorhanden) -- ein
Doppel-Lauf wird dadurch ungefaehrlich statt nur unwahrscheinlich.

**Neuer `scheduler`-Service** in `infra/docker-compose.yml`: derselbe
Image-Build wie `app`, Kommando `php artisan schedule:work` statt
`php-fpm`, kein `web-public`-Mount (er dient nie HTTP-Traffic). `make up`
startet ihn automatisch mit, ohne Makefile-Aenderung (Compose startet
alle Services ohne explizite Liste).

**CI-Anpassung:** `docker compose ... build` interpoliert die gesamte
Compose-Datei, auch fuer den reinen Build-Schritt -- der
`docker-build`-Job braucht deshalb jetzt ebenfalls einen (beliebigen,
nie tatsaechlich ausgefuehrten) `APP_KEY`-Wert, per `openssl rand`
generiert und in `$GITHUB_ENV` gesetzt.

## Konsequenzen

- **Migrationsschritt fuer bestehende Deployments:** `.env` braucht ab
  sofort `APP_KEY` (siehe README "Starten"). Ohne sie bricht
  `docker compose` mit einer klaren Fehlermeldung ab, statt still mit
  inkompatiblen Schluesseln pro Container weiterzulaufen.
- `review:send-reminders` (und jeder kuenftige geplante Befehl) laeuft
  ab sofort tatsaechlich taeglich, sobald der `scheduler`-Service
  gestartet ist -- kein manuelles `docker compose exec` mehr noetig.

## Verifikation

- `docker compose -f infra/docker-compose.yml config` validiert sauber
  mit gesetztem `APP_KEY`; ohne sie bricht die Interpolation mit der
  erwarteten Fehlermeldung ab (lokal beides getestet).
- `docker compose build app scheduler`: beide Images bauen sauber.
- Smoke-Test gegen die laufende lokale Compose-Umgebung: `scheduler`
  startet, fuehrt die Migrationen aus (`app`, kurz danach gestartet,
  meldet "Nothing to migrate" -- die Isolationssperre griff),
  protokolliert "Running scheduled tasks.", `/var/www/html/public`
  existiert im `scheduler`-Container gar nicht (kein Mount, keine
  Neubefuellung versucht). `app` startet nach der Image-Aktualisierung
  sauber neu.
