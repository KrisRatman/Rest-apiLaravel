<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Teams\InviteToTeam;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreInvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;
use Knuckles\Scribe\Attributes\Subgroup;

#[Group('Команды')]
#[Subgroup('Приглашения', 'Приглашение по email, в том числе человека без аккаунта. Письмо с кодом уходит через очередь, приглашение действует 7 дней.')]
class TeamInvitationController extends Controller
{
    private const INVITATION_EXAMPLE = [
        'id' => 1,
        'team_id' => 1,
        'email' => 'new.colleague@example.com',
        'role' => 'member',
        'invited_by' => 1,
        'expires_at' => '2026-10-01T10:00:00.000000Z',
        'is_expired' => false,
        'created_at' => '2026-09-24T10:00:00.000000Z',
    ];

    #[Endpoint('Приглашения команды', 'Все неиспользованные приглашения, включая просроченные (`is_expired`). Доступно владельцу и админам.')]
    #[ScribeResponse(['data' => [self::INVITATION_EXAMPLE]])]
    public function index(Team $team): AnonymousResourceCollection
    {
        Gate::authorize('manageMembers', $team);

        return InvitationResource::collection($team->invitations()->latest()->latest('id')->get());
    }

    #[Endpoint('Пригласить по email', 'Отправляет письмо с кодом приглашения. Просроченное приглашение на тот же адрес заменяется новым. Не больше 20 приглашений в минуту.')]
    #[ScribeResponse(['data' => self::INVITATION_EXAMPLE], 201)]
    #[ScribeResponse(['message' => 'This email has already been invited.', 'errors' => ['email' => ['This email has already been invited.']]], 422, 'Приглашение уже отправлено')]
    public function store(StoreInvitationRequest $request, Team $team, InviteToTeam $invite): JsonResponse
    {
        $invitation = $invite->handle(
            $team,
            $request->user(),
            $request->validated('email'),
            TeamRole::from($request->validated('role')),
        );

        return InvitationResource::make($invitation)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[Endpoint('Отозвать приглашение', 'Код из письма перестаёт действовать.')]
    #[ScribeResponse(status: 204)]
    public function destroy(TeamInvitation $invitation): Response
    {
        Gate::authorize('manageMembers', $invitation->team);

        $invitation->delete();

        return response()->noContent();
    }
}
