<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ChangeMemberRole
{
    /**
     * @throws ValidationException
     */
    public function handle(Team $team, User $member, TeamRole $role): User
    {
        if ($member->id === $team->owner_id) {
            throw ValidationException::withMessages([
                'role' => 'The owner role can only be changed by transferring ownership.',
            ]);
        }

        $team->members()->updateExistingPivot($member->id, ['role' => $role]);

        return $team->members()->findOrFail($member->id);
    }
}
