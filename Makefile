# CaiTong Docker Management Makefile (Azure PostgreSQL)

.PHONY: help dev-up dev-down dev-restart dev-logs dev-shell test clean

# Default target
help: ## Show this help message
	@echo "CaiTong Docker Management (Azure PostgreSQL)"
	@echo "==========================================="
	@echo ""
	@echo "Available commands:"
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# Development commands
dev-build: ## Build development Docker images
	docker-compose -f docker-compose.dev.yml build

dev-up: ## Start development environment
	docker-compose -f docker-compose.dev.yml up -d

dev-down: ## Stop development environment
	docker-compose -f docker-compose.dev.yml down

dev-restart: ## Restart development environment
	docker-compose -f docker-compose.dev.yml restart

dev-logs: ## Show development logs
	docker-compose -f docker-compose.dev.yml logs -f

dev-shell: ## Access application container shell
	docker-compose -f docker-compose.dev.yml exec app bash

# Setup commands
dev-setup: ## Initial setup for development
	cp docker.env .env
	docker-compose -f docker-compose.dev.yml run --rm app php artisan key:generate
	docker-compose -f docker-compose.dev.yml run --rm app php artisan migrate
	@echo "Development setup complete! Run 'make dev-up' to start the application."

# Testing commands
test: ## Run PHPUnit tests
	docker-compose -f docker-compose.dev.yml exec app php artisan test

test-coverage: ## Run tests with coverage
	docker-compose -f docker-compose.dev.yml exec app php artisan test --coverage

pint: ## Run Laravel Pint (code style)
	docker-compose -f docker-compose.dev.yml exec app ./vendor/bin/pint

# Laravel commands
migrate: ## Run database migrations
	docker-compose -f docker-compose.dev.yml exec app php artisan migrate

migrate-status: ## Check migration status
	docker-compose -f docker-compose.dev.yml exec app php artisan migrate:status

key-generate: ## Generate application key
	docker-compose -f docker-compose.dev.yml run --rm app php artisan key:generate

cache-clear: ## Clear application cache
	docker-compose -f docker-compose.dev.yml exec app php artisan cache:clear
	docker-compose -f docker-compose.dev.yml exec app php artisan config:clear
	docker-compose -f docker-compose.dev.yml exec app php artisan route:clear
	docker-compose -f docker-compose.dev.yml exec app php artisan view:clear

# Utility commands
redis-shell: ## Access Redis shell
	docker-compose -f docker-compose.dev.yml exec redis redis-cli

# Cleanup commands
clean: ## Clean up development containers and images
	docker-compose -f docker-compose.dev.yml down --rmi all --volumes --remove-orphans

rebuild: ## Rebuild and restart development environment
	docker-compose -f docker-compose.dev.yml build --no-cache
	docker-compose -f docker-compose.dev.yml up -d

# Status commands
status: ## Show development container status
	docker-compose -f docker-compose.dev.yml ps

# Quick start
start: dev-up key-generate migrate ## Quick start development environment
	@echo "🚀 Development environment started!"
	@echo "📱 Application: http://localhost:8000"
	@echo "📧 Email testing: http://localhost:8025"
	@echo "🔗 API: http://localhost:8000/api/companies"