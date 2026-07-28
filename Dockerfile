FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip git ca-certificates \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-interaction --optimize-autoloader || true

COPY . .
RUN composer install --no-dev --no-scripts --no-interaction --optimize-autoloader

RUN mkdir -p storage/cache storage/logs storage/users \
    && chmod -R 777 storage

# Render sets $PORT at runtime; 10000 is just a sane local default.
EXPOSE 10000

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} -t public"]
