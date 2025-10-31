#!/bin/bash

# CaiTong Deployment Script
# This script handles deployment of the Dockerized Laravel application

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
APP_NAME="caitong"
DOCKER_USERNAME="${DOCKER_USERNAME:-your-dockerhub-username}"
VERSION="${VERSION:-latest}"
ENVIRONMENT="${ENVIRONMENT:-production}"

echo -e "${GREEN}Starting deployment for ${APP_NAME}...${NC}"

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

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    print_error "Docker is not running. Please start Docker and try again."
    exit 1
fi

# Build the Docker image
print_status "Building Docker image..."
docker build -t ${DOCKER_USERNAME}/${APP_NAME}:${VERSION} .

# Tag as latest if version is not latest
if [ "${VERSION}" != "latest" ]; then
    docker tag ${DOCKER_USERNAME}/${APP_NAME}:${VERSION} ${DOCKER_USERNAME}/${APP_NAME}:latest
fi

# Push to Docker Hub (optional)
if [ "${PUSH_TO_REGISTRY}" = "true" ]; then
    print_status "Pushing image to Docker Hub..."
    docker push ${DOCKER_USERNAME}/${APP_NAME}:${VERSION}
    if [ "${VERSION}" != "latest" ]; then
        docker push ${DOCKER_USERNAME}/${APP_NAME}:latest
    fi
fi

# Deploy based on environment
if [ "${ENVIRONMENT}" = "production" ]; then
    print_status "Deploying to production..."
    
    # Stop existing containers
    docker-compose down || true
    
    # Start new containers
    docker-compose up -d
    
    # Run migrations
    print_status "Running database migrations..."
    docker-compose exec -T app php artisan migrate --force
    
    # Clear caches
    print_status "Clearing application caches..."
    docker-compose exec -T app php artisan cache:clear
    docker-compose exec -T app php artisan config:clear
    docker-compose exec -T app php artisan route:clear
    docker-compose exec -T app php artisan view:clear
    
elif [ "${ENVIRONMENT}" = "development" ]; then
    print_status "Deploying to development..."
    
    # Stop existing containers
    docker-compose -f docker-compose.dev.yml down || true
    
    # Start new containers
    docker-compose -f docker-compose.dev.yml up -d
    
    # Run migrations
    print_status "Running database migrations..."
    docker-compose -f docker-compose.dev.yml exec -T app php artisan migrate --force
    
else
    print_error "Invalid environment: ${ENVIRONMENT}. Use 'production' or 'development'."
    exit 1
fi

# Health check
print_status "Performing health check..."
sleep 10

if curl -f http://localhost:8000/health > /dev/null 2>&1; then
    print_status "Application is healthy and running!"
    print_status "Access the application at: http://localhost:8000"
else
    print_warning "Health check failed. Please check the logs:"
    echo "docker-compose logs app"
fi

# Cleanup old images (optional)
if [ "${CLEANUP_OLD_IMAGES}" = "true" ]; then
    print_status "Cleaning up old Docker images..."
    docker image prune -f
fi

print_status "Deployment completed successfully!"
