# CaiTong Local Development Guide

## 🚀 Quick Start

### Prerequisites
- Docker Desktop installed and running
- Git Bash (Windows) or Terminal (Mac/Linux)
- Access to Azure PostgreSQL database

### 1. **Start Development Environment (Easy Way)**
```bash
# Quick start with bash script
./docker.sh start

# Test the application
curl http://localhost:8000/api/companies
```

### 1. **Start Development Environment (Manual Way)**
```bash
# Copy environment configuration
cp docker.env .env

# Start all services
docker-compose -f docker-compose.dev.yml up -d

# Generate application key
docker-compose -f docker-compose.dev.yml run --rm app php artisan key:generate

# Test the application
curl http://localhost:8000/api/companies
```

### 2. **Access Your Application**
- **Main Application**: http://localhost:8000
- **API Endpoints**: http://localhost:8000/api/companies
- **Email Testing**: http://localhost:8025 (Mailpit)

## 🛠️ Development Commands

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

### **Container Management (Manual)**
```bash
# Start services
docker-compose -f docker-compose.dev.yml up -d

# Stop services
docker-compose -f docker-compose.dev.yml down

# Restart services
docker-compose -f docker-compose.dev.yml restart

# View running containers
docker-compose -f docker-compose.dev.yml ps
```

### **Laravel Commands**
```bash
# Run migrations
docker-compose -f docker-compose.dev.yml exec app php artisan migrate

# Run tests
docker-compose -f docker-compose.dev.yml exec app php artisan test

# Clear caches
docker-compose -f docker-compose.dev.yml exec app php artisan cache:clear
docker-compose -f docker-compose.dev.yml exec app php artisan config:clear
docker-compose -f docker-compose.dev.yml exec app php artisan route:clear

# Access Artisan Tinker
docker-compose -f docker-compose.dev.yml exec app php artisan tinker

# Install Composer packages
docker-compose -f docker-compose.dev.yml exec app composer install

# Install NPM packages
docker-compose -f docker-compose.dev.yml exec app npm install

# Build frontend assets
docker-compose -f docker-compose.dev.yml exec app npm run build
```

### **Container Access**
```bash
# Access application container
docker-compose -f docker-compose.dev.yml exec app bash

# Access Redis
docker-compose -f docker-compose.dev.yml exec redis redis-cli

# View logs
docker-compose -f docker-compose.dev.yml logs app
docker-compose -f docker-compose.dev.yml logs nginx
```

## 🔧 Configuration

### **Environment Variables**
Your application connects to:
- **Database**: Azure PostgreSQL (`ong-pg.postgres.database.azure.com`)
- **Database Name**: `ong_metrics`
- **Schema**: `cait_dev`
- **Cache/Sessions**: Local Redis container
- **Email**: Mailpit (for testing)

### **File Structure**
```
├── docker-compose.dev.yml    # Development Docker configuration
├── docker.env               # Environment template
├── .env                     # Active environment (copied from docker.env)
├── Dockerfile               # PHP application container
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

## 📋 Services Overview

| Service | Port | Purpose |
|---------|------|---------|
| **app** | 9000 (internal) | PHP 8.2 + PHP-FPM |
| **nginx** | 8000 | Web server |
| **redis** | 6380 | Cache and sessions |
| **mailpit** | 8025 | Email testing |

## 🔄 Daily Workflow

1. **Start development**
   ```bash
   docker-compose -f docker-compose.dev.yml up -d
   ```

2. **Make code changes** (files are automatically synced)

3. **Test changes**
   ```bash
   curl http://localhost:8000/api/companies
   ```

4. **Stop when done**
   ```bash
   docker-compose -f docker-compose.dev.yml down
   ```

## 📚 Additional Resources

- **Laravel Documentation**: https://laravel.com/docs
- **Docker Compose Reference**: https://docs.docker.com/compose/
- **API Documentation**: Check `API_DOCUMENTATION.md` in your project
