# Makefile for FSM Advanced Architecture Project
SHELL := /bin/bash
.DEFAULT_GOAL := help
.PHONY: help

# Terminal colors for better UX
RED := \033[0;31m
GREEN := \033[0;32m
YELLOW := \033[1;33m
BLUE := \033[0;34m
CYAN := \033[0;36m
MAGENTA := \033[0;35m
NC := \033[0m # No Color

# Version and build information
VERSION := $(shell git describe --tags --always --dirty 2>/dev/null || echo "dev")
BUILD_DATE := $(shell date -u +'%Y-%m-%dT%H:%M:%SZ')
GIT_COMMIT := $(shell git rev-parse --short HEAD 2>/dev/null || echo "unknown")

# Environment detection
ENV ?= dev
ifeq ($(ENV),prod)
    COMPOSER_FLAGS := --no-dev --optimize-autoloader --classmap-authoritative --no-scripts
    PHP_FLAGS := -d memory_limit=256M -d max_execution_time=0
    XDEBUG_MODE := off
else ifeq ($(ENV),test)
    COMPOSER_FLAGS := --optimize-autoloader
    PHP_FLAGS := -d memory_limit=512M -d max_execution_time=0
    XDEBUG_MODE := coverage
else
    COMPOSER_FLAGS :=
    PHP_FLAGS :=
    XDEBUG_MODE := debug
endif

# Docker configuration
DOCKER_COMPOSE := docker compose
DOCKER_COMPOSE_FILE := docker/docker-compose.yml
DOCKER_COMPOSE_EXEC := $(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) exec -T
PHP_CONTAINER := php-fpm
NGINX_CONTAINER := nginx

# PHP executables with proper flags
PHP := $(DOCKER_COMPOSE_EXEC) -e XDEBUG_MODE=$(XDEBUG_MODE) $(PHP_CONTAINER) php $(PHP_FLAGS)
COMPOSER := $(DOCKER_COMPOSE_EXEC) $(PHP_CONTAINER) composer

# Application configuration
SERVER_PORT := 8080
NGINX_PORT := 80
WORKERS := 4
MAX_REQUEST := 1000

# File paths
SRC_DIR := src
TEST_DIR := tests
VENDOR_DIR := vendor
VAR_DIR := var
COVERAGE_DIR := coverage
DOCS_DIR := docs
BIN_DIR := bin
MAKE_DIR := make

# Create required directories
$(shell mkdir -p $(VAR_DIR)/cache $(VAR_DIR)/log $(VAR_DIR)/tmp $(COVERAGE_DIR) $(DOCS_DIR))

# Include additional makefiles if they exist
-include $(MAKE_DIR)/*.mk

##@ General

help: ## Display this help message
	@echo ""
	@echo "${CYAN}FSM Application - Advanced Makefile${NC}"
	@echo "${CYAN}===================================${NC}"
	@echo ""
	@echo "Version: ${GREEN}$(VERSION)${NC}"
	@echo "Environment: ${YELLOW}$(ENV)${NC}"
	@echo "Build Date: ${BLUE}$(BUILD_DATE)${NC}"
	@echo "Git Commit: ${MAGENTA}$(GIT_COMMIT)${NC}"
	@echo ""
	@awk 'BEGIN {FS = ":.*##"; printf "Usage:\n  make ${GREEN}<target>${NC}\n\n"} /^[a-zA-Z_0-9-]+:.*?##/ { printf "  ${GREEN}%-20s${NC} %s\n", $$1, $$2 } /^##@/ { printf "\n${YELLOW}%s${NC}\n", substr($$0, 5) } ' $(MAKEFILE_LIST)
	@echo ""
	@echo "Examples:"
	@echo "  ${BLUE}make install${NC}         - Complete project setup"
	@echo "  ${BLUE}make dev${NC}             - Start development environment"
	@echo "  ${BLUE}make test${NC}            - Run all tests"
	@echo "  ${BLUE}make quality${NC}         - Run quality checks"
	@echo "  ${BLUE}make ENV=prod build${NC}  - Build for production"
	@echo ""

.PHONY: version
version: ## Display version information
	@echo "Version: ${GREEN}$(VERSION)${NC}"
	@echo "Build Date: ${BLUE}$(BUILD_DATE)${NC}"
	@echo "Git Commit: ${MAGENTA}$(GIT_COMMIT)${NC}"
	@echo "Environment: ${YELLOW}$(ENV)${NC}"
	@echo "PHP Flags: ${CYAN}$(PHP_FLAGS)${NC}"
	@echo "Composer Flags: ${CYAN}$(COMPOSER_FLAGS)${NC}"

##@ Setup & Installation

.PHONY: check-requirements
check-requirements: ## Check system requirements
	@echo "${BLUE}🔍 Checking requirements...${NC}"
	@command -v docker >/dev/null 2>&1 || { echo "${RED}❌ Docker is required but not installed.${NC}" >&2; exit 1; }
	@command -v docker-compose >/dev/null 2>&1 || command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1 || { echo "${RED}❌ Docker Compose is required but not installed.${NC}" >&2; exit 1; }
	@command -v make >/dev/null 2>&1 || { echo "${RED}❌ Make is required but not installed.${NC}" >&2; exit 1; }
	@command -v git >/dev/null 2>&1 || { echo "${RED}❌ Git is required but not installed.${NC}" >&2; exit 1; }
	@echo "${GREEN}✅ All requirements met!${NC}"
	@echo "${CYAN}ℹ️  Docker: $$(docker --version)${NC}"
	@echo "${CYAN}ℹ️  Docker Compose: $$($(DOCKER_COMPOSE) --version)${NC}"
	@echo "${CYAN}ℹ️  Make: $$(make --version | head -n1)${NC}"

.PHONY: install
install: check-requirements ## Complete project setup
	@echo "${BLUE}🚀 Setting up FSM project...${NC}"
	@echo "${CYAN}📋 Installation Steps:${NC}"
	@echo "  1. Clean Docker environment"
	@echo "  2. Build Docker containers"
	@echo "  3. Install dependencies"
	@echo "  4. Set up environment"
	@echo "  5. Fix permissions"
	@echo ""
	@$(MAKE) docker-clean
	@$(MAKE) docker-build
	@$(MAKE) composer-install
	@$(MAKE) env-setup
	@$(MAKE) permissions
	@$(MAKE) docker-up
	@echo ""
	@echo "${GREEN}✅ Setup complete!${NC}"
	@echo ""
	@echo "${CYAN}Next steps:${NC}"
	@echo "  ${BLUE}make dev${NC}        - Start development environment"
	@echo "  ${BLUE}make test${NC}       - Run tests to verify installation"
	@echo "  ${BLUE}make server${NC}     - Start Workerman API server"
	@echo ""

.PHONY: env-setup
env-setup: ## Set up environment files
	@echo "${BLUE}📝 Setting up environment...${NC}"
	@if [ ! -f .env ]; then \
		if [ -f .env.example ]; then \
			echo "${CYAN}Creating .env from .env.example...${NC}"; \
			cp .env.example .env; \
		else \
			echo "${CYAN}Creating default .env file...${NC}"; \
			echo "# FSM Application Environment" > .env; \
			echo "ENV=dev" >> .env; \
			echo "DEBUG=true" >> .env; \
			echo "NGINX_HOST_HTTP_PORT=8080" >> .env; \
			echo "PUID=1000" >> .env; \
			echo "PGID=1000" >> .env; \
			echo "INSTALL_XDEBUG=true" >> .env; \
			echo "PHP_IDE_CONFIG=serverName=localhost" >> .env; \
		fi; \
		echo "${GREEN}✅ .env file created${NC}"; \
	else \
		echo "${YELLOW}⚠️  .env file already exists${NC}"; \
	fi

.PHONY: permissions
permissions: ## Fix file permissions
	@echo "${BLUE}🔧 Fixing permissions...${NC}"
	@mkdir -p $(VAR_DIR)/cache $(VAR_DIR)/log $(VAR_DIR)/tmp $(COVERAGE_DIR) $(BIN_DIR)
	@chmod -R 755 $(BIN_DIR) 2>/dev/null || true
	@chmod -R 777 $(VAR_DIR) $(COVERAGE_DIR) 2>/dev/null || true
	@find $(BIN_DIR) -name "*.php" -exec chmod +x {} \; 2>/dev/null || true
	@echo "${GREEN}✅ Permissions fixed!${NC}"

##@ Docker Management

.PHONY: docker-build
docker-build: ## Build Docker containers
	@echo "${BLUE}🔨 Building Docker containers...${NC}"
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) build --pull --no-cache --parallel
	@echo "${GREEN}✅ Docker build complete!${NC}"

.PHONY: docker-up
docker-up: ## Start Docker containers
	@echo "${BLUE}🚀 Starting Docker containers...${NC}"
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) up -d --remove-orphans
	@$(MAKE) docker-wait
	@echo "${GREEN}✅ Containers started!${NC}"
	@echo ""
	@echo "${CYAN}Services available:${NC}"
	@echo "  - Web Server: ${GREEN}http://localhost:$(NGINX_PORT)${NC}"
	@echo "  - API Health: ${GREEN}http://localhost:$(NGINX_PORT)/health${NC}"
	@echo ""
	@$(MAKE) docker-status

.PHONY: docker-down
docker-down: ## Stop Docker containers
	@echo "${BLUE}🛑 Stopping Docker containers...${NC}"
	@$(MAKE) server-stop 2>/dev/null || true
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) down --remove-orphans
	@echo "${GREEN}✅ Containers stopped!${NC}"

.PHONY: docker-restart
docker-restart: docker-down docker-up ## Restart Docker containers

.PHONY: docker-wait
docker-wait: ## Wait for services to be ready
	@echo "${BLUE}⏳ Waiting for services...${NC}"
	@printf "Checking PHP container"
	@until $(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) exec -T $(PHP_CONTAINER) php -v > /dev/null 2>&1; do \
		printf "."; \
		sleep 1; \
	done
	@echo ""
	@printf "Checking Nginx container"
	@until $(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) exec -T $(NGINX_CONTAINER) nginx -v > /dev/null 2>&1; do \
		printf "."; \
		sleep 1; \
	done
	@echo ""
	@echo "${GREEN}✅ All services ready!${NC}"

.PHONY: docker-logs
docker-logs: ## Show Docker logs
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) logs -f --tail=100

.PHONY: docker-logs-php
docker-logs-php: ## Show PHP container logs
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) logs -f $(PHP_CONTAINER)

.PHONY: docker-logs-nginx
docker-logs-nginx: ## Show Nginx container logs
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) logs -f $(NGINX_CONTAINER)

.PHONY: docker-status
docker-status: ## Show container status
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) ps
	@echo ""
	@echo "${CYAN}Container Resources:${NC}"
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) top 2>/dev/null || true

.PHONY: docker-clean
docker-clean: ## Clean Docker resources
	@echo "${BLUE}🧹 Cleaning Docker resources...${NC}"
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) down -v --remove-orphans --rmi local 2>/dev/null || true
	@docker system prune -f --volumes 2>/dev/null || true
	@echo "${GREEN}✅ Docker cleaned!${NC}"

.PHONY: docker-stats
docker-stats: ## Show Docker container statistics
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) stats --no-stream

##@ Development

.PHONY: dev
dev: docker-up ## Start development environment
	@echo "${GREEN}🎉 Development environment ready!${NC}"
	@echo ""
	@echo "${CYAN}Available services:${NC}"
	@echo "  📡 API Server: ${GREEN}http://localhost:$(NGINX_PORT)${NC}"
	@echo "  🏥 Health Check: ${GREEN}http://localhost:$(NGINX_PORT)/health${NC}"
	@echo "  📚 API Documentation: ${GREEN}http://localhost:$(NGINX_PORT)/docs${NC}"
	@echo ""
	@echo "${CYAN}Quick start commands:${NC}"
	@echo "  ${BLUE}make server${NC}          - Start Workerman API server"
	@echo "  ${BLUE}make test${NC}            - Run all tests"
	@echo "  ${BLUE}make quality${NC}         - Run quality checks"
	@echo "  ${BLUE}make watch${NC}           - Watch for file changes"
	@echo "  ${BLUE}make shell${NC}           - Access container shell"
	@echo ""
	@echo "${CYAN}Development workflow:${NC}"
	@echo "  1. ${BLUE}make server-daemon${NC}  - Start API in background"
	@echo "  2. ${BLUE}make test-watch${NC}     - Run tests continuously"
	@echo "  3. Edit your code"
	@echo "  4. ${BLUE}make quality${NC}        - Check code quality"

.PHONY: server
server: docker-up ## Start Workerman server
	@echo "${BLUE}🚀 Starting Workerman server...${NC}"
	@echo "${CYAN}Server configuration:${NC}"
	@echo "  Port: $(SERVER_PORT)"
	@echo "  Workers: $(WORKERS)"
	@echo "  Max Requests: $(MAX_REQUEST)"
	@echo ""
	@$(DOCKER_COMPOSE_EXEC) $(PHP_CONTAINER) php bin/server.php start

.PHONY: server-daemon
server-daemon: docker-up ## Start Workerman server as daemon
	@echo "${BLUE}🚀 Starting Workerman server (daemon)...${NC}"
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) exec -d $(PHP_CONTAINER) php bin/server.php start -d
	@sleep 2
	@$(MAKE) server-status
	@echo "${GREEN}✅ Server started in background${NC}"

.PHONY: server-stop
server-stop: ## Stop Workerman server
	@echo "${BLUE}🛑 Stopping Workerman server...${NC}"
	@$(DOCKER_COMPOSE_EXEC) $(PHP_CONTAINER) php bin/server.php stop 2>/dev/null || echo "${YELLOW}⚠️  Server was not running${NC}"
	@echo "${GREEN}✅ Server stopped${NC}"

.PHONY: server-reload
server-reload: ## Reload Workerman server
	@echo "${BLUE}🔄 Reloading Workerman server...${NC}"
	@$(DOCKER_COMPOSE_EXEC) $(PHP_CONTAINER) php bin/server.php reload || echo "${YELLOW}⚠️  Server not running, starting fresh...${NC}" && $(MAKE) server-daemon

.PHONY: server-status
server-status: ## Check Workerman server status
	@echo "${BLUE}📊 Checking server status...${NC}"
	@$(DOCKER_COMPOSE_EXEC) $(PHP_CONTAINER) php bin/server.php status 2>/dev/null || echo "${YELLOW}⚠️  Server not running${NC}"

.PHONY: server-restart
server-restart: server-stop server-daemon ## Restart Workerman server

.PHONY: watch
watch: ## Watch for file changes and restart server
	@echo "${BLUE}👀 Watching for changes...${NC}"
	@echo "${YELLOW}Press Ctrl+C to stop${NC}"
	@echo "${CYAN}Watching directories: $(SRC_DIR), $(BIN_DIR)${NC}"
	@if command -v inotifywait >/dev/null 2>&1; then \
		while true; do \
			inotifywait -r -e modify,create,delete,move $(SRC_DIR) $(BIN_DIR) 2>/dev/null || sleep 2; \
			echo "${BLUE}🔄 Changes detected, reloading server...${NC}"; \
			$(MAKE) server-reload; \
		done; \
	else \
		echo "${YELLOW}⚠️  inotifywait not available, using simple polling...${NC}"; \
		while true; do \
			sleep 5; \
			$(MAKE) server-reload; \
		done; \
	fi

.PHONY: shell
shell: docker-up ## Access PHP container shell
	@echo "${BLUE}🐚 Opening PHP container shell...${NC}"
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) exec $(PHP_CONTAINER) bash

.PHONY: shell-nginx
shell-nginx: docker-up ## Access Nginx container shell
	@echo "${BLUE}🐚 Opening Nginx container shell...${NC}"
	@$(DOCKER_COMPOSE) -f $(DOCKER_COMPOSE_FILE) exec $(NGINX_CONTAINER) bash

.PHONY: composer-install
composer-install: docker-up ## Install PHP dependencies
	@echo "${BLUE}📦 Installing Composer dependencies...${NC}"
	@$(COMPOSER) install $(COMPOSER_FLAGS) --verbose
	@echo "${GREEN}✅ Dependencies installed!${NC}"

.PHONY: composer-update
composer-update: docker-up ## Update PHP dependencies
	@echo "${BLUE}📦 Updating Composer dependencies...${NC}"
	@$(COMPOSER) update --verbose
	@$(COMPOSER) show --outdated --direct 2>/dev/null || true
	@echo "${GREEN}✅ Dependencies updated!${NC}"

.PHONY: composer-outdated
composer-outdated: docker-up ## Show outdated dependencies
	@echo "${BLUE}📊 Checking for outdated dependencies...${NC}"
	@$(COMPOSER) outdated --direct --format=table

.PHONY: composer-validate
composer-validate: docker-up ## Validate composer.json
	@echo "${BLUE}🔍 Validating composer.json...${NC}"
	@$(COMPOSER) validate --strict --no-check-all

##@ Testing

.PHONY: test
test: docker-up ## Run all tests
	@echo "${BLUE}🧪 Running all tests...${NC}"
	@echo "${CYAN}Test Suite Overview:${NC}"
	@echo "  1. Unit tests"
	@echo "  2. Integration tests"  
	@echo "  3. Feature tests"
	@echo "  4. Property tests"
	@echo ""
	@$(MAKE) test-unit
	@$(MAKE) test-integration
	@$(MAKE) test-feature
	@$(MAKE) test-property
	@echo ""
	@echo "${GREEN}✅ All test suites completed!${NC}"

.PHONY: test-unit
test-unit: docker-up ## Run unit tests
	@echo "${BLUE}🧪 Running unit tests...${NC}"
	@$(PHP) vendor/bin/phpunit --testsuite=unit --colors=always --verbose

.PHONY: test-integration
test-integration: docker-up ## Run integration tests
	@echo "${BLUE}🧪 Running integration tests...${NC}"
	@$(PHP) vendor/bin/phpunit --testsuite=integration --colors=always --verbose

.PHONY: test-feature
test-feature: docker-up ## Run feature tests
	@echo "${BLUE}🧪 Running feature tests...${NC}"
	@$(PHP) vendor/bin/phpunit --testsuite=feature --colors=always --verbose

.PHONY: test-property
test-property: docker-up ## Run property-based tests
	@echo "${BLUE}🎲 Running property tests...${NC}"
	@$(PHP) vendor/bin/phpunit --group=property --colors=always --verbose

.PHONY: test-coverage
test-coverage: docker-up ## Generate test coverage report
	@echo "${BLUE}📊 Generating coverage report...${NC}"
	@echo "${CYAN}This may take a few minutes...${NC}"
	@XDEBUG_MODE=coverage $(PHP) vendor/bin/phpunit --coverage-html=$(COVERAGE_DIR)/html --coverage-text --coverage-xml=$(COVERAGE_DIR)/xml --log-junit=$(COVERAGE_DIR)/junit.xml
	@echo ""
	@echo "${GREEN}✅ Coverage report generated!${NC}"
	@echo "  📄 HTML: ${CYAN}$(COVERAGE_DIR)/html/index.html${NC}"
	@echo "  📄 XML: ${CYAN}$(COVERAGE_DIR)/xml${NC}"

.PHONY: test-coverage-check
test-coverage-check: docker-up ## Check test coverage meets requirements
	@echo "${BLUE}📊 Checking coverage requirements...${NC}"
	@XDEBUG_MODE=coverage $(PHP) vendor/bin/phpunit --coverage-text --colors=never | \
		grep -E "^\s*Lines:" | \
		awk '{gsub(/%.*/, "", $$2); if($$2 < 95) {printf "❌ Coverage too low: %.1f%% (minimum: 95%%)\n", $$2; exit 1} else {printf "✅ Coverage OK: %.1f%%\n", $$2}}'

.PHONY: test-mutation
test-mutation: docker-up ## Run mutation tests
	@echo "${BLUE}🧬 Running mutation tests...${NC}"
	@echo "${CYAN}This will take several minutes...${NC}"
	@$(PHP) vendor/bin/infection --show-mutations --min-msi=80 --min-covered-msi=90 --threads=4 --ansi

.PHONY: test-watch
test-watch: docker-up ## Run tests in watch mode
	@echo "${BLUE}👀 Watching tests...${NC}"
	@echo "${YELLOW}Press Ctrl+C to stop${NC}"
	@if [ -f vendor/bin/phpunit-watcher ]; then \
		$(PHP) vendor/bin/phpunit-watcher watch; \
	else \
		echo "${YELLOW}⚠️  phpunit-watcher not available, using simple watch...${NC}"; \
		while true; do \
			$(MAKE) test-unit; \
			sleep 5; \
		done; \
	fi

.PHONY: test-parallel
test-parallel: docker-up ## Run tests in parallel
	@echo "${BLUE}🚀 Running tests in parallel...${NC}"
	@if [ -f vendor/bin/paratest ]; then \
		$(PHP) vendor/bin/paratest --colors --processes=4; \
	else \
		echo "${YELLOW}⚠️  paratest not available, running sequential tests...${NC}"; \
		$(MAKE) test; \
	fi

.PHONY: benchmark
benchmark: docker-up ## Run performance benchmarks
	@echo "${BLUE}⚡ Running benchmarks...${NC}"
	@$(PHP) vendor/bin/phpbench run --report=aggregate --retry-threshold=5 --ansi

.PHONY: benchmark-compare
benchmark-compare: docker-up ## Compare benchmark results
	@echo "${BLUE}📊 Comparing benchmarks...${NC}"
	@$(PHP) vendor/bin/phpbench run --report=aggregate --report=compare --ansi

##@ Code Quality

.PHONY: quality
quality: ## Run all quality checks
	@echo "${BLUE}🔍 Running comprehensive quality checks...${NC}"
	@echo "${CYAN}Quality Check Overview:${NC}"
	@echo "  1. PHP syntax check"
	@echo "  2. Code style check"
	@echo "  3. PHPStan static analysis"
	@echo "  4. Psalm static analysis"
	@echo "  5. Security vulnerability check"
	@echo ""
	@$(MAKE) lint
	@$(MAKE) cs-check
	@$(MAKE) stan
	@$(MAKE) psalm
	@$(MAKE) security-check
	@echo ""
	@echo "${GREEN}✅ All quality checks passed!${NC}"

.PHONY: verify-level5
verify-level5: ## Verify Level 5 quality standards
	@echo "${BLUE}🔍 Running Level 5 Quality Verification...${NC}"
	@$(PHP) bin/verify-level5.php

.PHONY: quality-dashboard
quality-dashboard: ## Open quality dashboard
	@echo "${BLUE}📊 Starting Quality Dashboard...${NC}"
	@if [ -f bin/quality-dashboard.php ]; then \
		$(PHP) bin/quality-dashboard.php; \
	else \
		echo "${YELLOW}⚠️  Dashboard not available${NC}"; \
	fi

.PHONY: quality-fix
quality-fix: ## Fix all auto-fixable quality issues
	@echo "${BLUE}🔧 Fixing quality issues...${NC}"
	@$(MAKE) cs-fix
	@echo "${GREEN}✅ Auto-fixable issues resolved!${NC}"
	@echo "${CYAN}ℹ️  Run 'make quality' to check remaining issues${NC}"

.PHONY: lint
lint: docker-up ## Lint PHP files for syntax errors
	@echo "${BLUE}🔍 Linting PHP files...${NC}"
	@find $(SRC_DIR) $(TEST_DIR) -name "*.php" -print0 | xargs -0 -n1 -P4 $(PHP) -l > /dev/null 2>&1 && \
		echo "${GREEN}✅ No syntax errors found!${NC}" || \
		{ echo "${RED}❌ Syntax errors found!${NC}"; exit 1; }

.PHONY: cs-check
cs-check: docker-up ## Check code style
	@echo "${BLUE}🎨 Checking code style...${NC}"
	@$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff --verbose --show-progress=dots

.PHONY: cs-fix
cs-fix: docker-up ## Fix code style
	@echo "${BLUE}🎨 Fixing code style...${NC}"
	@$(PHP) vendor/bin/php-cs-fixer fix --verbose --show-progress=dots
	@echo "${GREEN}✅ Code style fixed!${NC}"

.PHONY: cs-check-psr12
cs-check-psr12: docker-up ## Check PSR-12 compliance
	@echo "${BLUE}📏 Checking PSR-12 compliance...${NC}"
	@$(PHP) vendor/bin/phpcs --standard=PSR12 --colors --report=summary $(SRC_DIR) $(TEST_DIR)

.PHONY: cs-fix-psr12
cs-fix-psr12: docker-up ## Fix PSR-12 violations
	@echo "${BLUE}📏 Fixing PSR-12 violations...${NC}"
	@$(PHP) vendor/bin/phpcbf --standard=PSR12 $(SRC_DIR) $(TEST_DIR) || true
	@echo "${GREEN}✅ PSR-12 violations fixed!${NC}"

.PHONY: stan
stan: docker-up ## Run PHPStan static analysis
	@echo "${BLUE}🔬 Running PHPStan analysis...${NC}"
	@$(PHP) vendor/bin/phpstan analyse --memory-limit=1G --ansi --verbose

.PHONY: stan-baseline
stan-baseline: docker-up ## Generate PHPStan baseline
	@echo "${BLUE}📝 Generating PHPStan baseline...${NC}"
	@$(PHP) vendor/bin/phpstan analyse --generate-baseline --memory-limit=1G
	@echo "${GREEN}✅ PHPStan baseline generated!${NC}"

.PHONY: psalm
psalm: docker-up ## Run Psalm static analysis
	@echo "${BLUE}🔬 Running Psalm analysis...${NC}"
	@$(PHP) vendor/bin/psalm --show-info=true --stats --long-progress

.PHONY: psalm-baseline
psalm-baseline: docker-up ## Generate Psalm baseline
	@echo "${BLUE}📝 Generating Psalm baseline...${NC}"
	@$(PHP) vendor/bin/psalm --set-baseline=psalm-baseline.xml
	@echo "${GREEN}✅ Psalm baseline generated!${NC}"

.PHONY: security-check
security-check: docker-up ## Check for security vulnerabilities
	@echo "${BLUE}🔒 Checking security vulnerabilities...${NC}"
	@$(COMPOSER) audit --format=table
	@echo "${GREEN}✅ Security check completed!${NC}"

.PHONY: quality-report
quality-report: docker-up ## Generate comprehensive quality report
	@echo "${BLUE}📊 Generating quality report...${NC}"
	@if [ -f $(BIN_DIR)/quality-check.php ]; then \
		$(PHP) $(BIN_DIR)/quality-check.php; \
	else \
		echo "${YELLOW}⚠️  Quality check script not found, creating basic report...${NC}"; \
		echo "Quality Report - $(shell date)"; \
		echo "============================"; \
		$(MAKE) lint 2>&1 | grep -E "(✅|❌)" || true; \
		$(MAKE) cs-check 2>&1 | grep -E "(✅|❌)" || true; \
		$(MAKE) stan 2>&1 | grep -E "(✅|❌)" || true; \
		$(MAKE) psalm 2>&1 | grep -E "(✅|❌)" || true; \
	fi

##@ Documentation

.PHONY: docs-generate
docs-generate: docker-up ## Generate API documentation
	@echo "${BLUE}📚 Generating documentation...${NC}"
	@mkdir -p $(DOCS_DIR)
	@if [ -f $(BIN_DIR)/generate-docs.php ]; then \
		$(PHP) $(BIN_DIR)/generate-docs.php; \
	else \
		echo "${YELLOW}⚠️  Documentation generator not found${NC}"; \
		echo "Creating basic documentation structure..."; \
		echo "# FSM API Documentation" > $(DOCS_DIR)/README.md; \
		echo "Generated on: $(shell date)" >> $(DOCS_DIR)/README.md; \
	fi
	@echo "${GREEN}✅ Documentation generated!${NC}"

.PHONY: docs-serve
docs-serve: docs-generate ## Serve documentation locally
	@echo "${BLUE}📚 Serving documentation...${NC}"
	@echo "${CYAN}Documentation available at: ${GREEN}http://localhost:8081${NC}"
	@echo "${YELLOW}Press Ctrl+C to stop${NC}"
	@if command -v python3 >/dev/null 2>&1; then \
		cd $(DOCS_DIR) && python3 -m http.server 8081; \
	elif command -v python >/dev/null 2>&1; then \
		cd $(DOCS_DIR) && python -m SimpleHTTPServer 8081; \
	else \
		echo "${RED}❌ Python not available for serving docs${NC}"; \
		exit 1; \
	fi

.PHONY: docs-clean
docs-clean: ## Clean generated documentation
	@echo "${BLUE}🧹 Cleaning documentation...${NC}"
	@rm -rf $(DOCS_DIR)/*
	@echo "${GREEN}✅ Documentation cleaned!${NC}"

.PHONY: openapi-validate
openapi-validate: ## Validate OpenAPI specification
	@echo "${BLUE}📋 Validating OpenAPI specification...${NC}"
	@if [ -f $(DOCS_DIR)/api/openapi.yaml ]; then \
		if command -v npx >/dev/null 2>&1; then \
			npx @apidevtools/swagger-cli validate $(DOCS_DIR)/api/openapi.yaml; \
			echo "${GREEN}✅ OpenAPI specification is valid!${NC}"; \
		else \
			echo "${YELLOW}⚠️  Node.js/npx not available for OpenAPI validation${NC}"; \
		fi; \
	else \
		echo "${YELLOW}⚠️  OpenAPI specification not found at $(DOCS_DIR)/api/openapi.yaml${NC}"; \
	fi

##@ CI/CD & Production

.PHONY: ci
ci: ## Run full CI pipeline locally
	@echo "${BLUE}🚀 Running CI pipeline...${NC}"
	@echo "${CYAN}CI Pipeline Steps:${NC}"
	@echo "  1. Requirements check"
	@echo "  2. Code quality checks"
	@echo "  3. Security audit"
	@echo "  4. Full test suite"
	@echo "  5. Coverage validation"
	@echo ""
	@$(MAKE) check-requirements
	@$(MAKE) quality
	@$(MAKE) test
	@$(MAKE) test-coverage-check
	@$(MAKE) security-check
	@echo ""
	@echo "${GREEN}✅ CI pipeline completed successfully!${NC}"

.PHONY: ci-fast
ci-fast: ## Run fast CI checks (no coverage)
	@echo "${BLUE}⚡ Running fast CI checks...${NC}"
	@$(MAKE) lint
	@$(MAKE) cs-check
	@$(MAKE) test-unit
	@echo "${GREEN}✅ Fast CI checks completed!${NC}"

.PHONY: build-prod
build-prod: ## Build for production
	@echo "${BLUE}🏗️  Building for production...${NC}"
	@echo "${CYAN}Production Build Steps:${NC}"
	@echo "  1. Clean environment"
	@echo "  2. Install production dependencies"
	@echo "  3. Optimize autoloader"
	@echo "  4. Clear caches"
	@echo ""
	@ENV=prod $(MAKE) clean
	@ENV=prod $(MAKE) composer-install
	@$(PHP) vendor/bin/composer dump-autoload --optimize --classmap-authoritative
	@$(MAKE) cache-clear
	@echo ""
	@echo "${GREEN}✅ Production build complete!${NC}"

.PHONY: deploy-check
deploy-check: ## Pre-deployment checks
	@echo "${BLUE}🔍 Running pre-deployment checks...${NC}"
	@$(MAKE) ci
	@$(MAKE) build-prod
	@echo "${GREEN}✅ Ready for deployment!${NC}"

.PHONY: deploy
deploy: deploy-check ## Deploy to production
	@echo "${BLUE}🚀 Deploying to production...${NC}"
	@echo "${YELLOW}⚠️  Implement your deployment strategy here${NC}"
	@echo "Deployment steps:"
	@echo "  1. Build production artifacts"
	@echo "  2. Run final checks"
	@echo "  3. Deploy to target environment"
	@echo "  4. Verify deployment"
	@echo ""
	@echo "${GREEN}✅ Deployment prepared!${NC}"

##@ Utilities

.PHONY: clean
clean: ## Clean generated files and caches
	@echo "${BLUE}🧹 Cleaning generated files...${NC}"
	@rm -rf $(VAR_DIR)/cache/* $(VAR_DIR)/log/* $(VAR_DIR)/tmp/*
	@rm -rf $(COVERAGE_DIR)/*
	@rm -f .phpunit.result.cache
	@rm -f .php-cs-fixer.cache
	@rm -rf .phpstan/
	@$(COMPOSER) clear-cache 2>/dev/null || true
	@echo "${GREEN}✅ Cleanup complete!${NC}"

.PHONY: clean-all
clean-all: clean docker-clean ## Clean everything including Docker resources
	@echo "${GREEN}✅ Full cleanup complete!${NC}"

.PHONY: cache-clear
cache-clear: docker-up ## Clear application cache
	@echo "${BLUE}🧹 Clearing application cache...${NC}"
	@rm -rf $(VAR_DIR)/cache/*
	@echo "${GREEN}✅ Cache cleared!${NC}"

.PHONY: logs
logs: ## Show application logs (live tail)
	@echo "${BLUE}📋 Showing application logs...${NC}"
	@echo "${YELLOW}Press Ctrl+C to stop${NC}"
	@tail -f $(VAR_DIR)/log/*.log 2>/dev/null || echo "${YELLOW}No log files found in $(VAR_DIR)/log/${NC}"

.PHONY: logs-show
logs-show: ## Show recent application logs
	@echo "${BLUE}📋 Recent application logs:${NC}"
	@find $(VAR_DIR)/log -name "*.log" -type f -exec echo "=== {} ===" \; -exec tail -20 {} \; 2>/dev/null || echo "${YELLOW}No log files found${NC}"

.PHONY: logs-clear
logs-clear: ## Clear application logs
	@echo "${BLUE}🧹 Clearing application logs...${NC}"
	@rm -f $(VAR_DIR)/log/*.log
	@echo "${GREEN}✅ Logs cleared!${NC}"

.PHONY: health
health: ## Check application health
	@echo "${BLUE}🏥 Checking application health...${NC}"
	@curl -s -f http://localhost:$(NGINX_PORT)/health 2>/dev/null | \
		(command -v jq >/dev/null 2>&1 && jq . || cat) || \
		echo "${RED}❌ Health check failed - service may be unavailable${NC}"

.PHONY: info
info: ## Show project information
	@echo "${CYAN}FSM Project Information${NC}"
	@echo "${CYAN}=======================${NC}"
	@echo "Version: ${GREEN}$(VERSION)${NC}"
	@echo "Environment: ${YELLOW}$(ENV)${NC}"
	@echo "Build Date: ${BLUE}$(BUILD_DATE)${NC}"
	@echo "Git Commit: ${MAGENTA}$(GIT_COMMIT)${NC}"
	@echo ""
	@echo "${CYAN}Paths:${NC}"
	@echo "  Source: $(SRC_DIR)"
	@echo "  Tests: $(TEST_DIR)"
	@echo "  Docs: $(DOCS_DIR)"
	@echo "  Var: $(VAR_DIR)"
	@echo "  Coverage: $(COVERAGE_DIR)"
	@echo ""
	@echo "${CYAN}Configuration:${NC}"
	@echo "  PHP Flags: $(PHP_FLAGS)"
	@echo "  Composer Flags: $(COMPOSER_FLAGS)"
	@echo "  XDebug Mode: $(XDEBUG_MODE)"
	@echo "  Server Port: $(SERVER_PORT)"
	@echo ""

.PHONY: metrics
metrics: ## Show application metrics
	@echo "${BLUE}📊 Application metrics:${NC}"
	@if curl -s -f http://localhost:$(NGINX_PORT)/metrics >/dev/null 2>&1; then \
		curl -s http://localhost:$(NGINX_PORT)/metrics | \
			(command -v jq >/dev/null 2>&1 && jq . || cat); \
	else \
		echo "${YELLOW}⚠️  Metrics endpoint not available${NC}"; \
		echo "Container stats:"; \
		$(MAKE) docker-stats; \
	fi

##@ Performance & Monitoring

.PHONY: load-test
load-test: docker-up ## Run load tests
	@echo "${BLUE}⚡ Running load tests...${NC}"
	@if ! command -v ab >/dev/null 2>&1; then \
		echo "${RED}❌ Apache Bench (ab) is required for load testing${NC}"; \
		echo "Install with: apt-get install apache2-utils"; \
		exit 1; \
	fi
	@echo "${CYAN}Load test configuration:${NC}"
	@echo "  Requests: 1000"
	@echo "  Concurrency: 10"
	@echo "  Target: http://localhost:$(NGINX_PORT)/health"
	@echo ""
	@ab -n 1000 -c 10 -g load-test-results.tsv http://localhost:$(NGINX_PORT)/health
	@echo ""
	@echo "${GREEN}✅ Load test completed!${NC}"
	@echo "Results saved to: load-test-results.tsv"

.PHONY: stress-test
stress-test: docker-up ## Run stress tests
	@echo "${BLUE}💪 Running stress tests...${NC}"
	@if ! command -v siege >/dev/null 2>&1; then \
		echo "${RED}❌ Siege is required for stress testing${NC}"; \
		echo "Install with: apt-get install siege"; \
		exit 1; \
	fi
	@echo "${CYAN}Stress test configuration:${NC}"
	@echo "  Concurrent users: 50"
	@echo "  Duration: 60 seconds"
	@echo "  Target: http://localhost:$(NGINX_PORT)/health"
	@echo ""
	@siege -c 50 -t 60s http://localhost:$(NGINX_PORT)/health
	@echo ""
	@echo "${GREEN}✅ Stress test completed!${NC}"

.PHONY: profile
profile: docker-up ## Profile application performance
	@echo "${BLUE}🔥 Starting performance profiling...${NC}"
	@if $(DOCKER_COMPOSE_EXEC) $(PHP_CONTAINER) which blackfire >/dev/null 2>&1; then \
		echo "Using Blackfire profiler..."; \
		$(DOCKER_COMPOSE_EXEC) $(PHP_CONTAINER) blackfire run php bin/server.php; \
	elif $(DOCKER_COMPOSE_EXEC) $(PHP_CONTAINER) php -m | grep -q xdebug; then \
		echo "Using XDebug profiler..."; \
		XDEBUG_MODE=profile $(PHP) bin/server.php; \
	else \
		echo "${YELLOW}⚠️  No profiler available${NC}"; \
		echo "Consider installing Blackfire or enabling XDebug"; \
	fi

.PHONY: monitor
monitor: ## Monitor system resources
	@echo "${BLUE}📊 Monitoring system resources...${NC}"
	@echo "${YELLOW}Press Ctrl+C to stop${NC}"
	@while true; do \
		clear; \
		echo "${CYAN}=== System Monitor ===${NC}"; \
		date; \
		echo ""; \
		$(MAKE) docker-stats --silent; \
		echo ""; \
		echo "${CYAN}=== Application Health ===${NC}"; \
		$(MAKE) health --silent 2>/dev/null || echo "${RED}Service unavailable${NC}"; \
		sleep 5; \
	done

# Self-documentation check
.PHONY: makefile-check
makefile-check: ## Validate Makefile syntax and best practices
	@echo "${BLUE}🔍 Checking Makefile...${NC}"
	@echo "${CYAN}Checking for common issues:${NC}"
	@if grep -n "^[[:space:]]\+[^[:space:]]" $(MAKEFILE_LIST) | grep -v "^\#"; then \
		echo "${RED}❌ Found lines with leading spaces (should use tabs)${NC}"; \
		exit 1; \
	fi
	@if ! grep -q "^\.PHONY:" $(MAKEFILE_LIST); then \
		echo "${YELLOW}⚠️  No .PHONY declarations found${NC}"; \
	fi
	@echo "${GREEN}✅ Makefile syntax check passed!${NC}"

# Include timestamp in default output
.PHONY: timestamp
timestamp: ## Show current timestamp
	@echo "Current time: ${CYAN}$(shell date -u +'%Y-%m-%d %H:%M:%S UTC')${NC}"