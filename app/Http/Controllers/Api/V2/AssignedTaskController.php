<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\ListTasksRequest;
use App\Http\Resources\V2\TaskResource;
use App\Models\Task;
use App\Queries\TaskListQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

#[Group('Задачи v2')]
class AssignedTaskController extends Controller
{
    #[Endpoint('Мои задачи', 'Задачи из всех команд, где исполнитель — текущий пользователь. Курсорная пагинация подходит для бесконечной ленты в мобильном клиенте.')]
    #[QueryParam('filter[status]', 'string', 'Статус или несколько через запятую.', required: false, example: 'todo,in_progress')]
    #[QueryParam('filter[priority]', 'string', 'Приоритет или несколько через запятую.', required: false, example: 'No-example')]
    #[QueryParam('filter[project_id]', 'integer', 'ID проекта.', required: false, example: 'No-example')]
    #[QueryParam('filter[label]', 'string', 'ID метки или несколько через запятую.', required: false, example: 'No-example')]
    #[QueryParam('filter[overdue]', 'boolean', '`1` — только просроченные.', required: false, example: 'No-example')]
    #[QueryParam('filter[due_from]', 'string', 'Срок не раньше даты, `Y-m-d`.', required: false, example: 'No-example')]
    #[QueryParam('filter[due_to]', 'string', 'Срок не позже даты, `Y-m-d`.', required: false, example: 'No-example')]
    #[QueryParam('filter[search]', 'string', 'Поиск по заголовку и описанию.', required: false, example: 'No-example')]
    #[QueryParam('sort', 'string', 'Как у задач проекта. `due_date` — ближайшие сроки сверху, задачи без срока в конце.', required: false, example: 'due_date')]
    #[QueryParam('per_page', 'integer', 'Размер страницы, 1–100.', required: false, example: 15)]
    #[QueryParam('cursor', 'string', 'Курсор следующей или предыдущей страницы из `meta`.', required: false, example: 'No-example')]
    #[ResponseFromApiResource(TaskResource::class, Task::class, collection: true, cursorPaginate: 15, with: ['assignee', 'creator', 'labels'], withCount: ['comments'])]
    public function index(ListTasksRequest $request): AnonymousResourceCollection
    {
        $tasks = $this->cursorPage(TaskListQuery::forCursor($request->user()->assignedTasks(), $request), $request);

        return TaskResource::collection($tasks);
    }
}
