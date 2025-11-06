# CaiTong Production Deployment Guide

## 🚀 Production Architecture

### **Services Overview**
- **Application**: PHP 8.2 + PHP-FPM
- **Web Server**: Nginx
- **Database**: Azure PostgreSQL (External)
- **Cache/Sessions**: Redis (External or Container)
- **Process Manager**: Supervisor

## 📋 Prerequisites

### **Server Requirements**
- Docker Engine 20.10+
- Docker Compose 2.0+
- Minimum 2GB RAM
- Minimum 20GB disk space
- Ubuntu 20.04+ or CentOS 8+

### **External Services**
- Azure PostgreSQL database
- Redis server (optional - can use container)
- Domain name and SSL certificate

## 🔧 Production Setup

### 1. **Create Production Environment File**
```bash
# Create production environment
cp docker.env .env.production

# Edit with production values
nano .env.production
```

### 2. **Production Environment Variables**
```bash
APP_NAME=CaiTong
APP_ENV=production
APP_KEY=base64:YOUR_PRODUCTION_KEY_HERE
APP_DEBUG=false
APP_URL=https://yourdomain.com

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=ong-pg.postgres.database.azure.com
DB_PORT=5432
DB_DATABASE=ong_metrics
DB_USERNAME=caitong
DB_PASSWORD=your_production_password
DB_SCHEMA=cait_dev

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=your-redis-server.com
REDIS_PASSWORD=your_redis_password
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=your-smtp-server.com
MAIL_PORT=587
MAIL_USERNAME=your_email@domain.com
MAIL_PASSWORD=your_email_password
MAIL_ENCRYPTION=tls
```

### 3. **Create Production Docker Compose**
```yaml
# docker-compose.prod.yml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: caitong_app_prod
    restart: unless-stopped
    working_dir: /var/www/html
    volumes:
      - ./:/var/www/html
      - ./docker/php/production.ini:/usr/local/etc/php/conf.d/production.ini
    expose:
      - "9000"
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
      - DB_CONNECTION=pgsql
      - DB_HOST=ong-pg.postgres.database.azure.com
      - DB_PORT=5432
      - DB_DATABASE=ong_metrics
      - DB_USERNAME=caitong
      - DB_PASSWORD=${DB_PASSWORD}
      - DB_SCHEMA=cait_dev
      - REDIS_HOST=${REDIS_HOST}
      - REDIS_PASSWORD=${REDIS_PASSWORD}
      - REDIS_PORT=6379
      - CACHE_DRIVER=redis
      - SESSION_DRIVER=redis
      - QUEUE_CONNECTION=redis
    networks:
      - caitong_prod_network

  nginx:
    image: nginx:alpine
    container_name: caitong_nginx_prod
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./:/var/www/html
      - ./docker/nginx/production.conf:/etc/nginx/conf.d/default.conf
      - ./ssl:/etc/nginx/ssl
    depends_on:
      - app
    networks:
      - caitong_prod_network

networks:
  caitong_prod_network:
    driver: bridge
```

## 🚀 Deployment Commands

### **Initial Deployment**
```bash
# 1. Clone repository
git clone <your-repo-url>
cd caitong_v1

# 2. Set up environment
cp .env.production .env

# 3. Build and start
docker-compose -f docker-compose.prod.yml build
docker-compose -f docker-compose.prod.yml up -d

# 4. Generate application key
docker-compose -f docker-compose.prod.yml run --rm app php artisan key:generate --force

# 5. Run migrations
docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force

# 6. Set permissions
docker-compose -f docker-compose.prod.yml exec app chown -R www-data:www-data storage bootstrap/cache
docker-compose -f docker-compose.prod.yml exec app chmod -R 755 storage bootstrap/cache
```

### **Updates and Maintenance**
```bash
# Pull latest code
git pull origin main

# Rebuild and restart
docker-compose -f docker-compose.prod.yml build app
docker-compose -f docker-compose.prod.yml up -d

# Run migrations
docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force

# Clear caches
docker-compose -f docker-compose.prod.yml exec app php artisan cache:clear
docker-compose -f docker-compose.prod.yml exec app php artisan config:clear
docker-compose -f docker-compose.prod.yml exec app php artisan route:clear
```

## 🔒 Security Configuration

### **Nginx Production Configuration**
```nginx
# docker/nginx/production.conf
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/html/public;
    index index.php;

    # SSL Configuration
    ssl_certificate /etc/nginx/ssl/cert.pem;
    ssl_certificate_key /etc/nginx/ssl/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

    # Handle Laravel routes
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Handle PHP files
    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Deny access to sensitive files
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff|woff2|ttf|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Security: deny access to sensitive directories
    location ~ ^/(storage|bootstrap/cache) {
        deny all;
    }
}
```

### **PHP Production Configuration**
```ini
# docker/php/production.ini
[PHP]
; Production settings
memory_limit = 256M
max_execution_time = 60
max_input_time = 60
post_max_size = 50M
upload_max_filesize = 50M

; Error reporting
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

; Session settings
session.gc_maxlifetime = 1440
session.cookie_httponly = 1
session.cookie_secure = 1

; OPcache settings
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 0
opcache.fast_shutdown = 1

; Security
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off
```

## 📊 Monitoring and Logs

### **Log Management**
```bash
# View application logs
docker-compose -f docker-compose.prod.yml logs -f app

# View nginx logs
docker-compose -f docker-compose.prod.yml logs -f nginx

# View Laravel logs
docker-compose -f docker-compose.prod.yml exec app tail -f storage/logs/laravel.log
```

### **Health Checks**
```bash
# Check container status
docker-compose -f docker-compose.prod.yml ps

# Test application
curl -I https://yourdomain.com/api/companies

# Check database connection
docker-compose -f docker-compose.prod.yml exec app php artisan migrate:status
```

## 🛡️ Security Checklist

- [ ] SSL certificate installed and configured
- [ ] Environment variables secured
- [ ] Database credentials protected
- [ ] Firewall configured (ports 80, 443 only)
- [ ] Regular security updates
- [ ] Backup strategy implemented
- [ ] Monitoring and alerting set up
- [ ] Log rotation configured

## 📈 Performance Optimization

### **Nginx Optimizations**
- Enable gzip compression
- Set proper cache headers
- Use HTTP/2
- Configure rate limiting

### **PHP Optimizations**
- Enable OPcache
- Optimize memory settings
- Use Redis for sessions and cache
- Implement queue workers

### **Database Optimizations**
- Use connection pooling
- Optimize queries
- Implement proper indexing
- Regular maintenance

## 🆘 Troubleshooting

### **Common Production Issues**

1. **High Memory Usage**
   ```bash
   # Check memory usage
   docker stats
   
   # Optimize PHP memory settings
   # Edit docker/php/production.ini
   ```

2. **Slow Response Times**
   ```bash
   # Check nginx logs
   docker-compose -f docker-compose.prod.yml logs nginx
   
   # Enable nginx access logs
   # Check database query performance
   ```

3. **SSL Certificate Issues**
   ```bash
   # Test SSL configuration
   openssl s_client -connect yourdomain.com:443
   
   # Renew certificate
   certbot renew
   ```

## 📞 Support

For production issues:
1. Check logs first
2. Verify all services are running
3. Test database connectivity
4. Check SSL certificate validity
5. Contact system administrator if needed
