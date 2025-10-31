#!/bin/bash

# CaiTong Docker Management Script (Azure PostgreSQL)
# Usage: ./docker.sh [command]

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_header() {
    echo -e "${BLUE}================================${NC}"
    echo -e "${BLUE}  CaiTong Docker Management${NC}"
    echo -e "${BLUE}  (Azure PostgreSQL)${NC}"
    echo -e "${BLUE}================================${NC}"
}

# Function to show help
show_help() {
    print_header
    echo ""
    echo "Available commands:"
    echo ""
    echo "  Development Commands:"
    echo "    dev-up          Start development environment"
    echo "    dev-down        Stop development environment"
    echo "    dev-restart     Restart development environment"
    echo "    dev-logs        Show development logs"
    echo "    dev-shell       Access application container shell"
    echo ""
    echo "  Setup Commands:"
    echo "    setup           Initial setup for development"
    echo "    key-generate    Generate application key"
    echo "    migrate         Run database migrations"
    echo "    migrate-status  Check migration status"
    echo ""
    echo "  Testing Commands:"
    echo "    test            Run PHPUnit tests"
    echo "    test-coverage   Run tests with coverage"
    echo "    pint            Run Laravel Pint (code style)"
    echo ""
    echo "  Utility Commands:"
    echo "    cache-clear     Clear application cache"
    echo "    redis-shell     Access Redis shell"
    echo "    status          Show container status"
    echo "    clean           Clean up containers and images"
    echo "    rebuild         Rebuild and restart"
    echo ""
    echo "  Quick Start:"
    echo "    start           Quick start development environment"
    echo ""
    echo "Usage: ./docker.sh [command]"
    echo ""
}

# Function to check if Docker is running
check_docker() {
    if ! docker info > /dev/null 2>&1; then
        print_error "Docker is not running. Please start Docker Desktop first."
        exit 1
    fi
}

# Function to check if docker-compose.dev.yml exists
check_compose_file() {
    if [ ! -f "docker-compose.dev.yml" ]; then
        print_error "docker-compose.dev.yml not found. Please run this script from the project root."
        exit 1
    fi
}

# Development commands
dev_up() {
    print_status "Starting development environment..."
    check_docker
    check_compose_file
    docker-compose -f docker-compose.dev.yml up -d
    print_status "Development environment started!"
    echo "📱 Application: http://localhost:8000"
    echo "📧 Email testing: http://localhost:8025"
}

dev_down() {
    print_status "Stopping development environment..."
    docker-compose -f docker-compose.dev.yml down
    print_status "Development environment stopped!"
}

dev_restart() {
    print_status "Restarting development environment..."
    docker-compose -f docker-compose.dev.yml restart
    print_status "Development environment restarted!"
}

dev_logs() {
    print_status "Showing development logs (Ctrl+C to exit)..."
    docker-compose -f docker-compose.dev.yml logs -f
}

dev_shell() {
    print_status "Accessing application container shell..."
    docker-compose -f docker-compose.dev.yml exec app bash
}

# Setup commands
setup() {
    print_status "Setting up development environment..."
    
    if [ ! -f ".env" ]; then
        if [ -f "docker.env" ]; then
            cp docker.env .env
            print_status "Copied docker.env to .env"
        else
            print_error "docker.env not found!"
            exit 1
        fi
    else
        print_warning ".env already exists. Skipping copy."
    fi
    
    print_status "Generating application key..."
    docker-compose -f docker-compose.dev.yml run --rm app php artisan key:generate
    
    print_status "Running database migrations..."
    docker-compose -f docker-compose.dev.yml exec app php artisan migrate --force
    
    print_status "Setup complete!"
    echo "🚀 Run './docker.sh start' to start the application."
}

key_generate() {
    print_status "Generating application key..."
    docker-compose -f docker-compose.dev.yml run --rm app php artisan key:generate
}

migrate() {
    print_status "Running database migrations..."
    docker-compose -f docker-compose.dev.yml exec app php artisan migrate --force
}

migrate_status() {
    print_status "Checking migration status..."
    docker-compose -f docker-compose.dev.yml exec app php artisan migrate:status
}

# Testing commands
test() {
    print_status "Running PHPUnit tests..."
    docker-compose -f docker-compose.dev.yml exec app php artisan test
}

test_coverage() {
    print_status "Running tests with coverage..."
    docker-compose -f docker-compose.dev.yml exec app php artisan test --coverage
}

pint() {
    print_status "Running Laravel Pint (code style)..."
    docker-compose -f docker-compose.dev.yml exec app ./vendor/bin/pint
}

# Utility commands
cache_clear() {
    print_status "Clearing application cache..."
    docker-compose -f docker-compose.dev.yml exec app php artisan cache:clear
    docker-compose -f docker-compose.dev.yml exec app php artisan config:clear
    docker-compose -f docker-compose.dev.yml exec app php artisan route:clear
    docker-compose -f docker-compose.dev.yml exec app php artisan view:clear
    print_status "Cache cleared!"
}

redis_shell() {
    print_status "Accessing Redis shell..."
    docker-compose -f docker-compose.dev.yml exec redis redis-cli
}

status() {
    print_status "Container status:"
    docker-compose -f docker-compose.dev.yml ps
}

clean() {
    print_warning "This will remove all containers, images, and volumes!"
    read -p "Are you sure? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        print_status "Cleaning up containers and images..."
        docker-compose -f docker-compose.dev.yml down --rmi all --volumes --remove-orphans
        print_status "Cleanup complete!"
    else
        print_status "Cleanup cancelled."
    fi
}

rebuild() {
    print_status "Rebuilding and restarting development environment..."
    docker-compose -f docker-compose.dev.yml build --no-cache
    docker-compose -f docker-compose.dev.yml up -d
    print_status "Rebuild complete!"
}

# Quick start
start() {
    print_status "Quick starting development environment..."
    dev_up
    key_generate
    migrate
    print_status "Development environment ready!"
    echo "📱 Application: http://localhost:8000"
    echo "📧 Email testing: http://localhost:8025"
    echo "🔗 API: http://localhost:8000/api/companies"
}

# Main script logic
case "${1:-help}" in
    "dev-up")
        dev_up
        ;;
    "dev-down")
        dev_down
        ;;
    "dev-restart")
        dev_restart
        ;;
    "dev-logs")
        dev_logs
        ;;
    "dev-shell")
        dev_shell
        ;;
    "setup")
        setup
        ;;
    "key-generate")
        key_generate
        ;;
    "migrate")
        migrate
        ;;
    "migrate-status")
        migrate_status
        ;;
    "test")
        test
        ;;
    "test-coverage")
        test_coverage
        ;;
    "pint")
        pint
        ;;
    "cache-clear")
        cache_clear
        ;;
    "redis-shell")
        redis_shell
        ;;
    "status")
        status
        ;;
    "clean")
        clean
        ;;
    "rebuild")
        rebuild
        ;;
    "start")
        start
        ;;
    "help"|*)
        show_help
        ;;
esac
