<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\ChecksTeamRole;
use Illuminate\Auth\Access\Response;

class CommentPolicy
{
    use ChecksTeamRole;

    public function viewAny(User $user, Task $task): Response
    {
        return $this->checkMember($user, $task->project->team_id);
    }

    public function create(User $user, Task $task): Response
    {
        return $this->checkMember($user, $task->project->team_id);
    }

    /**
     * Текст комментария может править только автор.
     */
    public function update(User $user, Comment $comment): Response
    {
        return $this->checkRole(
            $user,
            $comment->task->project->team_id,
            fn () => $comment->user_id === $user->id,
        );
    }

    /**
     * Удалить можно свой комментарий, а владелец и админ модерируют любые.
     */
    public function delete(User $user, Comment $comment): Response
    {
        return $this->checkRole(
            $user,
            $comment->task->project->team_id,
            fn (TeamRole $role) => $role->canManageTeam() || $comment->user_id === $user->id,
        );
    }
}
