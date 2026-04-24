# Per-app dev tooling for the API.
# Infra (compose, up/down, logs, tests, migrations) lives at the monorepo root.

C := mybible-api

.DEFAULT_GOAL := help
.PHONY: help bash lint lint-fix stan

help:
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z0-9_-]+:.*?## / {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

bash: ## Shell into the container
	docker exec -it $(C) /bin/bash

lint: ## Pint --test
	docker exec $(C) composer lint

lint-fix: ## Pint fix
	docker exec $(C) composer lint-fix

stan: ## PHPStan
	docker exec $(C) composer stan
