# CaiTong Docker Setup Guide

This guide will help you set up and run the CaiTong Laravel application using Docker with Azure PostgreSQL.

## 🚀 Quick Start

### Prerequisites
- Docker Desktop installed and running
- Git Bash (Windows) or Terminal (Mac/Linux)
- Access to Azure PostgreSQL database

### 1. **Environment Setup**
```bash
# Copy environment configuration
cp docker.env .env

# Start development environment
docker-compose -f docker-compose.dev.yml up -d

# Generate application key
docker-compose -f docker-compose.dev.yml run --rm app php artisan key:generate

# Run migrations
docker-compose -f docker-compose.dev.yml exec app php artisan migrate --force
```

### 2. **Access Your Application**
- **Main Application**: http://localhost:8000
- **API Endpoints**: http://localhost:8000/api/companies
- **Email Testing**: http://localhost:8025 (Mailpit)

## 🏗️ Architecture

### **Services Overview**
- **app**: PHP 8.2 + PHP-FPM with Laravel 12.0
- **nginx**: Web server (port 8000)
- **redis**: Cache and session storage
- **mailpit**: Email testing tool
- **Database**: Azure PostgreSQL (External)

### **Database Configuration**
- **Engine**: Azure PostgreSQL
- **Host**: `ong-pg.postgres.database.azure.com`
- **Database**: `ong_metrics`
- **Schema**: `cait_dev`
- **Port**: 5432

## 📋 Available Commands

### **Using Bash Script (Recommended)**
```bash
# Quick start
./docker.sh start

# Development commands
./docker.sh dev-up          # Start development environment
./docker.sh dev-down        # Stop development environment
./docker.sh dev-logs        # View logs
./docker.sh dev-shell       # Access container shell

# Laravel commands
./docker.sh migrate         # Run migrations
./docker.sh test           # Run tests
./docker.sh cache-clear    # Clear caches
./docker.sh key-generate   # Generate app key

# Utility commands
./docker.sh clean          # Clean up containers
./docker.sh rebuild        # Rebuild and restart
./docker.sh status         # Show container status

# Show all available commands
./docker.sh help
```

### **Using Makefile (Alternative)**

### **Direct Docker Commands**
```bash
# Start services
docker-compose -f docker-compose.dev.yml up -d

# Stop services
docker-compose -f docker-compose.dev.yml down

# View logs
docker-compose -f docker-compose.dev.yml logs -f

# Access container
docker-compose -f docker-compose.dev.yml exec app bash

# Run Laravel commands
docker-compose -f docker-compose.dev.yml exec app php artisan migrate
docker-compose -f docker-compose.dev.yml exec app php artisan test
```

## 🔧 Configuration

### **Environment Variables**
Key environment variables in `docker.env`:
```bash
APP_NAME=CaiTong
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=your_database_host_here
DB_PORT=5432
DB_DATABASE=your_database_name_here
DB_USERNAME=your_database_username_here
DB_PASSWORD=your_database_password_here
DB_SCHEMA=your_database_schema_here

CACHE_DRIVER=redis
SESSION_DRIVER=redis
REDIS_HOST=redis
REDIS_PORT=6379
```

### **File Structure**
```
├── docker-compose.dev.yml    # Development Docker configuration
├── docker.env               # Environment template
├── .env                     # Active environment (copied from docker.env)
├── Dockerfile               # PHP application container
├── Makefile                 # Convenient commands
├── docker/
│   ├── nginx/               # Nginx configuration
│   └── php/                 # PHP configuration
└── app/                     # Laravel application
```

## 🐛 Troubleshooting

### **Common Issues**

1. **Port 8000 already in use**
   ```bash
   # Change port in docker-compose.dev.yml
   ports:
     - "8001:80"  # Use port 8001 instead
   ```

2. **Database connection failed**
   ```bash
   # Check if Azure PostgreSQL is accessible
   docker-compose -f docker-compose.dev.yml exec app php artisan migrate:status
   ```

3. **Application key not set**
   ```bash
   # Generate application key
   docker-compose -f docker-compose.dev.yml run --rm app php artisan key:generate
   ```

4. **Permission issues**
   ```bash
   # Fix storage permissions
   docker-compose -f docker-compose.dev.yml exec app chmod -R 755 storage bootstrap/cache
   ```

### **Reset Everything**
```bash
# Stop and remove all containers
docker-compose -f docker-compose.dev.yml down

# Remove volumes (WARNING: deletes Redis data)
docker-compose -f docker-compose.dev.yml down -v

# Rebuild and start fresh
docker-compose -f docker-compose.dev.yml build app
docker-compose -f docker-compose.dev.yml up -d
```

## 📊 Services Overview

| Service | Port | Purpose |
|---------|------|---------|
| **app** | 9000 (internal) | PHP 8.2 + PHP-FPM |
| **nginx** | 8000 | Web server |
| **redis** | 6380 | Cache and sessions |
| **mailpit** | 8025 | Email testing |

## 🔄 Daily Workflow

1. **Start development**
   ```bash
   ./docker.sh dev-up
   # or
   docker-compose -f docker-compose.dev.yml up -d
   ```

2. **Make code changes** (files are automatically synced)

3. **Test changes**
   ```bash
   curl http://localhost:8000/api/companies
   ```

4. **Stop when done**
   ```bash
   ./docker.sh dev-down
   # or
   docker-compose -f docker-compose.dev.yml down
   ```

## 📚 Additional Resources

- **Local Development Guide**: `LOCAL_DEVELOPMENT.md`
- **Production Deployment**: `PRODUCTION_DEPLOYMENT.md`
- **API Documentation**: `API_DOCUMENTATION.md`
- **Laravel Documentation**: https://laravel.com/docs
- **Docker Compose Reference**: https://docs.docker.com/compose/