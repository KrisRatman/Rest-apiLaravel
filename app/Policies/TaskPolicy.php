<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\ChecksTeamRole;
use Illuminate\Auth\Access\Response;

class TaskPolicy
{
    use ChecksTeamRole;

    public function viewAny(User $user, Project $project): Response
    {
        return $this->checkMember($user, $project->team_id);
    }

    public function create(User $user, Project $project): Response
    {
        $membership = $this->checkMember($user, $project->team_id);

        if ($membership->denied()) {
            return $membership;
        }

        return $project->isArchived()
            ? Response::deny('Tasks cannot be added to an archived project.')
            : Response::allow();
    }

    public function view(User $user, Task $task): Response
    {
        return $this->checkMember($user, $task->project->team_id);
    }

    public function update(User $user, Task $task): Response
    {
        return $this->checkMember($user, $task->project->team_id);
    }

    /**
     * Участник может удалить только созданную им задачу, владелец и админ — любую.
     */
    public function delete(User $user, Task $task): Response
    {
        return $this->checkRole(
            $user,
            $task->project->team_id,
            fn (TeamRole $role) => $role->canManageTeam() || $task->creator_id === $user->id,
        );
    }
}
