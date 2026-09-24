# Task Manager API

REST API для приложения задач с командами и правами: бэкенд для мобильного или веб-клиента в духе Trello или Todoist.

Пользователь состоит в нескольких командах и в каждой имеет свою роль. В команде есть проекты, в проектах задачи с исполнителями, сроками, приоритетами, метками и комментариями.

**Стек:** Laravel 13 · PHP 8.4 · Sanctum · MySQL · Redis · Pest · Scribe (OpenAPI + Postman) · Docker

## Что умеет

- **Авторизация по токенам (Sanctum):** регистрация, вход, выход, профиль, смена пароля. Отдельный токен на каждое устройство, при смене пароля остальные сессии отзываются.
- **Команды и роли:** `owner`, `admin`, `member`. Участники добавляются по email, роли меняются, владение можно передать.
- **Проекты** с архивацией: в архивный проект нельзя добавить задачу.
- **Задачи:** статусы, приоритеты, исполнитель из команды, срок, метки. `completed_at` ставится сам при переходе в `done`.
- **Фильтры, сортировка, пагинация:** `?filter[status]=todo,in_progress&filter[overdue]=1&sort=-priority,due_date&per_page=20`.
- **«Мои задачи»:** задачи пользователя из всех его команд на одном экране.
- **Комментарии и цветные метки.**
- **Единый формат ответов и ошибок**, изоляция команд: чужой ресурс отвечает 404, по ответу не понять, существует ли он.
- **Защита входа от перебора:** 5 попыток в минуту на пару email + IP.

## Быстрый старт

```bash
docker compose up -d
```

Через полминуты:

- документация: http://localhost:8080/docs/
- API: http://localhost:8080/api/v1
- демо-вход: `demo@example.com` / `password`

Поднимаются четыре контейнера: API на FrankenPHP, воркер очереди, MySQL и Redis. Миграции и демо-данные накатываются при старте.

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
```

## Документация API

Генерируется из кода (Form Requests, API Resources, PHP-атрибуты контроллеров) командой `php artisan scribe:generate` и лежит в репозитории:

| Файл | Что это |
|---|---|
| [`public/docs/index.html`](public/docs/index.html) | HTML-документация с примерами запросов и кнопкой «Try it out» |
| [`public/docs/openapi.yaml`](public/docs/openapi.yaml) | OpenAPI 3.0 для Swagger UI, Insomnia, генераторов клиентов |
| [`public/docs/collection.json`](public/docs/collection.json) | Коллекция Postman |

## Эндпоинты

Все пути начинаются с `/api/v1`. Кроме регистрации и входа, всем нужен заголовок `Authorization: Bearer {token}`.

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
| GET, POST | `/teams/{team}/projects` | Проекты команды |
| GET, PATCH, DELETE | `/projects/{project}` | Проект |
| GET, POST | `/projects/{project}/tasks` | Задачи проекта с фильтрами |
| GET, PATCH, DELETE | `/tasks/{task}` | Задача |
| GET, POST | `/tasks/{task}/comments` | Комментарии |
| PATCH, DELETE | `/comments/{comment}` | Комментарий |
| GET, POST | `/teams/{team}/labels` | Метки команды |
| PATCH, DELETE | `/labels/{label}` | Метка |

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

`400` неизвестный фильтр или сортировка · `401` нет токена · `403` не хватает роли · `404` ресурса нет или он чужой · `422` валидация · `429` лимит запросов.

## Архитектура

Контроллеры тонкие: проверка прав и валидация живут в Form Request, бизнес-логика в Actions, ответ собирает API Resource.

```
Запрос → Form Request (authorize → Policy, rules) → Controller → Action → Model
                                                          ↓
                                                     API Resource → JSON
```

```
app/
├── Actions/            бизнес-операции: CreateTeam, AddTeamMember, TransferOwnership,
│                       RemoveTeamMember (снимает задачи с ушедшего), CreateTask, UpdateTask…
├── Enums/              TeamRole, TaskStatus, TaskPriority, ProjectStatus
├── Http/
│   ├── Controllers/Api/V1/   контроллеры версии v1
│   ├── Requests/       валидация и авторизация до контроллера
│   └── Resources/      форма JSON-ответов
├── Models/             Team, Membership (pivot с ролью), Project, Task, Comment, Label
├── Policies/           права по ролям; общий трейт ChecksTeamRole отвечает 404 чужим
└── Queries/            TaskListQuery: фильтры и сортировка, PrioritySort по весу приоритета
```

Решения, которые стоит отметить:

- **Роль хранится в pivot `team_user`**, поэтому у одного пользователя в разных командах разные права. Глобальных ролей нет.
- **404 вместо 403 для чужих команд.** Политики отвечают `denyAsNotFound()`, а обработчик исключений выдаёт одинаковое сообщение и для чужого, и для несуществующего id, не раскрывая имя модели.
- **Права проверяются в `authorize()` Form Request**, то есть до валидации: посторонний получает 404, а не список ошибок полей.
- **Приоритет сортируется по весу** (`CASE ... WHEN 'urgent' THEN 4`), а не по алфавиту. Работает и на MySQL, и на SQLite.
- **Исполнитель и метки задачи валидируются в рамках команды**, так что назначить задачу на постороннего или повесить чужую метку нельзя.

## База данных

```
users ─┬─< team_user >─── teams ─┬─< projects ──< tasks ─┬─< comments
       │   (role)                 └─< labels >── label_task ┘
       ├── tasks.assignee_id
       └── tasks.creator_id
```

Внешние ключи с каскадным удалением: при удалении команды уходят её проекты, задачи, метки и комментарии. Индексы подобраны под частые запросы: `(project_id, status)`, `(assignee_id, status)`, `due_date`.

## Тесты

```bash
php artisan test
```

166 тестов на Pest:

- **Feature-тесты на каждый эндпоинт:** 401 без токена, 404 для чужой команды, 403 для недостаточной роли, 422 на валидацию, успешный сценарий с проверкой состояния базы.
- **Матрица прав по политикам:** каждое действие проверено для каждой роли.
- **Фильтры, сортировка по приоритету, пагинация**, запрет подмены `team_id` или `project_id` через тело запроса, лимит попыток входа.

Тесты идут на SQLite в памяти с `APP_DEBUG=false`, то есть проверяют ровно тот формат ошибок, который увидит клиент в продакшене.
