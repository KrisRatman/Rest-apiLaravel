<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Policies\Concerns\ChecksTeamRole;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
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

    public function view(User $user, Project $project): Response
    {
        return $this->checkMember($user, $project->team_id);
    }

    public function update(User $user, Project $project): Response
    {
        return $this->checkManager($user, $project->team_id);
    }

    public function delete(User $user, Project $project): Response
    {
        return $this->checkManager($user, $project->team_id);
    }
}
