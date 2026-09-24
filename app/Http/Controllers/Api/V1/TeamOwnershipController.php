<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Teams\TransferOwnership;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\TransferOwnershipRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Models\User;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;
use Knuckles\Scribe\Attributes\Subgroup;

#[Group('Команды')]
#[Subgroup('Участники')]
class TeamOwnershipController extends Controller
{
    #[Endpoint('Передать владение', 'Только владелец. Новый владелец должен состоять в команде, прежний становится админом.')]
    #[ScribeResponse(['data' => ['owner_id' => 2, 'my_role' => 'admin'] + TeamController::TEAM_EXAMPLE])]
    public function store(TransferOwnershipRequest $request, Team $team, TransferOwnership $transfer): TeamResource
    {
        $newOwner = User::findOrFail($request->validated('user_id'));

        $team = $transfer->handle($team, $newOwner);

        return TeamResource::make(
            $request->user()->teams()->withCount(['members', 'projects'])->findOrFail($team->id),
        );
    }
}
