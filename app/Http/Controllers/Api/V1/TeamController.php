<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Teams\CreateTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreTeamRequest;
use App\Http\Requests\Team\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;

#[Group('Команды', 'Команда объединяет людей и проекты. Роли в команде: `owner` (один), `admin`, `member`. Если вы не состоите в команде, её ресурсы отвечают 404.')]
class TeamController extends Controller
{
    /**
     * Пример команды для документации. Задан вручную: фабрика не умеет
     * создавать участников с ролью в pivot, которые нужны для members_count.
     */
    public const TEAM_EXAMPLE = [
        'id' => 1,
        'name' => 'Mobile Team',
        'description' => 'iOS and Android apps of the online store',
        'owner_id' => 1,
        'my_role' => 'owner',
        'members_count' => 3,
        'projects_count' => 2,
        'created_at' => '2026-09-24T10:00:00.000000Z',
        'updated_at' => '2026-09-24T10:00:00.000000Z',
    ];

    #[Endpoint('Мои команды', 'Команды, в которых состоит пользователь, с его ролью в каждой (`my_role`).')]
    #[QueryParam('per_page', 'integer', 'Размер страницы, 1–100.', required: false, example: 15)]
    #[ScribeResponse(['data' => [self::TEAM_EXAMPLE], 'links' => ['first' => '…', 'last' => '…', 'prev' => null, 'next' => null], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 1]])]
    public function index(Request $request): AnonymousResourceCollection
    {
        $teams = $request->user()->teams()
            ->withCount(['members', 'projects'])
            ->orderBy('name')
            ->orderBy('teams.id')
            ->paginate($this->perPage($request));

        return TeamResource::collection($teams);
    }

    #[Endpoint('Создать команду', 'Создатель становится владельцем (`owner`).')]
    #[ScribeResponse(['data' => self::TEAM_EXAMPLE], 201)]
    public function store(StoreTeamRequest $request, CreateTeam $createTeam): JsonResponse
    {
        $team = $createTeam->handle($request->user(), $request->validated());

        return TeamResource::make($this->forCurrentUser($request, $team))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[Endpoint('Команда')]
    #[ScribeResponse(['data' => self::TEAM_EXAMPLE])]
    #[ScribeResponse(['message' => 'Resource not found.'], 404, 'Команды нет или вы в ней не состоите')]
    public function show(Request $request, Team $team): TeamResource
    {
        Gate::authorize('view', $team);

        return TeamResource::make($this->forCurrentUser($request, $team));
    }

    #[Endpoint('Изменить команду', 'Доступно владельцу и админам.')]
    #[ScribeResponse(['data' => self::TEAM_EXAMPLE])]
    #[ScribeResponse(['message' => 'This action is unauthorized.'], 403, 'Недостаточно прав')]
    public function update(UpdateTeamRequest $request, Team $team): TeamResource
    {
        $team->update($request->validated());

        return TeamResource::make($this->forCurrentUser($request, $team));
    }

    #[Endpoint('Удалить команду', 'Только владелец. Удаляются все проекты, задачи и метки команды.')]
    #[ScribeResponse(status: 204)]
    public function destroy(Team $team): Response
    {
        Gate::authorize('delete', $team);

        $team->delete();

        return response()->noContent();
    }

    /**
     * Перечитывает команду через членство, чтобы в ответе была роль текущего пользователя.
     */
    private function forCurrentUser(Request $request, Team $team): Team
    {
        return $request->user()->teams()
            ->withCount(['members', 'projects'])
            ->findOrFail($team->id);
    }
}
