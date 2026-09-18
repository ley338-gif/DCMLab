COMPOSE = docker compose -f infra/docker-compose.yml --env-file .env
COMPOSE_DEV = $(COMPOSE) -f infra/docker-compose.dev.yml

.PHONY: up down test test-db-pgsql lint seed content-validate content-build logs sandbox-images

up: sandbox-images
	$(COMPOSE_DEV) up --build -d

# Images der Spielwiese (Abschnitt 6): keine Compose-Services, sondern von
# services/sandbox zur Laufzeit per Docker-Socket gestartete Container-Paare
# -- muessen deshalb vorab als Images existieren, nicht als `build:`-Eintrag
# in docker-compose.yml.
sandbox-images:
	docker build -t dcmlab/orthanc:latest containers/orthanc
	docker build -f containers/toolbox/Dockerfile -t dcmlab/toolbox:latest .

down:
	$(COMPOSE_DEV) down

logs:
	$(COMPOSE_DEV) logs -f

test:
	cd apps/web && APP_ENV=testing ./vendor/bin/pest
	cd services/engine && python -m pytest
	cd services/scenario-engine && python -m pytest
	cd services/sandbox && python -m pytest

# Einmalig fuer die optionale Postgres-Paritaetssuite (README "Tests sicher
# ausfuehren", docs/adr/0069-test-datenbank-isolation.md): legt dcmlab_test
# an, falls sie noch nicht existiert. Laesst dcmlab unberuehrt -- CREATE
# DATABASE, nie DROP/migrate:fresh gegen die echte Datenbank.
test-db-pgsql:
	$(COMPOSE_DEV) exec postgres psql -U dcmlab -tc "SELECT 1 FROM pg_database WHERE datname = 'dcmlab_test'" | grep -q 1 || \
		$(COMPOSE_DEV) exec postgres psql -U dcmlab -c "CREATE DATABASE dcmlab_test"

lint:
	cd apps/web && ./vendor/bin/pint --test
	cd apps/web && npm run check
	cd services/engine && ruff check . && mypy app
	cd services/scenario-engine && ruff check . && mypy app
	cd services/sandbox && ruff check . && mypy app

seed:
	$(COMPOSE_DEV) exec app php artisan migrate:fresh --seed
	$(COMPOSE_DEV) exec app php artisan content:sync
	# PR #152 (Lab Content Deployment): nur der Initial-Bootstrap-Pfad --
	# `labs:import` ist idempotent und ruehrt nie Nutzerfortschritt an, ist
	# hier aber bewusst NICHT Teil des laufenden Production-Update-Wegs
	# (docs/betrieb.md: git pull -> migrate --force -> content:sync). Ein
	# frisch importiertes/geaendertes Lab dort mit auszurollen ist ein
	# separater, spaeterer Schritt.
	$(COMPOSE_DEV) exec app php artisan labs:import

content-validate:
	cd apps/web && php artisan content:validate

content-build:
	cd apps/web && php artisan content:build
