# Task Manager API

[![CI](https://github.com/KrisRatman/Rest-apiLaravel/actions/workflows/ci.yml/badge.svg)](https://github.com/KrisRatman/Rest-apiLaravel/actions/workflows/ci.yml)

REST API для приложения задач с командами и правами: бэкенд для мобильного или веб-клиента в духе Trello или Todoist.

Пользователь состоит в нескольких командах и в каждой имеет свою роль. В команде есть проекты, в проектах задачи с исполнителями, сроками, приоритетами, метками и комментариями.

**Стек:** Laravel 13 · PHP 8.4 · Sanctum · MySQL · Redis · Pest · Scribe (OpenAPI + Postman) · Docker

## Что умеет

- **Авторизация по токенам (Sanctum):** регистрация, вход, выход, профиль, смена пароля. Отдельный токен на каждое устройство, при смене пароля остальные сессии отзываются.
- **Команды и роли:** `owner`, `admin`, `member`. Участники добавляются по email, роли меняются, владение можно передать. Участник может выйти сам, а задачи ушедшего остаются без исполнителя.
- **Проекты** с архивацией: в архивный проект нельзя добавить задачу. В списке проектов видно, сколько в каждом задач всего и сколько открытых.
- **Задачи:** статусы, приоритеты, исполнитель из команды, срок, метки. `completed_at` ставится сам при переходе в `done` и сбрасывается, если задачу вернули в работу. Флаг `is_overdue` помечает просроченные.
- **Фильтры, сортировка, пагинация:** `?filter[status]=todo,in_progress&filter[overdue]=1&sort=-priority,due_date&per_page=20`.
- **«Мои задачи»:** задачи пользователя из всех его команд на одном экране.
- **Комментарии и цветные метки.**
- **Приглашения по email**, в том числе людям без аккаунта. Код приходит в письме, в базе хранится только его хеш, срок действия 7 дней.
- **Письма через очередь:** приветствие после регистрации, уведомление исполнителю о назначенной задаче, приглашение в команду, готовность выгрузки.
- **Экспорт задач в CSV фоновым job'ом** с теми же фильтрами, что у списка: ответ 202, статус выгрузки, скачивание, письмо о готовности. Файл открывается в Excel, а ячейки, которые Excel выполнил бы как формулы, экранируются.
- **Планировщик** раз в сутки удаляет выгрузки старше 7 дней вместе с файлами и просроченные приглашения.
- **Единый формат ответов и ошибок**, изоляция команд: чужой ресурс отвечает 404, по ответу не понять, существует ли он.
- **Rate limiting:** 120 запросов в минуту на пользователя и 30 на IP для публичных эндпоинтов, отдельные лимиты на вход (5 на пару email + IP), регистрацию, приглашения и выгрузки. Клиент видит `X-RateLimit-*` и `Retry-After`. Все значения задаются в `.env`.
- **Кэш в Redis:** роли в командах (проверяются на каждом запросе) и статистика проекта. Кэш сбрасывается событиями моделей в момент изменения, поэтому данные в ответах не устаревают.
- **Статистика проекта для дашборда:** задачи по статусам, открытые по приоритетам и исполнителям, просроченные, закрытые за неделю.
- **Версионирование API:** `/api/v1` и `/api/v2`. В v2 задачи с курсорной пагинацией для бесконечной ленты и статусом и приоритетом в виде объектов с подписью. Устаревшие эндпоинты v1 сообщают об этом клиенту заголовками `Deprecation`, `Sunset` и `Link` на замену в v2.

## Быстрый старт

```bash
docker compose up -d
```

Через полминуты:

- документация: http://localhost:8080/docs/
- API: http://localhost:8080/api/v1 и http://localhost:8080/api/v2
- письма, которые отправляет API (Mailpit): http://localhost:8025
- демо-вход: `demo@example.com` / `password`

Миграции и демо-данные накатываются при старте. `.env` для Docker не обязателен: если `APP_KEY` не задан, первый контейнер генерирует ключ и делит его с остальными через общий том.

| Сервис | Что делает | Порт |
|---|---|---|
| `app` | API на FrankenPHP, при старте накатывает миграции и демо-данные | 8080 |
| `queue` | Воркер очереди: письма и выгрузки CSV | — |
| `schedule` | Планировщик: ежедневная очистка старых данных | — |
| `mysql` | MySQL 8.4 | 3308 |
| `redis` | Очереди, кэш, счётчики лимитов | — |
| `mailpit` | Перехватывает письма, веб-интерфейс | 8025 |

```bash
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"demo@example.com","password":"password"}'

curl -g "http://localhost:8080/api/v1/me/tasks?sort=-priority&filter[status]=todo,in_progress" \
  -H "Authorization: Bearer <token>"
```

### Без Docker

Нужны PHP 8.3+ и Composer. По умолчанию используется SQLite.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
php artisan queue:work      # в соседнем терминале: письма и выгрузки
php artisan schedule:work   # по желанию: ежедневная очистка
```

Без Docker письма по умолчанию пишутся в `storage/logs/laravel.log` (`MAIL_MAILER=log`), а кэш и очереди хранятся в базе. Для Redis задайте `CACHE_STORE=redis` и `QUEUE_CONNECTION=redis`.

### Демо-данные

Пароль у всех аккаунтов `password`.

| Аккаунт | Роль |
|---|---|
| `demo@example.com` | Владелец команды Mobile Team, участник команды Marketing |
| `ivan@example.com` | Админ Mobile Team |
| `maria@example.com` | Участник Mobile Team |
| `alex@example.com` | Владелец Marketing |

В Mobile Team есть активный проект «Release 2.0» с задачами разных статусов и приоритетов, метками, комментариями и двумя просроченными задачами, а также архивный проект «Release 1.5».

### Настройки

Всё задаётся в `.env`, полный список в [`.env.example`](.env.example).

| Переменная | Зачем |
|---|---|
| `FRONTEND_URL` | Адрес клиентского приложения, на него ведут ссылки в письмах |
| `API_RATE_LIMIT`, `API_GUEST_RATE_LIMIT`, `API_EXPORTS_RATE_LIMIT`… | Лимиты запросов в минуту ([`config/api.php`](config/api.php)) |
| `API_CACHE_TEAM_ROLE_TTL`, `API_CACHE_PROJECT_STATS_TTL` | Время жизни кэша в секундах |
| `API_V1_DEPRECATED_AT`, `API_V1_SUNSET_AT` | Даты для заголовков `Deprecation` и `Sunset` у устаревших эндпоинтов v1 |
| `CACHE_STORE`, `QUEUE_CONNECTION`, `REDIS_HOST` | Redis для кэша и очередей |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_FROM_ADDRESS` | Отправка писем |

## CI и деплой

Каждый пуш и pull request проходит [GitHub Actions](.github/workflows/ci.yml):

| Job | Что проверяет |
|---|---|
| Стиль кода | `pint --test`: PSR-12, пресет Laravel |
| Тесты (sqlite) | Весь набор Pest на SQLite в памяти, быстрый прогон |
| Тесты (mysql) | Тот же набор на MySQL 8.4, как в продакшене: виртуальные колонки, JSON, каскады |
| Docker-образ и smoke-тест | Собирает образ, поднимает весь стек через `docker compose` и проверяет его HTTP-запросами ([`docker/smoke-test.sh`](docker/smoke-test.sh)): вход, v2 с курсором, заголовки устаревания и лимитов, выгрузку CSV через Redis и воркер, документацию |
| Деплой | Только после зелёных тестов и smoke-теста в `main`: публикует образ в GitHub Container Registry (`latest` и короткий SHA) и обновляет контейнеры на сервере по SSH |

Dependabot раз в неделю открывает PR с обновлениями Composer, Docker-образа и actions, и CI прогоняет их так же.

### Деплой на VPS

На сервере нужен только Docker. Образ собирается в CI, на сервере ничего не компилируется.

```bash
mkdir ~/task-manager-api && cd ~/task-manager-api
# скопировать compose.prod.yaml и .env.production.example из репозитория
cp .env.production.example .env   # заполнить APP_KEY, APP_URL, пароли БД, SMTP
docker compose -f compose.prod.yaml up -d
```

[`compose.prod.yaml`](compose.prod.yaml) поднимает API, воркер очереди, планировщик, MySQL и Redis. База и Redis наружу не открыты, API слушает `127.0.0.1:8080`, а TLS выдаёт обратный прокси (Caddy, nginx или Cloudflare). Миграции накатываются при старте контейнера API.

Автодеплой из CI включается в настройках репозитория (Settings → Secrets and variables → Actions):

| Где | Имя | Значение |
|---|---|---|
| Variables | `DEPLOY_HOST` | Адрес сервера |
| Variables | `DEPLOY_PATH` | Папка проекта от домашней папки, по умолчанию `task-manager-api` |
| Variables | `DEPLOY_TARGET` | `docker` (по умолчанию) или `shared` для виртуального хостинга |
| Secrets | `DEPLOY_USER`, `DEPLOY_SSH_KEY` | Пользователь и приватный SSH-ключ |

Пока `DEPLOY_HOST` не задан, job деплоя пропускается, а остальной CI работает как обычно.

### Установка на виртуальный хостинг (Beget и аналоги)

API работает и на обычном хостинге с SSH, без Docker и VPS. Redis там нет, поэтому кэш, очередь и счётчики лимитов хранятся в MySQL. Постоянного воркера тоже нет: очередь разбирает `queue:work --stop-when-empty`, который раз в минуту запускает cron через планировщик.

1. **Панель хостинга:** создайте сайт и базу MySQL, у сайта выберите PHP 8.4, включите SSH.
2. **Код:** клонируйте репозиторий в папку сайта и направьте корень сайта в `public/` (на Beget — симлинк `public_html` → `laravel/public`). Composer нужен версии 2.
   ```bash
   php8.4 ~/bin/composer.phar install --no-dev --optimize-autoloader
   cp .env.example .env && chmod 600 .env
   ```
3. **`.env`:** `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, доступы к MySQL, а также
   ```
   CACHE_STORE=database
   QUEUE_CONNECTION=database
   API_SCHEDULED_QUEUE_WORKER=true
   ```
4. **База и кэши:**
   ```bash
   php8.4 artisan key:generate --force
   php8.4 artisan migrate --force && php8.4 artisan db:seed --force
   php8.4 artisan optimize
   ```
5. **Cron** раз в минуту: `php8.4 /путь/к/проекту/artisan schedule:run`. Он запускает очередь (письма, выгрузки CSV) и ежедневную очистку.
6. **HTTPS.** Если у сайта нет своего сертификата, подойдёт прокси, например Cloudflare Worker. В `APP_URL` укажите https-адрес прокси: все ссылки в ответах, включая пагинацию, API строит от него, а не от заголовков запроса.

Автодеплой из CI в этом режиме: переменная `DEPLOY_TARGET=shared` и `DEPLOY_PATH` — путь к проекту от домашней папки. После каждого пуша в `main` CI выполнит `git pull`, `composer install`, миграции и `optimize`.

## Документация API

Генерируется из кода (Form Requests, API Resources, PHP-атрибуты контроллеров) командой `php artisan scribe:generate` и лежит в репозитории. На запущенном сервере главная страница и `/docs` открывают её.

| Файл | Что это |
|---|---|
| [`public/docs/index.html`](public/docs/index.html) | HTML-документация с примерами запросов и кнопкой «Try it out» |
| [`public/docs/openapi.yaml`](public/docs/openapi.yaml) | OpenAPI 3.0 для Swagger UI, Insomnia, генераторов клиентов |
| [`public/docs/collection.json`](public/docs/collection.json) | Коллекция Postman |

## Эндпоинты

Пути ниже начинаются с `/api/v1`. Кроме регистрации и входа, всем нужен заголовок `Authorization: Bearer {token}`.

| Метод | Путь | Что делает |
|---|---|---|
| POST | `/auth/register`, `/auth/login` | Регистрация, вход, возвращают токен |
| POST | `/auth/logout` | Отзыв текущего токена |
| GET, PATCH | `/me` | Профиль |
| PUT | `/me/password` | Смена пароля |
| GET | `/me/tasks` | Мои задачи из всех команд |
| GET, POST | `/teams` | Мои команды, создать команду |
| GET, PATCH, DELETE | `/teams/{team}` | Команда |
| GET, POST | `/teams/{team}/members` | Участники, добавить по email |
| PATCH, DELETE | `/teams/{team}/members/{user}` | Сменить роль, исключить или выйти |
| POST | `/teams/{team}/ownership` | Передать владение |
| GET, POST | `/teams/{team}/invitations` | Приглашения, пригласить по email |
| DELETE | `/invitations/{invitation}` | Отозвать приглашение |
| POST | `/invitations/accept` | Принять приглашение по коду из письма |
| GET, POST | `/teams/{team}/projects` | Проекты команды |
| GET, PATCH, DELETE | `/projects/{project}` | Проект |
| GET | `/projects/{project}/stats` | Статистика проекта (кэшируется) |
| GET, POST | `/projects/{project}/tasks` | Задачи проекта с фильтрами |
| GET, PATCH, DELETE | `/tasks/{task}` | Задача |
| GET, POST | `/tasks/{task}/comments` | Комментарии |
| PATCH, DELETE | `/comments/{comment}` | Комментарий |
| GET, POST | `/teams/{team}/labels` | Метки команды |
| PATCH, DELETE | `/labels/{label}` | Метка |
| POST | `/projects/{project}/exports` | Запустить выгрузку задач в CSV (202) |
| GET | `/me/exports`, `/exports/{export}` | Мои выгрузки, статус выгрузки |
| GET | `/exports/{export}/download` | Скачать CSV (409, пока не готов) |

### Версия v2

В v2 вынесены только задачи, потому что только у них формат ответа изменился несовместимо. Остальные эндпоинты клиент v2 вызывает из v1. Тела запросов и права в обеих версиях одинаковые.

| Метод | Путь | Что делает |
|---|---|---|
| GET | `/api/v2/me/tasks` | Мои задачи, курсорная пагинация |
| GET, POST | `/api/v2/projects/{project}/tasks` | Задачи проекта, курсорная пагинация |
| GET, PATCH, DELETE | `/api/v2/tasks/{task}` | Задача |

Чем v2 отличается от v1:

| | v1 | v2 |
|---|---|---|
| Пагинация | Номера страниц, `meta.total` | Курсор: `meta.next_cursor` и `links.next`. Страницы не съезжают, когда появляются новые задачи |
| Статус и приоритет | `"status": "todo"` | `"status": {"value": "todo", "label": "To do"}` |
| Автор задачи | `creator_id` | Объект `creator` |
| Сортировка по сроку | Задачи без срока в начале | Задачи без срока в конце |

Эндпоинты задач в v1 продолжают работать, но в каждом ответе сообщают, что устарели:

```
Deprecation: @1790208000
Sunset: Wed, 31 Mar 2027 00:00:00 GMT
Link: <http://localhost:8080/api/v2/tasks/7>; rel="successor-version"
```

### Права

| Действие | owner | admin | member |
|---|---|---|---|
| Смотреть команду, проекты, задачи | ✅ | ✅ | ✅ |
| Создавать и менять задачи, комментировать | ✅ | ✅ | ✅ |
| Удалять задачи | любые | любые | только свои |
| Править комментарии | только свои | только свои | только свои |
| Удалять комментарии | любые | любые | только свои |
| Управлять проектами, метками, участниками | ✅ | ✅ | ❌ |
| Удалить команду, передать владение | ✅ | ❌ | ❌ |

### Формат ответов

```json
{ "data": { "id": 7, "title": "Apple Pay integration", "status": "in_progress", "priority": "urgent", ... } }
```

Списки дополнительно содержат `links` и `meta` (`current_page`, `last_page`, `per_page`, `total`). Ошибки всегда устроены одинаково:

```json
{ "message": "The assignee must be a member of the team.", "errors": { "assignee_id": ["The assignee must be a member of the team."] } }
```

`202` принято в фоновую обработку · `400` неизвестный фильтр или сортировка · `401` нет токена · `403` не хватает роли · `404` ресурса нет или он чужой · `409` файл ещё не готов · `422` валидация · `429` лимит запросов.

## Архитектура

Контроллеры тонкие: проверка прав и валидация живут в Form Request, бизнес-логика в Actions, ответ собирает API Resource.

```
Запрос → Form Request (authorize → Policy, rules) → Controller → Action → Model
                                                          ↓
                                                     API Resource → JSON
```

```
app/
├── Actions/            бизнес-операции: CreateTeam, InviteToTeam, AcceptInvitation, TransferOwnership,
│                       RemoveTeamMember (снимает задачи с ушедшего), CreateTask, UpdateTask, StartExport…
├── Enums/              TeamRole, TaskStatus, TaskPriority, ProjectStatus, ExportStatus
├── Http/
│   ├── Controllers/Api/V1/   контроллеры версии v1
│   ├── Controllers/Api/V2/   контроллеры версии v2 (задачи), используют те же Actions и Form Requests
│   ├── Middleware/     MarkDeprecatedVersion: заголовки Deprecation, Sunset и Link у устаревших эндпоинтов
│   ├── Requests/       валидация и авторизация до контроллера
│   └── Resources/      форма JSON-ответов, Resources/V2 — формат задач для v2
├── Jobs/               ExportProjectTasks: сборка CSV, повторы с задержкой, статус failed после последней попытки
├── Models/             Team, Membership (pivot с ролью), TeamInvitation, Project, Task, Comment, Label, Export
├── Notifications/      письма, все через очередь и только после коммита транзакции
├── Observers/          TaskObserver: сбрасывает кэш статистики проекта при изменении задач
├── Policies/           права по ролям; общий трейт ChecksTeamRole отвечает 404 чужим
├── Queries/            TaskListQuery: фильтры и сортировка для обеих версий, PrioritySort по весу приоритета
└── Services/           ProjectStatistics: агрегаты по задачам с кэшем

config/api.php          лимиты запросов, TTL кэша и даты устаревания v1, переопределяются через .env
routes/api.php          подключает версии: routes/api/v1.php и routes/api/v2.php
routes/console.php      расписание: ежедневный model:prune
docker/entrypoint.sh    роли контейнера: serve, queue, schedule
docker/smoke-test.sh    проверка поднятого стека HTTP-запросами, запускается в CI
compose.yaml            локальный стек со сборкой образа и Mailpit
compose.prod.yaml       продакшен-стек из готового образа GHCR
.github/workflows/      CI: Pint, тесты на SQLite и MySQL, образ, smoke-тест, деплой
```

Решения, которые стоит отметить:

- **Роль хранится в pivot `team_user`**, поэтому у одного пользователя в разных командах разные права. Глобальных ролей нет.
- **404 вместо 403 для чужих команд.** Политики отвечают `denyAsNotFound()`, а обработчик исключений выдаёт одинаковое сообщение и для чужого, и для несуществующего id, не раскрывая имя модели.
- **Права проверяются в `authorize()` Form Request**, то есть до валидации: посторонний получает 404, а не список ошибок полей.
- **Приоритет сортируется по весу** (`CASE ... WHEN 'urgent' THEN 4`), а не по алфавиту. Работает и на MySQL, и на SQLite.
- **Исполнитель и метки задачи валидируются в рамках команды**, так что назначить задачу на постороннего или повесить чужую метку нельзя.
- **Код приглашения хранится только в виде SHA-256.** Задание очереди с письмом шифруется (`ShouldBeEncrypted`), поэтому открытый код не лежит ни в таблице приглашений, ни в Redis. Принять приглашение можно только с аккаунта с тем же email.
- **Письма и job'ы уходят после коммита** (`afterCommit`): воркер не увидит задачу или выгрузку, которой ещё нет в базе.
- **Выгрузка не доверяет очереди проверку ввода.** Фильтры проверяются тем же `TaskListQuery` ещё в запросе (неизвестный фильтр → 400), а в job они восстанавливаются из сохранённых параметров.
- **Общий диск для выгрузок в Docker:** CSV пишет контейнер воркера, а отдаёт контейнер API.
- **Кэш сбрасывается событиями, а не только по TTL.** Роль хранится под ключом `team:{id}:member:{id}:role` и сбрасывается событиями pivot-модели `Membership`: `attach`, `detach` и `updateExistingPivot` идут через неё. Статистику сбрасывает `TaskObserver`, а массовый `update()` в обход модели (снятие задач с исключённого участника) сбрасывает её явно. Отсутствие членства тоже кэшируется, так что запросы посторонних не нагружают базу.
- **Версия в URL, в v2 только то, что изменилось.** Новая версия не копирует весь API: контроллеры v2 тонкие и используют те же Actions, Form Requests и политики, что и v1, а различаются только API Resource и пагинация. Замену для заголовка `Link` middleware находит по имени маршрута (`v1.tasks.show` → `v2.tasks.show`), поэтому пометить устаревшим новый эндпоинт можно одной строкой в маршрутах.
- **Курсорная пагинация по вычисляемым колонкам.** Курсор Laravel продолжает выдачу условием «после последней строки» и работает только с обычными колонками: сортировку по весу приоритета через `CASE` он молча пропускает, а на `NULL` в сроке теряет задачи. Поэтому в `tasks` есть виртуальные колонки `priority_weight` и `due_date_sort` (срок, а для задач без срока `9999-12-31`) с индексами. Тесты проходят все страницы и проверяют, что каждая задача встречается ровно один раз.
- **Лимит считается по пользователю, а не по IP**, чтобы коллеги из одного офиса за NAT не делили квоту. Счётчики лежат в Redis и общие для всех экземпляров API.

## База данных

```
users ─┬─< team_user >─── teams ─┬─< projects ──< tasks ─┬─< comments
       │   (role)                 ├─< labels >── label_task ┘
       │                          └─< team_invitations (email, role, token_hash, expires_at)
       ├── tasks.assignee_id
       ├── tasks.creator_id
       └─< exports >── projects   (status, filters, file_path, rows_count)
```

Внешние ключи с каскадным удалением: при удалении команды уходят её проекты, задачи, метки и комментарии. Индексы подобраны под частые запросы: `(project_id, status)`, `(assignee_id, status)`, `due_date`, а для курсорной пагинации v2 — `(project_id, priority_weight)` и `(project_id, due_date_sort)`.

## Тесты

```bash
php artisan test
```

259 тестов на Pest:

- **Feature-тесты на каждый эндпоинт:** 401 без токена, 404 для чужой команды, 403 для недостаточной роли, 422 на валидацию, успешный сценарий с проверкой состояния базы.
- **Матрица прав по политикам:** каждое действие проверено для каждой роли.
- **Фильтры, сортировка по приоритету, пагинация**, запрет подмены `team_id` или `project_id` через тело запроса, лимит попыток входа.
- **Фоновые задачи:** постановка в очередь (`Queue::fake`), содержимое CSV и экранирование формул, статус `failed` после последней попытки, кому и какие письма уходят (`Notification::fake`), текст писем с экранированием, очистка старых данных планировщиком.
- **Версии API:** формат задач v2, проход всех страниц по курсору при каждой сортировке, возврат по `prev_cursor`, 422 вместо 500 на курсор от другой сортировки, заголовки устаревания у v1 (в том числе в ответах с ошибкой) и их отсутствие у актуальных эндпоинтов.
- **Кэш и лимиты:** повторное чтение роли без запросов к базе, сброс кэша при каждом виде изменения, устаревший ответ при изменении в обход моделей (доказательство, что кэш реально используется), заголовки `X-RateLimit-*`, 429 с `Retry-After`, раздельные счётчики пользователей.

Локально тесты идут на SQLite в памяти, в CI дополнительно на MySQL 8.4. Везде `APP_DEBUG=false`, поэтому тесты проверяют ровно тот формат ошибок, который увидит клиент в продакшене.

### Команды для разработки

```bash
php artisan test                        # тесты
vendor/bin/pint                         # код-стайл (PSR-12, пресет Laravel)
php artisan scribe:generate --force     # пересобрать документацию после изменения эндпоинтов
docker compose exec app php artisan …   # любая artisan-команда в контейнере
```
