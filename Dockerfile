# ============================================================================
# CSWeb Community Platform - Production Dockerfile
# ============================================================================
# Author: Bouna DRAME
# Date: 14 Mars 2026
# Version: 1.0.0
#
# Multi-stage build for optimized production image
# ============================================================================

FROM php:8.1-apache AS base

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    mysqli \
    pgsql \
    mbstring \
    xml \
    zip \
    opcache

# Enable Apache modules
RUN a2enmod rewrite headers ssl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/files \
    && mkdir -p /var/www/html/var/logs \
    && chmod -R 775 /var/www/html/files \
    && chmod -R 775 /var/www/html/var/logs

# Expose port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --retries=3 \
    CMD curl -f http://localhost/api/ || exit 1

# Start Apache
CMD ["apache2-foreground"]
