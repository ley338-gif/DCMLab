# 0003 — Valkey statt Redis

Status: akzeptiert
Datum: 2026-09-12

## Kontext

Der Auftrag (Abschnitt 3.1) nennt „Redis" als Queue-/Cache-Baustein, ohne
eine konkrete Distribution festzulegen. Redis selbst ist seit Version 7.4
nicht mehr unter einer OSI-Open-Source-Lizenz erhältlich, sondern dual unter
RSALv2/SSPLv2 (source-available). Das steht der Absicht entgegen, DCM Lab als
offene Plattform zu betreiben (Abschnitt 14: Lizenzfrage des Repos ist zwar
noch offen, die Tendenz geht aber Richtung offen).

## Entscheidung

Statt `redis:*` wird `valkey/valkey:8-alpine` verwendet — der von der Linux
Foundation getragene Fork des letzten BSD-3-lizenzierten Redis-Standes.
Valkey ist protokoll- und API-kompatibel: `phpredis` (Laravel), `redis-py`
(Sandbox-Orchestrator) und der `REDIS_*`-Konfigurationsblock sprechen weiter
dasselbe RESP-Protokoll, es musste kein Zeilencode geändert werden.

Geändert wurden nur:

- `infra/docker-compose.yml`: Service `redis` → `valkey`, Image
  `valkey/valkey:8-alpine`, Healthcheck `valkey-cli ping`
- `apps/web/.env.example`, `.env.docker`: `REDIS_HOST=valkey`
- `services/sandbox/app/config.py`: Default-`SANDBOX_REDIS_URL` zeigt auf
  `valkey`

Die Laravel-seitigen Variablennamen bleiben `REDIS_*` — das ist Laravels
Bezeichnung für den RESP-Client, unabhängig vom Server-Produkt dahinter,
genau wie `DB_CONNECTION=pgsql` unabhängig vom konkreten Postgres-Anbieter
bleibt.

## Folgen

Keine funktionale Änderung, keine Code-Änderung außerhalb der drei oben
genannten Konfigurationsstellen. Reversibel, falls doch einmal ein
Redis-exklusives Feature gebraucht wird (aktuell wird nur Cache/Queue/Session
genutzt, alles Standard-RESP).
