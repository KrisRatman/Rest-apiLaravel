#!/bin/sh
# Smoke-тест поднятого стека: проверяет API как настоящий клиент, через HTTP.
# Запуск: docker compose up -d --wait && sh docker/smoke-test.sh [base_url]
# Нужны curl и jq.
set -eu

BASE_URL="${1:-http://localhost:8080}"
API="${BASE_URL}/api"

fail() {
    echo "FAIL: $1" >&2
    exit 1
}

echo "Жду ${BASE_URL}/up..."
i=0
until curl -fsS "${BASE_URL}/up" > /dev/null 2>&1; do
    i=$((i + 1))
    [ "$i" -ge 60 ] && fail "API не поднялся за 60 секунд"
    sleep 1
done

TOKEN=$(curl -fsS -X POST "${API}/v1/auth/login" \
    -H 'Accept: application/json' \
    -H 'Content-Type: application/json' \
    -d '{"email":"demo@example.com","password":"password"}' | jq -r '.data.token')
[ -n "$TOKEN" ] && [ "$TOKEN" != "null" ] || fail "вход демо-пользователем не вернул токен"
echo "OK  вход демо-пользователем"

AUTH="Authorization: Bearer ${TOKEN}"

TASKS=$(curl -fsS "${API}/v2/me/tasks?sort=due_date" -H 'Accept: application/json' -H "$AUTH")
echo "$TASKS" | jq -e '.data | length > 0' > /dev/null || fail "v2: у демо-пользователя нет задач"
echo "$TASKS" | jq -e '.data[0].status.label and (.meta | has("next_cursor"))' > /dev/null \
    || fail "v2: неожиданный формат ответа"
echo "OK  v2 /me/tasks: формат v2 и курсорная пагинация"

HEADERS=$(curl -fsS -o /dev/null -D - "${API}/v1/me/tasks" -H 'Accept: application/json' -H "$AUTH")
echo "$HEADERS" | grep -qi '^deprecation: @' || fail "v1: нет заголовка Deprecation"
echo "$HEADERS" | grep -qi '^x-ratelimit-limit:' || fail "нет заголовков rate limiting"
echo "OK  v1 /me/tasks: заголовки Deprecation и X-RateLimit"

STATUS=$(curl -sS -o /dev/null -w '%{http_code}' "${API}/v1/me" -H 'Accept: application/json')
[ "$STATUS" = "401" ] || fail "без токена ожидался 401, пришёл ${STATUS}"
echo "OK  без токена 401"

PROJECT_ID=$(echo "$TASKS" | jq -r '.data[0].project_id')
EXPORT_ID=$(curl -fsS -X POST "${API}/v1/projects/${PROJECT_ID}/exports" \
    -H 'Accept: application/json' -H "$AUTH" | jq -r '.data.id')
i=0
until curl -fsS "${API}/v1/exports/${EXPORT_ID}" -H 'Accept: application/json' -H "$AUTH" \
    | jq -e '.data.status == "completed"' > /dev/null; do
    i=$((i + 1))
    [ "$i" -ge 30 ] && fail "выгрузка CSV не завершилась за 30 секунд: воркер очереди не работает"
    sleep 1
done
curl -fsS "${API}/v1/exports/${EXPORT_ID}/download" -H "$AUTH" | head -c 3 | grep -q "$(printf '\357\273\277')" \
    || fail "скачанный CSV без BOM"
echo "OK  выгрузка CSV через очередь (Redis + воркер) и скачивание"

curl -fsS "${BASE_URL}/docs/index.html" | grep 'Task Manager API' > /dev/null || fail "документация не открывается"
echo "OK  документация"

echo "Smoke-тест пройден."
