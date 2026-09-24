<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Teams\AddTeamMember;
use App\Actions\Teams\ChangeMemberRole;
use App\Actions\Teams\RemoveTeamMember;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreMemberRequest;
use App\Http\Requests\Team\UpdateMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;
use Knuckles\Scribe\Attributes\Subgroup;

#[Group('Команды')]
#[Subgroup('Участники', 'Состав команды и роли. Управлять участниками могут владелец и админы.')]
class TeamMemberController extends Controller
{
    private const MEMBER_EXAMPLE = ['id' => 2, 'name' => 'Иван Петров', 'email' => 'ivan@example.com', 'role' => 'member', 'joined_at' => '2026-09-24T10:00:00.000000Z'];

    #[Endpoint('Участники команды', 'Видит любой участник команды.')]
    #[QueryParam('per_page', 'integer', 'Размер страницы, 1–100.', required: false, example: 50)]
    #[ScribeResponse(['data' => [self::MEMBER_EXAMPLE], 'links' => ['first' => '…', 'last' => '…', 'prev' => null, 'next' => null], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 1]])]
    public function index(Request $request, Team $team): AnonymousResourceCollection
    {
        Gate::authorize('view', $team);

        $members = $team->members()
            ->orderBy('team_user.created_at')
            ->orderBy('users.id')
            ->paginate($this->perPage($request, 50));

        return MemberResource::collection($members);
    }

    #[Endpoint('Добавить участника', 'Добавляет зарегистрированного пользователя по email.')]
    #[ScribeResponse(['data' => self::MEMBER_EXAMPLE], 201)]
    #[ScribeResponse(['message' => 'This user is already a member of the team.', 'errors' => ['email' => ['This user is already a member of the team.']]], 422, 'Уже в команде')]
    public function store(StoreMemberRequest $request, Team $team, AddTeamMember $addMember): JsonResponse
    {
        $member = $addMember->handle(
            $team,
            $request->validated('email'),
            TeamRole::from($request->validated('role')),
        );

        return MemberResource::make($member)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[Endpoint('Сменить роль участника', 'Роль владельца так не меняется — для этого есть передача владения.')]
    #[ScribeResponse(['data' => ['role' => 'admin'] + self::MEMBER_EXAMPLE])]
    public function update(UpdateMemberRequest $request, Team $team, User $user, ChangeMemberRole $changeRole): MemberResource
    {
        $member = $team->members()->findOrFail($user->id);

        return MemberResource::make(
            $changeRole->handle($team, $member, TeamRole::from($request->validated('role'))),
        );
    }

    #[Endpoint('Исключить участника или выйти из команды', 'Владелец и админы исключают других, любой участник может удалить себя сам (выйти). Задачи команды, назначенные на участника, остаются без исполнителя. Владелец выйти не может — сначала передайте владение.')]
    #[ScribeResponse(status: 204)]
    #[ScribeResponse(['message' => 'The team owner cannot leave the team. Transfer ownership first.', 'errors' => ['user' => ['The team owner cannot leave the team. Transfer ownership first.']]], 422, 'Попытка удалить владельца')]
    public function destroy(Request $request, Team $team, User $user, RemoveTeamMember $removeMember): Response
    {
        Gate::authorize($request->user()->is($user) ? 'view' : 'manageMembers', $team);

        $member = $team->members()->findOrFail($user->id);

        $removeMember->handle($team, $member);

        return response()->noContent();
    }
}
