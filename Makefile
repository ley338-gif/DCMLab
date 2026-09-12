COMPOSE = docker compose -f infra/docker-compose.yml --env-file .env
COMPOSE_DEV = $(COMPOSE) -f infra/docker-compose.dev.yml

.PHONY: up down test lint seed content-validate content-build logs sandbox-images

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
	cd apps/web && ./vendor/bin/pest
	cd services/engine && python -m pytest
	cd services/sandbox && python -m pytest

lint:
	cd apps/web && ./vendor/bin/pint --test
	cd apps/web && npm run check
	cd services/engine && ruff check . && mypy app
	cd services/sandbox && ruff check . && mypy app

seed:
	$(COMPOSE_DEV) exec app php artisan migrate:fresh --seed
	$(COMPOSE_DEV) exec app php artisan content:sync

content-validate:
	cd apps/web && php artisan content:validate

content-build:
	cd apps/web && php artisan content:build
