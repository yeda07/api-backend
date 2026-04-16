FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git unzip libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

COPY . .

RUN chmod +x /app/docker/render-start.sh

EXPOSE 10000

CMD ["sh", "/app/docker/render-start.sh"]
