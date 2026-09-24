<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Teams\AcceptInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\AcceptInvitationRequest;
use App\Http\Resources\TeamResource;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;
use Knuckles\Scribe\Attributes\Subgroup;

#[Group('Команды')]
#[Subgroup('Приглашения')]
class InvitationAcceptanceController extends Controller
{
    #[Endpoint('Принять приглашение', 'Добавляет текущего пользователя в команду с ролью из приглашения. Email аккаунта должен совпадать с адресом, на который пришло письмо: сначала зарегистрируйтесь или войдите с ним.')]
    #[ScribeResponse(['data' => ['my_role' => 'member'] + TeamController::TEAM_EXAMPLE])]
    #[ScribeResponse(['message' => 'The invitation is invalid or has expired.', 'errors' => ['token' => ['The invitation is invalid or has expired.']]], 422, 'Код неверный или просрочен')]
    #[ScribeResponse(['message' => 'This invitation was sent to another email address.'], 403, 'Приглашение на другой email')]
    public function store(AcceptInvitationRequest $request, AcceptInvitation $accept): TeamResource
    {
        $team = $accept->handle($request->user(), $request->validated('token'));

        return TeamResource::make(
            $request->user()->teams()->withCount(['members', 'projects'])->findOrFail($team->id),
        );
    }
}
