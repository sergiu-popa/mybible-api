# MyBible API

JSON-only Laravel 13 API, shipped with a Docker-based development environment and a CI pipeline that enforces lint, static analysis, and test coverage.

## Stack

- **PHP** 8.4
- **Laravel** 13
- **MySQL** 8.0 and **Redis** (separate containers for app and tests)
- **PHPUnit** 12, **Larastan/PHPStan** 3, **Laravel Pint** 1
- **Laravel Boost** for AI-assisted development

## Requirements

- Docker and Docker Compose
- `mybible-api.localhost` resolves automatically on macOS; on Linux add an entry to `/etc/hosts`.

## Quick Start

```bash
make setup
```

This target copies `.env.example` to `.env`, boots the containers, installs composer dependencies, generates the app key, and runs migrations. The API is available at http://mybible-api.localhost.

## Make Targets

All commands run inside the `mybible-api-app` container.

### Containers
- `make up` / `make down` / `make restart` — start, stop, restart the stack
- `make bash` — interactive shell in the app container
- `make tinker` — `php artisan tinker`
- `make app-logs` — tail the app container logs

### Database
- `make migrate` / `make fresh` / `make seed` / `make refresh`
- `make migrate-test` / `make fresh-test` / `make refresh-test` — same commands against the isolated test MySQL container

### Quality
- `make lint` / `make lint-fix` — Pint
- `make stan` — PHPStan / Larastan
- `make test` — PHPUnit (optionally `make test filter=testName`)
- `make test-unit` / `make test-feature`
- `make check` — lint + stan + test

## Docker Services

Defined in `docker-compose.yml`:

| Service | Container | Purpose |
| --- | --- | --- |
| `traefik` | `mybible-api-traefik` | Reverse proxy, exposes the API at `mybible-api.localhost` |
| `app` | `mybible-api-app` | PHP 8.4 application container (built from `.docker/Dockerfile`) |
| `worker` | `mybible-api-worker` | Queue worker |
| `mysql` / `test-mysql` | `mybible-api-mysql` / `-test-mysql` | Application and tmpfs-backed test databases |
| `redis` / `test-redis` | `mybible-api-redis` / `-test-redis` | Application and test Redis instances |

Xdebug is available by setting `XDEBUG_MODE` in `.env`.

## API Conventions

- All endpoints live under `/api/v1`.
- All responses are JSON; all exceptions (validation, not-found, auth, generic) are rendered as JSON via `bootstrap/app.php`.
- Controllers go under `App\Http\Controllers\Api\V1`.
- Use Form Requests for validation and Eloquent API Resources for response shaping.
- Health check: `GET /up`.

## CI Pipeline

`.github/workflows/ci.yml` runs on every push and is organized into parallel jobs:

1. **Build image** — builds the `ci` target of `.docker/Dockerfile`, pushes/caches it against a branch tag, and shares it with downstream jobs via the GitHub Actions cache.
2. **Code style** — `composer lint` (Pint in `--test` mode).
3. **Static analysis** — `composer stan` (PHPStan with result cache restored from the `main` branch).
4. **Tests** — `php artisan migrate` followed by `php artisan test --coverage --min=80` against MySQL and Redis service containers. **Builds fail if coverage drops below 80%.**
5. **Docker push** — publishes the image to GHCR once all checks pass.
6. **Cleanup** — deletes the per-run image cache entry.

## Project Layout Notes

- `.docker/` — Dockerfile (multi-stage: `development`, `ci`, `production`), entrypoint, Apache/PHP/supervisor config.
- `CLAUDE.md` — guidelines for AI coding agents; includes Laravel Boost rules and the Docker command convention.
- `boost.json` — Laravel Boost configuration.
- `phpstan.neon`, `pint.json`, `phpunit.xml` — quality-tool configuration.

## License

MIT.
