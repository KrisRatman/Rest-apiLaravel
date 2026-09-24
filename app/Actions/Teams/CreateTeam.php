<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTeam
{
    /**
     * @param  array{name: string, description?: string|null}  $data
     */
    public function handle(User $owner, array $data): Team
    {
        return DB::transaction(function () use ($owner, $data): Team {
            $team = new Team($data);
            $team->owner()->associate($owner);
            $team->save();

            $team->members()->attach($owner, ['role' => TeamRole::Owner]);

            return $team;
        });
    }
}
