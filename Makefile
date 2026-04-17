# Plain `docker exec` (no `-t`) so batch targets work without a TTY (CI, IDE agents).
# Interactive shells keep `docker exec -it` (bash, tinker).
up:
	docker compose up -d

down:
	docker compose down

restart: down up

setup: env up composer key migrate npm-build
	@echo "Setup complete. Visit http://laravel-13-start.localhost"

env:
	cp .env.example .env

composer:
	docker exec laravel-13-start-app composer install

key:
	docker exec laravel-13-start-app php artisan key:generate

migrate:
	docker exec laravel-13-start-app php artisan migrate

migrate-test:
	docker exec -e DB_HOST=laravel-13-start-test-mysql laravel-13-start-app php artisan migrate

fresh:
	docker exec laravel-13-start-app php artisan migrate:fresh

fresh-test:
	docker exec -e DB_HOST=laravel-13-start-test-mysql laravel-13-start-app php artisan migrate:fresh

seed:
	docker exec laravel-13-start-app php artisan db:seed

refresh: fresh seed

refresh-test: fresh-test

bash:
	docker exec -it laravel-13-start-app /bin/bash

tinker:
	docker exec -it laravel-13-start-app php artisan tinker

npm:
	docker exec laravel-13-start-app npm install

npm-dev:
	docker exec laravel-13-start-app bash -c "npm install && npm run dev"

npm-build:
	docker exec laravel-13-start-app bash -c "npm install && npm run build"

test: migrate-test
	docker exec laravel-13-start-app php artisan test --compact $(if $(filter),--filter="$(filter)")

test-unit:
	docker exec laravel-13-start-app php artisan test --compact --testsuite=Unit

test-feature: migrate-test
	docker exec laravel-13-start-app php artisan test --compact --testsuite=Feature

lint:
	docker exec laravel-13-start-app composer lint

lint-fix:
	docker exec laravel-13-start-app composer lint-fix

stan:
	docker exec laravel-13-start-app composer stan

check: lint stan test

cache:
	docker exec laravel-13-start-app php artisan optimize:clear

app-logs:
	docker logs --tail 500 laravel-13-start-app
