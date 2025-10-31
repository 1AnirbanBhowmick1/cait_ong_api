# Multi-stage build for production optimization
FROM php:8.2-fpm as base

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libpq-dev \
    && docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js 20 (required for Vite 7+)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies (skip post-install scripts for now)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy package files
COPY package.json package-lock.json* ./

# Install Node.js dependencies
RUN npm install --omit=dev

# Copy application code
COPY . .

# Create necessary directories
RUN mkdir -p bootstrap/cache storage/logs

# Run post-install scripts now that artisan is available
RUN composer run-script post-autoload-dump

# Install dev dependencies for building
RUN npm install

# Build frontend assets
RUN npm run build

# Remove dev dependencies to keep image size small
RUN npm prune --omit=dev

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Copy PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]

# Development stage
FROM base as development

# Install development dependencies
RUN composer install --dev --no-interaction

# Install all Node.js dependencies (including dev)
RUN npm install

# Copy development PHP configuration
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

# Enable Xdebug for development
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Development command
CMD ["php-fpm"]
