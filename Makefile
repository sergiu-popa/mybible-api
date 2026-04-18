# Plain `docker exec` (no `-t`) so batch targets work without a TTY (CI, IDE agents).
# Interactive shells keep `docker exec -it` (bash, tinker).
up:
	docker compose up -d

down:
	docker compose down

restart: down up

setup: env up composer key migrate
	@echo "Setup complete. Visit http://mybible-api.localhost"

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
