# Convenience wrappers around docker compose. Every target is a plain command you can also run by hand.
COMPOSE ?= docker compose
APP     := $(COMPOSE) exec -T app
RUNAPP  := $(COMPOSE) run --rm --no-deps app

.DEFAULT_GOAL := help
.PHONY: smoke docs-check ci-check db-fresh-test db-reset-dev safety-test help setup build install up down logs ps migrate fresh test test-backend test-frontend \
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

docs-check: ## Markdown links/anchors + Mermaid syntax (needs python3 and docker)
	python3 scripts/check-docs.py
	docker run --rm -u 0 -e HOME=/tmp -v "$(CURDIR)/scripts:/src:ro" -v "$(CURDIR)/docs:/work/docs:ro" -w /work node:22-alpine \
	  sh -c 'npm init -y >/dev/null && npm i mermaid@12 jsdom@30 --no-audit --no-fund >/dev/null 2>&1 && cp /src/check-mermaid.mjs . && node check-mermaid.mjs /work'

ci-check: ## CI workflow + compose safety rules (python3)
	python3 scripts/check-ci.py
	python3 scripts/check-ci.py --selftest

db-fresh-test: ## Rebuild the disposable TEST database (restricted test role, *_test only)
	sh scripts/ensure-test-db.sh
	$(COMPOSE) exec -T app sh -c 'DB_DATABASE="$${DB_DATABASE}_test" DB_USERNAME="$$DB_TEST_USERNAME" DB_PASSWORD="$$DB_TEST_PASSWORD" php artisan migrate:fresh --force'

db-reset-dev: ## DESTRUCTIVE: reset the dev DB. Usage: make db-reset-dev CONFIRM=<db name> [WIPE_DATA=yes]
	sh scripts/db-reset-dev.sh

safety-test: ## Prove tests/commands cannot touch the dev DB (stack must run)
	sh scripts/safety-test.sh

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

check: lint analyse typecheck test build-frontend docs-check ci-check safety-test ## Everything CI runs
