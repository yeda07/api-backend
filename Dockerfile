FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git nginx supervisor unzip libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
RUN mkdir -p /run/nginx /var/log/supervisor \
    && cp docker/nginx.conf /etc/nginx/sites-available/default \
    && cp docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf \
    && chmod +x docker/start.sh \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8080

CMD ["sh", "docker/start.sh"]
