# syntax=docker/dockerfile:1

# -------------------------------------------------------------
# Stage 1: Build frontend assets with Vite
# -------------------------------------------------------------
FROM node:20-alpine AS node_builder

WORKDIR /app

# Copy dependency specifications
COPY package*.json ./

# Install npm dependencies
RUN npm ci --prefer-offline --no-audit

# Copy application source code for Tailwind CSS / Vite scanning
COPY . .

# Build assets to public/build
RUN npm run build


# -------------------------------------------------------------
# Stage 2: Production PHP-FPM + Nginx runtime
# -------------------------------------------------------------
FROM php:8.3-fpm-alpine

LABEL maintainer="Nocfinity"

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    bash \
    sed \
    libpng-dev \
    libzip-dev

# Install PHP extensions using docker-php-extension-installer
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    bcmath \
    mbstring \
    xml \
    curl \
    zip \
    intl \
    gd \
    opcache \
    redis \
    pcntl

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer manifests first for Docker layer caching
COPY composer.json composer.lock ./

# Install PHP dependencies without dev dependencies
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy application code
COPY . .

# Copy compiled frontend assets from node_builder stage
COPY --from=node_builder /app/public/build ./public/build

# Generate optimized autoloader and discover packages
RUN composer dump-autoload --optimize --no-dev

# Ensure storage and bootstrap directories exist with correct permissions
RUN mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# Configure Nginx and PHP-FPM permissions
RUN sed -i 's/user nginx;/user www-data;/g' /etc/nginx/nginx.conf \
    && chown -R www-data:www-data /var/lib/nginx /var/log/nginx

# Copy Docker configuration files
COPY docker/nginx.conf /etc/nginx/http.d/default.conf.template
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-docker.conf
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && rm -f /etc/nginx/http.d/default.conf

# Render web services default to port 10000 (overridden by $PORT)
ENV PORT=10000
EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
