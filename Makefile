# Convenience wrappers around docker compose. Every target is a plain command you can also run by hand.
COMPOSE ?= docker compose
APP     := $(COMPOSE) exec -T app
RUNAPP  := $(COMPOSE) run --rm --no-deps app

.DEFAULT_GOAL := help
.PHONY: smoke help setup build install up down logs ps migrate fresh test test-backend test-frontend \
        lint lint-backend lint-frontend format analyse typecheck build-frontend check

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*## ' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  %-16s %s\n", $$1, $$2}'

setup: ## Create .env with generated secrets (never overwrites)
	sh scripts/setup.sh

build: ## Build the PHP image
	$(COMPOSE) build

install: ## Install Composer dependencies
	$(RUNAPP) composer install --no-interaction

up: ## Start the whole stack
	$(COMPOSE) up -d --wait
	sh scripts/ensure-test-db.sh

down: ## Stop the stack (volumes are kept)
	$(COMPOSE) down

logs: ## Follow logs
	$(COMPOSE) logs -f --tail=100

ps: ## Show service status
	$(COMPOSE) ps

migrate: ## Run migrations on the DEVELOPMENT database
	$(APP) php artisan migrate

test-backend: ## Backend tests (PostgreSQL "<DB>_test" + Redis)
	sh scripts/ensure-test-db.sh
	$(APP) composer test

test-frontend: ## Frontend tests (Vitest)
	$(COMPOSE) exec -T frontend npm test

test: test-backend test-frontend ## All tests

smoke: ## Infrastructure smoke test against the running stack
	sh scripts/smoke-test.sh

analyse: ## PHPStan / Larastan
	$(APP) composer analyse

lint-backend: ## Laravel Pint (check only)
	$(APP) composer lint

lint-frontend: ## ESLint
	$(COMPOSE) exec -T frontend npm run lint

lint: lint-backend lint-frontend ## All linters

format: ## Auto-format backend (Pint)
	$(APP) composer format

typecheck: ## vue-tsc
	$(COMPOSE) exec -T frontend npm run typecheck

build-frontend: ## Production build of the frontend
	$(COMPOSE) exec -T frontend npm run build

check: lint analyse typecheck test build-frontend ## Everything CI runs
