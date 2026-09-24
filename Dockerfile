# Образ API: FrankenPHP отдаёт public/ напрямую, без связки nginx + php-fpm.
# Один образ поднимает две роли — веб и воркер очереди (см. compose.yaml).
FROM dunglas/frankenphp:php8.4

# pdo_mysql — база, redis — кеш и очереди, pcntl — корректная остановка queue:work.
RUN install-php-extensions \
        intl \
        zip \
        bcmath \
        pdo_mysql \
        redis \
        pcntl \
        opcache

WORKDIR /app

# Значения по умолчанию, чтобы контейнер поднимался и без внешних переменных.
# compose и панель хостинга перекрывают их своими.
ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_TIMEZONE=UTC \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=mysql \
    DB_HOST=mysql \
    DB_PORT=3306 \
    DB_DATABASE=task_manager \
    DB_USERNAME=task_manager \
    REDIS_HOST=redis \
    CACHE_STORE=redis \
    QUEUE_CONNECTION=redis

COPY --from=composer/composer:2-bin /composer /usr/bin/composer

# Сначала только манифесты — слой с зависимостями переиспользуется между сборками.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --no-scripts \
        --no-autoloader

COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && chmod +x docker/entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["serve"]
