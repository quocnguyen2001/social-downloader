FROM php:8.4-cli-alpine AS base

WORKDIR /var/www/html

# Install system dependencies (gộp chung và xóa cache)
RUN apk add --no-cache \
    git \
    curl \
    libpng \
    libjpeg-turbo \
    freetype \
    libwebp \
    oniguruma \
    libxml2 \
    libzip \
    icu-libs \
    ffmpeg \
    aria2 \
    bash \
    python3 \
    py3-pip

# Install build dependencies, PHP extensions, then clean up
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libwebp-dev \
    oniguruma-dev \
    libxml2-dev \
    libzip-dev \
    icu-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        soap \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install yt-dlp (xóa cache pip)
RUN pip3 install --no-cache-dir --break-system-packages \
    yt-dlp \
    mutagen \
    pycryptodomex \
    websockets \
    brotli \
    && rm -rf ~/.cache/pip

# ==================================
# Builder stage - chỉ để install dependencies
# ==================================
FROM base AS builder

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    && rm -rf /root/.composer

# ==================================
# Final stage - image cuối cùng
# ==================================
FROM base

# Copy vendor từ builder stage
COPY --from=builder /var/www/html/vendor ./vendor

# Copy application code
COPY --chown=www-data:www-data . .

# Set permissions
RUN chmod -R 755 storage bootstrap/cache

EXPOSE 8080

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
