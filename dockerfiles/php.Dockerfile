# ============================================================
# PHP-FPM Custom Dockerfile (Multi-version)
# Usage: docker build --build-arg PHP_VERSION=8.4 -f php.Dockerfile .
# Supports: PHP 8.1, 8.2, 8.3, 8.4
# For PHP 7.4, use php74.Dockerfile instead
# ============================================================

ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-fpm-alpine

LABEL maintainer="Docker Local Webserver"
LABEL description="PHP-FPM ${PHP_VERSION} with extensions for web development"

# ============================================================
# System Dependencies
# ============================================================
RUN apk add --no-cache \
    # Image processing
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libwebp-dev \
    # Compression
    libzip-dev \
    # Internationalization
    icu-dev \
    icu-libs \
    # XML
    libxml2-dev \
    oniguruma-dev \
    # Crypto
    libsodium-dev \
    # Build tools
    linux-headers \
    autoconf \
    gcc \
    g++ \
    make \
    # General tools
    git \
    unzip \
    curl \
    bash \
    # Supervisor (for worker container)
    supervisor \
    # Shadow (for user management)
    shadow

# ============================================================
# PHP Extensions — Core
# ============================================================
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        intl \
        opcache \
        mbstring \
        xml \
        bcmath \
        soap \
        exif \
        pcntl \
        sodium \
        sockets

# ============================================================
# PHP Extensions — PECL
# ============================================================
RUN pecl install redis \
    && pecl install xdebug \
    && docker-php-ext-enable redis

# Note: xdebug is installed but NOT enabled by default
# It is controlled via the xdebug.ini mount and XDEBUG_ENABLED env var

# ============================================================
# Composer (latest)
# ============================================================
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ============================================================
# Create www-data user with proper UID (match common Linux UID)
# ============================================================
RUN apk add --no-cache shadow \
    && usermod -u 1000 www-data \
    && groupmod -g 1000 www-data || true

# ============================================================
# Cleanup
# ============================================================
RUN apk del autoconf gcc g++ make linux-headers \
    && rm -rf /var/cache/apk/* /tmp/*

# ============================================================
# Working Directory
# ============================================================
WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
