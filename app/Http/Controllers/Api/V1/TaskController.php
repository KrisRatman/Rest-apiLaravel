<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\UpdateTask;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\ListTasksRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
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

#[Group('Задачи', 'Задачи проекта. Создавать, менять и комментировать может любой участник команды; удалять — автор задачи, владелец и админы.')]
class TaskController extends Controller
{
    #[Endpoint('Задачи проекта', 'Фильтры, сортировка и пагинация. Неизвестный фильтр или поле сортировки → 400.')]
    #[QueryParam('filter[status]', 'string', 'Статус или несколько через запятую: `todo`, `in_progress`, `review`, `done`.', required: false, example: 'todo,in_progress')]
    #[QueryParam('filter[priority]', 'string', 'Приоритет или несколько через запятую: `low`, `medium`, `high`, `urgent`.', required: false, example: 'high,urgent')]
    #[QueryParam('filter[assignee_id]', 'integer', 'ID исполнителя.', required: false, example: 'No-example')]
    #[QueryParam('filter[label]', 'string', 'ID метки или несколько через запятую.', required: false, example: 'No-example')]
    #[QueryParam('filter[overdue]', 'boolean', '`1` — только просроченные незавершённые задачи.', required: false, example: 'No-example')]
    #[QueryParam('filter[due_from]', 'string', 'Срок не раньше даты, `Y-m-d`.', required: false, example: 'No-example')]
    #[QueryParam('filter[due_to]', 'string', 'Срок не позже даты, `Y-m-d`.', required: false, example: 'No-example')]
    #[QueryParam('filter[search]', 'string', 'Поиск по заголовку и описанию, до 100 символов.', required: false, example: 'No-example')]
    #[QueryParam('sort', 'string', 'Поля: `created_at`, `due_date`, `priority`, `title`. Минус — по убыванию, несколько — через запятую. По умолчанию `-created_at`.', required: false, example: '-priority,due_date')]
    #[QueryParam('per_page', 'integer', 'Размер страницы, 1–100. По умолчанию 15.', required: false, example: 15)]
    #[QueryParam('page', 'integer', 'Номер страницы.', required: false, example: 1)]
    #[ResponseFromApiResource(TaskResource::class, Task::class, collection: true, paginate: 15, with: ['assignee', 'labels'], withCount: ['comments'])]
    #[ScribeResponse(['message' => 'Requested filter(s) `colour` are not allowed. Allowed filter(s) are `status, priority, assignee_id, project_id, label, overdue, due_from, due_to, search`.'], 400, 'Неизвестный фильтр')]
    public function index(ListTasksRequest $request, Project $project): AnonymousResourceCollection
    {
        $tasks = TaskListQuery::for($project->tasks(), $request)
            ->paginate($this->perPage($request))
            ->withQueryString();

        return TaskResource::collection($tasks);
    }

    #[Endpoint('Создать задачу', 'В архивный проект задачи не добавляются (403).')]
    #[ResponseFromApiResource(TaskResource::class, Task::class, 201, with: ['assignee', 'labels'])]
    #[ScribeResponse(['message' => 'The assignee must be a member of the team.', 'errors' => ['assignee_id' => ['The assignee must be a member of the team.']]], 422, 'Исполнитель не из команды')]
    public function store(StoreTaskRequest $request, Project $project, CreateTask $createTask): JsonResponse
    {
        $task = $createTask->handle($project, $request->user(), $request->validated());

        return TaskResource::make($this->withDetails($task))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[Endpoint('Задача')]
    #[ResponseFromApiResource(TaskResource::class, Task::class, with: ['assignee', 'labels'], withCount: ['comments'])]
    public function show(Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        return TaskResource::make($this->withDetails($task));
    }

    #[Endpoint('Изменить задачу', 'Частичное обновление: передавайте только изменённые поля. Смена статуса на `done` проставляет `completed_at`, возврат в работу его сбрасывает.')]
    #[ResponseFromApiResource(TaskResource::class, Task::class, with: ['assignee', 'labels'], withCount: ['comments'])]
    public function update(UpdateTaskRequest $request, Task $task, UpdateTask $updateTask): TaskResource
    {
        $task = $updateTask->handle($task, $request->validated());

        return TaskResource::make($this->withDetails($task));
    }

    #[Endpoint('Удалить задачу')]
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
        return $task->load(['assignee', 'labels'])->loadCount('comments');
    }
}
