#!/bin/sh
# Стартовый скрипт контейнера. Первый аргумент выбирает роль:
#   serve     — веб-сервер API (по умолчанию)
#   queue     — воркер очереди: письма и выгрузки CSV
#   schedule  — планировщик: ежедневная очистка старых выгрузок и приглашений
set -e

cd /app

ROLE="${1:-serve}"

mkdir -p \
    bootstrap/cache \
    storage/app/private \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

# Ключ приложения обязателен: им шифруются задания очереди с кодом приглашения,
# и у API и воркера он должен совпадать. Обычно приходит из .env или панели хостинга.
# Если не задан — первый контейнер кладёт ключ в общий том, остальные читают его оттуда.
if [ -z "${APP_KEY}" ]; then
    KEY_FILE=storage/app/private/.app_key
    if [ ! -s "$KEY_FILE" ]; then
        # noclobber: из двух одновременно стартующих контейнеров файл создаст только один.
        (set -o noclobber; php artisan key:generate --force --show > "$KEY_FILE") 2>/dev/null || sleep 1
    fi
    APP_KEY="$(cat "$KEY_FILE")"
    export APP_KEY
    echo "APP_KEY не задан — использую сгенерированный ключ из общего тома."
fi

# MySQL в соседнем контейнере поднимается дольше, чем приложение.
echo "Жду базу ${DB_HOST}:${DB_PORT}..."
i=0
until php -r 'exit(@fsockopen(getenv("DB_HOST"), (int) getenv("DB_PORT")) ? 0 : 1);'; do
    i=$((i + 1))
    if [ "$i" -ge 60 ]; then
        echo "База не ответила за 60 секунд." >&2
        exit 1
    fi
    sleep 1
done

# Миграции накатывает только веб-контейнер, иначе роли стартуют одновременно
# и лезут в одни и те же таблицы.
if [ "$ROLE" = "serve" ]; then
    php artisan migrate --force

    # Демо-данные появляются на пустой базе и не дублируются на существующей.
    php artisan db:seed --force

    php artisan config:cache
    php artisan route:cache
else
    echo "Жду готовности схемы базы..."
    i=0
    while : ; do
        if php artisan migrate:status --no-ansi > /tmp/migrate-status 2>/dev/null &&
           ! grep -qi 'pending' /tmp/migrate-status; then
            break
        fi

        i=$((i + 1))
        if [ "$i" -ge 90 ]; then
            echo "Схема базы так и не появилась." >&2
            exit 1
        fi
        sleep 2
    done
fi

case "$ROLE" in
    serve)
        # На хостингах порт приходит в $PORT, TLS терминируется на их стороне.
        export SERVER_NAME=":${PORT:-8080}"
        exec frankenphp run --config /etc/caddy/Caddyfile
        ;;
    queue)
        # --timeout меньше retry_after (90 c) очереди, иначе задание успеют выдать второму воркеру.
        exec php artisan queue:work --tries=3 --sleep=1 --timeout=80 --max-time=3600
        ;;
    schedule)
        exec php artisan schedule:work
        ;;
    *)
        # Любая другая команда выполняется как есть: docker compose run app php artisan ...
        exec "$@"
        ;;
esac
