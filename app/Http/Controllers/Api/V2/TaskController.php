<?php

namespace App\Http\Controllers\Api\V2;

use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\UpdateTask;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\ListTasksRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\V2\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Queries\TaskListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

#[Group('Задачи v2', 'Задачи в API v2: курсорная пагинация, статус и приоритет объектами `{value, label}`, вложенный автор `creator`. Тела запросов и права — те же, что в v1.')]
class TaskController extends Controller
{
    #[Endpoint('Задачи проекта', 'Курсорная пагинация: следующая страница — по ссылке `links.next` или с параметром `cursor` из `meta.next_cursor`. Общего числа задач в ответе нет, зато при добавлении новых задач страницы не съезжают. Фильтры — как в v1.')]
    #[QueryParam('filter[status]', 'string', 'Статус или несколько через запятую: `todo`, `in_progress`, `review`, `done`.', required: false, example: 'todo,in_progress')]
    #[QueryParam('filter[priority]', 'string', 'Приоритет или несколько через запятую: `low`, `medium`, `high`, `urgent`.', required: false, example: 'No-example')]
    #[QueryParam('filter[assignee_id]', 'integer', 'ID исполнителя.', required: false, example: 'No-example')]
    #[QueryParam('filter[label]', 'string', 'ID метки или несколько через запятую.', required: false, example: 'No-example')]
    #[QueryParam('filter[overdue]', 'boolean', '`1` — только просроченные незавершённые задачи.', required: false, example: 'No-example')]
    #[QueryParam('filter[due_from]', 'string', 'Срок не раньше даты, `Y-m-d`.', required: false, example: 'No-example')]
    #[QueryParam('filter[due_to]', 'string', 'Срок не позже даты, `Y-m-d`.', required: false, example: 'No-example')]
    #[QueryParam('filter[search]', 'string', 'Поиск по заголовку и описанию, до 100 символов.', required: false, example: 'No-example')]
    #[QueryParam('sort', 'string', 'Поля: `created_at`, `due_date`, `priority`, `title`. Минус — по убыванию, несколько — через запятую. По умолчанию `-created_at`. При сортировке по `due_date` задачи без срока идут в конце.', required: false, example: '-priority,due_date')]
    #[QueryParam('per_page', 'integer', 'Размер страницы, 1–100. По умолчанию 15.', required: false, example: 15)]
    #[QueryParam('cursor', 'string', 'Курсор следующей или предыдущей страницы из `meta`.', required: false, example: 'No-example')]
    #[ResponseFromApiResource(TaskResource::class, Task::class, collection: true, cursorPaginate: 15, with: ['assignee', 'creator', 'labels'], withCount: ['comments'])]
    #[ScribeResponse(['message' => 'Requested sort(s) `colour` is not allowed. Allowed sort(s) are `created_at, title, due_date, priority`.'], 400, 'Неизвестное поле сортировки')]
    #[ScribeResponse(['message' => 'The cursor does not match the requested sort. Request the first page again.', 'errors' => ['cursor' => ['The cursor does not match the requested sort. Request the first page again.']]], 422, 'Курсор от другой сортировки')]
    public function index(ListTasksRequest $request, Project $project): AnonymousResourceCollection
    {
        $tasks = $this->cursorPage(TaskListQuery::forCursor($project->tasks(), $request), $request);

        return TaskResource::collection($tasks);
    }

    #[Endpoint('Создать задачу', 'Тело запроса — как в v1. В архивный проект задачи не добавляются (403).')]
    #[ResponseFromApiResource(TaskResource::class, Task::class, 201, with: ['assignee', 'creator', 'labels'])]
    #[ScribeResponse(['message' => 'The assignee must be a member of the team.', 'errors' => ['assignee_id' => ['The assignee must be a member of the team.']]], 422, 'Исполнитель не из команды')]
    public function store(StoreTaskRequest $request, Project $project, CreateTask $createTask): JsonResponse
    {
        $task = $createTask->handle($project, $request->user(), $request->validated());

        return TaskResource::make($this->withDetails($task))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[Endpoint('Задача')]
    #[ResponseFromApiResource(TaskResource::class, Task::class, with: ['assignee', 'creator', 'labels'], withCount: ['comments'])]
    public function show(Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        return TaskResource::make($this->withDetails($task));
    }

    #[Endpoint('Изменить задачу', 'Частичное обновление, тело запроса — как в v1.')]
    #[ResponseFromApiResource(TaskResource::class, Task::class, with: ['assignee', 'creator', 'labels'], withCount: ['comments'])]
    public function update(UpdateTaskRequest $request, Task $task, UpdateTask $updateTask): TaskResource
    {
        $task = $updateTask->handle($task, $request->user(), $request->validated());

        return TaskResource::make($this->withDetails($task));
    }

    #[Endpoint('Удалить задачу', 'Удалять может автор задачи, владелец и админы команды.')]
    #[ScribeResponse(status: 204)]
    #[ScribeResponse(['message' => 'This action is unauthorized.'], 403, 'Участник пытается удалить чужую задачу')]
    public function destroy(Task $task): Response
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return response()->noContent();
    }

    private function withDetails(Task $task): Task
    {
        return $task->load(['assignee', 'creator', 'labels'])->loadCount('comments');
    }
}
