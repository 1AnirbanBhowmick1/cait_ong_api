# CI/CD Setup Guide for CaiTong

## 🚀 Overview

Your CI/CD pipeline is configured with GitHub Actions and includes:
- **Automated Testing** (PHPUnit, Laravel Pint)
- **Security Scanning** (Composer audit)
- **Docker Image Building** (Multi-platform)
- **Performance Testing** (Weekly)
- **Security Scanning** (Docker images)

## 📋 Current Workflows

### 1. **Main CI/CD Pipeline** (`.github/workflows/ci-cd.yml`)
**Triggers**: Push to `main`/`dev` branches, Pull Requests

**Jobs**:
- ✅ **Test**: PHPUnit tests with PostgreSQL + Redis
- ✅ **Security**: Composer security audit
- ✅ **Docker Build**: Multi-platform Docker image (main branch only)
- ✅ **Deploy**: Production deployment (main branch only)

### 2. **Security Scan** (`.github/workflows/security-scan.yml`)
**Triggers**: Weekly, Push to main

**Features**:
- ✅ **Trivy**: Docker image vulnerability scanning
- ✅ **CodeQL**: Code security analysis

### 3. **Performance Test** (`.github/workflows/performance-test.yml`)
**Triggers**: Weekly

**Features**:
- ✅ **Load Testing**: API endpoint performance
- ✅ **Database Performance**: Query optimization checks

## 🔧 Setup Instructions

### **Step 1: Configure GitHub Secrets**

Go to your GitHub repository → Settings → Secrets and variables → Actions

Add these secrets:

#### **Required Secrets**
```bash
# Docker Hub (for image publishing)
DOCKER_USERNAME=your_dockerhub_username
DOCKER_PASSWORD=your_dockerhub_password

# Production Deployment (if using cloud provider)
PROD_HOST=your_production_server_ip
PROD_USER=your_server_username
PROD_SSH_KEY=your_private_ssh_key

# Azure PostgreSQL (for production)
DB_PASSWORD=your_production_db_password
REDIS_HOST=your_production_redis_host
REDIS_PASSWORD=your_production_redis_password
```

#### **Optional Secrets**
```bash
# Codecov (for test coverage)
CODECOV_TOKEN=your_codecov_token

# Notification (Slack/Discord)
SLACK_WEBHOOK_URL=your_slack_webhook_url
DISCORD_WEBHOOK_URL=your_discord_webhook_url
```

### **Step 2: Test the Pipeline**

1. **Push to dev branch** to trigger CI:
   ```bash
   git add .
   git commit -m "test: trigger CI pipeline"
   git push origin dev
   ```

2. **Check GitHub Actions**:
   - Go to your repository → Actions tab
   - Watch the workflow run
   - Check for any failures

### **Step 3: Configure Production Deployment**

Update the deploy job in `.github/workflows/ci-cd.yml`:

```yaml
deploy:
  runs-on: ubuntu-latest
  needs: [docker-build]
  if: github.event_name == 'push' && github.ref == 'refs/heads/main'
  environment: production
  
  steps:
  - name: Deploy to production server
    uses: appleboy/ssh-action@v1.0.0
    with:
      host: ${{ secrets.PROD_HOST }}
      username: ${{ secrets.PROD_USER }}
      key: ${{ secrets.PROD_SSH_KEY }}
      script: |
        # Pull latest image
        docker pull ${{ secrets.DOCKER_USERNAME }}/caitong:latest
        
        # Stop current containers
        docker-compose -f docker-compose.prod.yml down
        
        # Update environment
        cp .env.production .env
        
        # Start with new image
        docker-compose -f docker-compose.prod.yml up -d
        
        # Run migrations
        docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force
        
        # Clear caches
        docker-compose -f docker-compose.prod.yml exec app php artisan cache:clear
```

## 🔍 Pipeline Details

### **Test Job**
- **Database**: PostgreSQL 15 (test database)
- **Cache**: Redis 7
- **PHP Extensions**: pdo_pgsql, redis, mbstring, dom, fileinfo, zip, gd, bcmath
- **Tests**: PHPUnit with coverage
- **Code Style**: Laravel Pint

### **Docker Build Job**
- **Platforms**: linux/amd64, linux/arm64
- **Registry**: Docker Hub
- **Caching**: GitHub Actions cache
- **Tags**: branch, commit SHA, latest (main branch)

### **Security Job**
- **Composer Audit**: Checks for known vulnerabilities
- **Trivy**: Scans Docker images for CVEs
- **CodeQL**: Analyzes code for security issues

## 📊 Monitoring & Notifications

### **Success Notifications**
Add to your workflow for Slack/Discord notifications:

```yaml
- name: Notify Success
  if: success()
  uses: 8398a7/action-slack@v3
  with:
    status: success
    webhook_url: ${{ secrets.SLACK_WEBHOOK_URL }}
    text: "✅ Deployment successful: ${{ github.ref_name }}"
```

### **Failure Notifications**
```yaml
- name: Notify Failure
  if: failure()
  uses: 8398a7/action-slack@v3
  with:
    status: failure
    webhook_url: ${{ secrets.SLACK_WEBHOOK_URL }}
    text: "❌ Deployment failed: ${{ github.ref_name }}"
```

## 🛠️ Customization

### **Add More Tests**
```yaml
- name: Run Browser Tests
  run: |
    npm run test:e2e
    
- name: Run API Tests
  run: |
    php artisan test --testsuite=Feature
```

### **Add Database Seeding**
```yaml
- name: Seed test database
  run: php artisan db:seed --class=TestDataSeeder
```

### **Add Performance Tests**
```yaml
- name: Run Performance Tests
  run: |
    php artisan test --testsuite=Performance
```

## 🚨 Troubleshooting

### **Common Issues**

1. **Tests Failing**
   ```bash
   # Check database connection
   php artisan migrate:status
   
   # Check Redis connection
   redis-cli ping
   ```

2. **Docker Build Failing**
   ```bash
   # Test locally
   docker build -t test-image .
   docker run --rm test-image php artisan --version
   ```

3. **Deployment Failing**
   ```bash
   # Check server connectivity
   ssh user@server "docker ps"
   
   # Check environment variables
   ssh user@server "cat .env"
   ```

### **Debug Mode**
Add debug steps to your workflow:

```yaml
- name: Debug Environment
  run: |
    echo "PHP Version: $(php --version)"
    echo "Node Version: $(node --version)"
    echo "Database Status: $(php artisan migrate:status)"
```

## 📈 Metrics & Reporting

### **Test Coverage**
- **Codecov**: Automatic coverage reports
- **Badge**: Add to README.md
- **Thresholds**: Set minimum coverage requirements

### **Performance Metrics**
- **API Response Times**: Tracked in performance tests
- **Database Query Performance**: Monitored weekly
- **Docker Image Size**: Optimized with multi-stage builds

## 🔄 Workflow Triggers

| Event | Branches | Jobs |
|-------|----------|------|
| **Push** | `main`, `dev` | Test, Security, Docker Build, Deploy |
| **Pull Request** | `main`, `dev` | Test, Security |
| **Schedule** | - | Security Scan, Performance Test |

## 📚 Next Steps

1. **Configure Secrets** in GitHub repository
2. **Test Pipeline** with a push to dev branch
3. **Set up Production Server** for deployment
4. **Configure Notifications** (Slack/Discord)
5. **Monitor Performance** and optimize as needed

Your CI/CD pipeline is now ready to automatically test, build, and deploy your CaiTong application! 🎉
