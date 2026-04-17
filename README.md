# Laravel 13 Starter

Opinionated starter template for Laravel 13 applications built with Livewire 4 and Flux UI Pro, shipped with a Docker-based development environment and a CI pipeline that enforces lint, static analysis, and test coverage.

## Stack

- **PHP** 8.4
- **Laravel** 13
- **Livewire** 4
- **Flux UI Pro** 2 (requires a license — see below)
- **Tailwind CSS** 4 + Vite
- **MySQL** 8.0 and **Redis** (separate containers for app and tests)
- **PHPUnit** 12, **Larastan/PHPStan** 3, **Laravel Pint** 1
- **Laravel Boost** for AI-assisted development

## Requirements

- Docker and Docker Compose
- A Flux UI Pro license — the `auth.json` file must be populated before running `composer install`. See [Flux Pro installation](https://fluxui.dev/docs/installation).
- `laravel-13-start.localhost` resolves automatically on macOS; on Linux add an entry to `/etc/hosts`.

## Quick Start

```bash
make setup
```

This target copies `.env.example` to `.env`, boots the containers, installs composer and npm dependencies, generates the app key, runs migrations, and builds frontend assets. The app will be available at http://laravel-13-start.localhost.

To run Vite in dev mode instead of a one-shot build:

```bash
make npm-dev
```

## Make Targets

All commands run inside the `laravel-13-start-app` container.

### Containers
- `make up` / `make down` / `make restart` — start, stop, restart the stack
- `make bash` — interactive shell in the app container
- `make tinker` — `php artisan tinker`
- `make app-logs` — tail the app container logs

### Database
- `make migrate` / `make fresh` / `make seed` / `make refresh`
- `make migrate-test` / `make fresh-test` / `make refresh-test` — same commands against the isolated test MySQL container

### Frontend
- `make npm` — install npm dependencies
- `make npm-dev` — run Vite dev server
- `make npm-build` — production build

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
| `traefik` | `laravel-13-start-traefik` | Reverse proxy, exposes the app at `laravel-13-start.localhost` |
| `app` | `laravel-13-start-app` | PHP 8.4 application container (built from `.docker/Dockerfile`) |
| `worker` | `laravel-13-start-worker` | Queue worker |
| `mysql` / `test-mysql` | `laravel-13-start-mysql` / `-test-mysql` | Application and tmpfs-backed test databases |
| `redis` / `test-redis` | `laravel-13-start-redis` / `-test-redis` | Application and test Redis instances |

Xdebug is available by setting `XDEBUG_MODE` in `.env`.

## CI Pipeline

`.github/workflows/ci.yml` runs on every push and is organized into parallel jobs:

1. **Build image** — builds the `ci` target of `.docker/Dockerfile`, pushes/caches it against a branch tag, and shares it with downstream jobs via the GitHub Actions cache.
2. **Code style** — `composer lint` (Pint in `--test` mode).
3. **Static analysis** — `composer stan` (PHPStan with result cache restored from the `main` branch).
4. **Tests** — `php artisan migrate` followed by `php artisan test --coverage --min=80` against MySQL and Redis service containers. **Builds fail if coverage drops below 80%.**
5. **Docker push** — publishes the image to GHCR once all checks pass.
6. **Cleanup** — deletes the per-run image cache entry.

Required secrets: `COMPOSER_AUTH` (for Flux Pro) and `GITHUB_TOKEN` (provided by Actions).

## Project Layout Notes

- `.docker/` — Dockerfile (multi-stage: `development`, `ci`, etc.), entrypoint, Apache/PHP/supervisor config.
- `CLAUDE.md` — guidelines for AI coding agents; includes Laravel Boost rules and the Docker command convention.
- `boost.json` — Laravel Boost configuration.
- `phpstan.neon`, `pint.json`, `phpunit.xml` — quality-tool configuration.

## License

MIT.
