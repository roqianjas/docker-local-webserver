# ============================================================
# PHP 7.4-FPM Dockerfile (Legacy — separate due to API differences)
# ============================================================

FROM php:7.4-fpm-alpine

LABEL maintainer="Docker Local Webserver"
LABEL description="PHP-FPM 7.4 (Legacy) with extensions for web development"

# ============================================================
# System Dependencies
# ============================================================
RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    icu-libs \
    libxml2-dev \
    oniguruma-dev \
    libsodium-dev \
    linux-headers \
    autoconf \
    gcc \
    g++ \
    make \
    git \
    unzip \
    curl \
    bash \
    supervisor \
    shadow

# ============================================================
# PHP Extensions — Core (PHP 7.4 uses different gd config syntax)
# ============================================================
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
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
# PHP Extensions — PECL (compatible versions for PHP 7.4)
# ============================================================
RUN pecl install redis-5.3.7 \
    && docker-php-ext-enable redis

# Note: Xdebug 3.1.x is the last version supporting PHP 7.4
# Not installed by default for 7.4 to keep it lightweight

# ============================================================
# Composer (latest v2 — supports PHP 7.4)
# ============================================================
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ============================================================
# User setup
# ============================================================
RUN usermod -u 1000 www-data \
    && groupmod -g 1000 www-data || true

# ============================================================
# Cleanup
# ============================================================
RUN apk del autoconf gcc g++ make linux-headers \
    && rm -rf /var/cache/apk/* /tmp/*

WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
