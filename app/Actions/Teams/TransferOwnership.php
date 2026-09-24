<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransferOwnership
{
    /**
     * Передаёт владение участнику команды. Прежний владелец остаётся админом.
     */
    public function handle(Team $team, User $newOwner): Team
    {
        return DB::transaction(function () use ($team, $newOwner): Team {
            $team->members()->updateExistingPivot($team->owner_id, ['role' => TeamRole::Admin]);
            $team->members()->updateExistingPivot($newOwner->id, ['role' => TeamRole::Owner]);

            $team->owner()->associate($newOwner);
            $team->save();

            return $team;
        });
    }
}
