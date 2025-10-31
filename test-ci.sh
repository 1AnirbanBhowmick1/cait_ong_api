#!/bin/bash

# CI Test Script - Test CI pipeline locally
# Usage: ./test-ci.sh

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

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
    echo -e "${BLUE}  CI Pipeline Local Test${NC}"
    echo -e "${BLUE}================================${NC}"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Check prerequisites
check_prerequisites() {
    print_status "Checking prerequisites..."
    
    if ! command_exists docker; then
        print_error "Docker is not installed"
        exit 1
    fi
    
    if ! command_exists docker-compose; then
        print_error "Docker Compose is not installed"
        exit 1
    fi
    
    # Check if Docker is running
    if ! docker info > /dev/null 2>&1; then
        print_error "Docker is not running. Please start Docker Desktop first."
        exit 1
    fi
    
    # Check if docker-compose.dev.yml exists
    if [ ! -f "docker-compose.dev.yml" ]; then
        print_error "docker-compose.dev.yml not found. Please run this script from the project root."
        exit 1
    fi
    
    print_status "All prerequisites found!"
}

# Test PHP setup
test_php() {
    print_status "Testing PHP setup in Docker container..."
    
    # Check if Docker is running
    if ! docker-compose -f docker-compose.dev.yml ps app | grep -q "Up"; then
        print_status "Starting Docker container for testing..."
        docker-compose -f docker-compose.dev.yml up -d app
        sleep 5
    fi
    
    php_version=$(docker-compose -f docker-compose.dev.yml exec app php --version | head -n1)
    print_status "PHP Version: $php_version"
    
    # Check required extensions
    required_extensions=("mbstring" "dom" "fileinfo" "pdo_pgsql" "zip" "gd" "bcmath" "redis")
    
    for ext in "${required_extensions[@]}"; do
        if docker-compose -f docker-compose.dev.yml exec app php -m | grep -q "$ext"; then
            print_status "✅ $ext extension found"
        else
            print_error "❌ $ext extension missing"
            exit 1
        fi
    done
    
    print_status "PHP setup test passed!"
}

# Test Composer
test_composer() {
    print_status "Testing Composer in Docker container..."
    
    composer_version=$(docker-compose -f docker-compose.dev.yml exec app composer --version | head -n1)
    print_status "Composer Version: $composer_version"
    
    # Install dependencies
    print_status "Installing Composer dependencies..."
    docker-compose -f docker-compose.dev.yml exec app composer install --no-progress --prefer-dist --optimize-autoloader
    
    print_status "Composer test passed!"
}

# Test Node.js
test_node() {
    print_status "Testing Node.js in Docker container..."
    
    node_version=$(docker-compose -f docker-compose.dev.yml exec app node --version)
    npm_version=$(docker-compose -f docker-compose.dev.yml exec app npm --version)
    print_status "Node Version: $node_version"
    print_status "npm Version: $npm_version"
    
    # Install dependencies (use npm ci if lock file exists, otherwise npm install)
    print_status "Installing npm dependencies..."
    if [ -f "package-lock.json" ]; then
        docker-compose -f docker-compose.dev.yml exec app npm ci
    else
        print_warning "package-lock.json not found, using npm install instead"
        docker-compose -f docker-compose.dev.yml exec app npm install
    fi
    
    print_status "Node.js test passed!"
}

# Test Laravel
test_laravel() {
    print_status "Testing Laravel in Docker container..."
    
    # Copy environment file
    if [ -f "docker.env" ]; then
        cp docker.env .env
        print_status "Copied docker.env to .env"
    else
        print_error "docker.env not found!"
        exit 1
    fi
    
    # Generate application key
    print_status "Generating application key..."
    docker-compose -f docker-compose.dev.yml exec app php artisan key:generate
    
    # Test artisan commands
    print_status "Testing Artisan commands..."
    docker-compose -f docker-compose.dev.yml exec app php artisan --version
    docker-compose -f docker-compose.dev.yml exec app php artisan route:list > /dev/null
    docker-compose -f docker-compose.dev.yml exec app php artisan config:cache > /dev/null
    
    print_status "Laravel test passed!"
}

# Test Code Style
test_code_style() {
    print_status "Testing code style with Laravel Pint in Docker container..."
    
    if docker-compose -f docker-compose.dev.yml exec app test -f "./vendor/bin/pint"; then
        docker-compose -f docker-compose.dev.yml exec app ./vendor/bin/pint --test
        print_status "Code style test passed!"
    else
        print_warning "Laravel Pint not found, skipping code style test"
    fi
}

# Test PHPUnit (if database is available)
test_phpunit() {
    print_status "Testing PHPUnit in Docker container..."
    
    # Check if we can connect to database
    if docker-compose -f docker-compose.dev.yml exec app php artisan migrate:status > /dev/null 2>&1; then
        print_status "Database connection available, running tests..."
        docker-compose -f docker-compose.dev.yml exec app php artisan test --stop-on-failure
        print_status "PHPUnit test passed!"
    else
        print_warning "Database not available, skipping PHPUnit tests"
        print_warning "Make sure your Azure PostgreSQL is accessible"
    fi
}

# Test Docker build
test_docker() {
    print_status "Testing Docker build..."
    
    if command_exists docker; then
        docker build -t caitong-test .
        print_status "Docker build test passed!"
        
        # Test the built image
        docker run --rm caitong-test php --version
        print_status "Docker image test passed!"
    else
        print_warning "Docker not found, skipping Docker build test"
    fi
}

# Main test function
main() {
    print_header
    
    check_prerequisites
    test_php
    test_composer
    test_node
    test_laravel
    test_code_style
    test_phpunit
    test_docker
    
    print_status "🎉 All CI tests passed!"
    print_status "Your code is ready for GitHub Actions!"
    
    echo ""
    print_status "Next steps:"
    echo "1. Push your code to GitHub"
    echo "2. Configure GitHub Secrets (see CI_CD_SETUP.md)"
    echo "3. Watch the Actions tab for CI results"
}

# Run main function
main "$@"
