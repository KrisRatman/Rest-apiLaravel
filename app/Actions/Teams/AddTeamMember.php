<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AddTeamMember
{
    /**
     * Добавляет зарегистрированного пользователя в команду по email.
     *
     * @throws ValidationException
     */
    public function handle(Team $team, string $email, TeamRole $role): User
    {
        $user = User::where('email', $email)->firstOrFail();

        if ($user->roleIn($team) !== null) {
            throw ValidationException::withMessages([
                'email' => 'This user is already a member of the team.',
            ]);
        }

        $team->members()->attach($user, ['role' => $role]);

        return $team->members()->findOrFail($user->id);
    }
}
