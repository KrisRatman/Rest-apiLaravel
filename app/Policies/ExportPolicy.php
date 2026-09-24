<?php

namespace App\Policies;

use App\Models\Export;
use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\ChecksTeamRole;
use Illuminate\Auth\Access\Response;

class ExportPolicy
{
    use ChecksTeamRole;

    /**
     * Выгрузить задачи может любой участник команды: он и так видит их через API.
     */
    public function create(User $user, Project $project): Response
    {
        return $this->checkMember($user, $project->team_id);
    }

    /**
     * Выгрузка личная: видит и скачивает её только автор, и только пока он в команде.
     */
    public function view(User $user, Export $export): Response
    {
        if ($export->user_id !== $user->id) {
            return Response::denyAsNotFound();
        }

        return $this->checkMember($user, $export->project->team_id);
    }
}
