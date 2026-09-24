<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

#[Group('Проекты', 'Проекты команды. Смотреть может любой участник, создавать, менять и удалять — владелец и админы.')]
class ProjectController extends Controller
{
    #[Endpoint('Проекты команды', 'С количеством задач: всего и незавершённых.')]
    #[QueryParam('status', 'string', 'Только проекты с этим статусом: `active` или `archived`.', required: false, example: 'active')]
    #[QueryParam('per_page', 'integer', 'Размер страницы, 1–100.', required: false, example: 15)]
    #[ResponseFromApiResource(ProjectResource::class, Project::class, collection: true, paginate: 15)]
    public function index(Request $request, Team $team): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Project::class, $team]);

        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
        ]);

        $projects = $team->projects()
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->withCount($this->taskCounts())
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($this->perPage($request));

        return ProjectResource::collection($projects);
    }

    #[Endpoint('Создать проект')]
    #[ResponseFromApiResource(ProjectResource::class, Project::class, 201)]
    public function store(StoreProjectRequest $request, Team $team): JsonResponse
    {
        $project = new Project($request->validated());
        $project->team()->associate($team);
        $project->creator()->associate($request->user());
        $project->save();

        return ProjectResource::make($project->loadCount($this->taskCounts()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[Endpoint('Проект')]
    #[ResponseFromApiResource(ProjectResource::class, Project::class)]
    #[ScribeResponse(['message' => 'Resource not found.'], 404, 'Проекта нет или он в чужой команде')]
    public function show(Project $project): ProjectResource
    {
        Gate::authorize('view', $project);

        return ProjectResource::make($project->loadCount($this->taskCounts()));
    }

    #[Endpoint('Изменить проект', 'Через `status: archived` проект уходит в архив.')]
    #[ResponseFromApiResource(ProjectResource::class, Project::class)]
    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $project->update($request->validated());

        return ProjectResource::make($project->loadCount($this->taskCounts()));
    }

    #[Endpoint('Удалить проект', 'Вместе со всеми задачами. Если задачи нужно сохранить, переведите проект в архив.')]
    #[ScribeResponse(status: 204)]
    public function destroy(Project $project): Response
    {
        Gate::authorize('delete', $project);

        $project->delete();

        return response()->noContent();
    }

    /**
     * @return array<string|int, mixed>
     */
    private function taskCounts(): array
    {
        return [
            'tasks',
            'tasks as open_tasks_count' => fn (Builder $query) => $query->where('status', '!=', TaskStatus::Done),
        ];
    }
}
