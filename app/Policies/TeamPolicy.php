<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use App\Policies\Concerns\ChecksTeamRole;
use Illuminate\Auth\Access\Response;

class TeamPolicy
{
    use ChecksTeamRole;

    public function view(User $user, Team $team): Response
    {
        return $this->checkMember($user, $team->id);
    }

    public function update(User $user, Team $team): Response
    {
        return $this->checkManager($user, $team->id);
    }

    public function delete(User $user, Team $team): Response
    {
        return $this->checkOwner($user, $team->id);
    }

    /**
     * Добавлять участников, менять им роли и исключать из команды.
     */
    public function manageMembers(User $user, Team $team): Response
    {
        return $this->checkManager($user, $team->id);
    }

    public function transferOwnership(User $user, Team $team): Response
    {
        return $this->checkOwner($user, $team->id);
    }
}
