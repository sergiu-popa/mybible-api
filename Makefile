# Plain `docker exec` (no `-t`) so batch targets work without a TTY (CI, IDE agents).
# Interactive shells keep `docker exec -it` (bash, tinker).
up:
	docker compose up -d

down:
	docker compose down

restart: down up

setup: env up composer key migrate
	@echo "Setup complete. Visit http://api.mybible.local"

env:
	cp .env.example .env

composer:
	docker exec mybible-api-app composer install

key:
	docker exec mybible-api-app php artisan key:generate

migrate:
	docker exec mybible-api-app php artisan migrate

migrate-test:
	docker exec -e DB_HOST=mybible-api-test-mysql mybible-api-app php artisan migrate

fresh:
	docker exec mybible-api-app php artisan migrate:fresh

fresh-test:
	docker exec -e DB_HOST=mybible-api-test-mysql mybible-api-app php artisan migrate:fresh

seed:
	docker exec mybible-api-app php artisan db:seed

refresh: fresh seed

refresh-test: fresh-test

bash:
	docker exec -it mybible-api-app /bin/bash

tinker:
	docker exec -it mybible-api-app php artisan tinker

test: migrate-test
	docker exec mybible-api-app php artisan test --compact $(if $(filter),--filter="$(filter)")

test-unit:
	docker exec mybible-api-app php artisan test --compact --testsuite=Unit

test-feature: migrate-test
	docker exec mybible-api-app php artisan test --compact --testsuite=Feature

# Post-cutover "is it up?" smoke suite. Hits the live TARGET_URL;
# credentials are loaded from .env.smoke (gitignored) if present, or
# can be exported on the host and forwarded via `-e`. Status-code
# assertions only — correctness is the regular feature suite's job.
#
# Example:
#   make smoke TARGET_URL=https://api.mybible.eu
smoke:
	@test -f .env.smoke || { echo ".env.smoke not found. See runbook.md §4."; exit 1; }
	docker exec \
		-e SMOKE_TARGET_URL=$(TARGET_URL) \
		--env-file .env.smoke \
		mybible-api-app \
		php artisan test --compact --testsuite=Smoke --group=smoke

coverage: migrate-test
	docker exec -e XDEBUG_MODE=coverage mybible-api-app php artisan test --coverage $(if $(min),--min=$(min))

lint:
	docker exec mybible-api-app composer lint

lint-fix:
	docker exec mybible-api-app composer lint-fix

stan:
	docker exec mybible-api-app composer stan

check: lint stan test

cache:
	docker exec mybible-api-app php artisan optimize:clear

app-logs:
	docker logs --tail 500 mybible-api-app
