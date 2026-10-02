# PHP 8.3 CLI image used for both running the Laravel dev server and all
# artisan/composer tooling. Kept deliberately simple (CLI server, not FPM+nginx)
# since this is a local-dev/demo deployment, not a production container.
FROM php:8.3-cli-bookworm

# Native extensions required by Laravel + PostgreSQL + exact-decimal math.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libzip-dev \
        unzip \
        git \
    && docker-php-ext-install pdo_pgsql pgsql bcmath pcntl zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer, pinned by its official installer image for reproducibility.
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
