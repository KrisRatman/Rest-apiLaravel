<?php

namespace App\Policies;

use App\Models\Label;
use App\Models\Team;
use App\Models\User;
use App\Policies\Concerns\ChecksTeamRole;
use Illuminate\Auth\Access\Response;

class LabelPolicy
{
    use ChecksTeamRole;

    public function viewAny(User $user, Team $team): Response
    {
        return $this->checkMember($user, $team->id);
    }

    public function create(User $user, Team $team): Response
    {
        return $this->checkManager($user, $team->id);
    }

    public function update(User $user, Label $label): Response
    {
        return $this->checkManager($user, $label->team_id);
    }

    public function delete(User $user, Label $label): Response
    {
        return $this->checkManager($user, $label->team_id);
    }
}
