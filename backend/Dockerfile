# LiveCommerce API — cloud image (Render / Railway, ADR-018)
# Build context: backend/

FROM php:8.4-cli-bookworm

# ffmpeg/ffprobe required for VOD pipeline (Sprint 3.2 / ADR video processing)
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    libpq-dev \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    ffmpeg \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        zip \
        intl \
        bcmath \
        gd \
        pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
# Skip Composer scripts at build time — artisan needs APP_KEY / env (only available at runtime on Render).
RUN composer dump-autoload --optimize --no-scripts \
    && chmod +x docker/cloud-entrypoint.sh \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

ENV PORT=10000
EXPOSE 10000

ENTRYPOINT ["docker/cloud-entrypoint.sh"]
# Override with `worker` on Render Background Worker (dockerCommand: worker)
CMD ["web"]
