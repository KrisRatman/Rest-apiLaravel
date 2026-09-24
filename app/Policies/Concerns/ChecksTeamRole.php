<?php

namespace App\Policies\Concerns;

use App\Enums\TeamRole;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\Response;

trait ChecksTeamRole
{
    /**
     * Проверяет роль пользователя в команде.
     *
     * Чужим пользователям отвечаем 404, а не 403: API не должен подтверждать,
     * что ресурс с таким id существует в другой команде.
     *
     * @param  (Closure(TeamRole): bool)|null  $allowed  null — достаточно состоять в команде
     */
    protected function checkRole(User $user, int $teamId, ?Closure $allowed = null): Response
    {
        $role = $user->roleIn($teamId);

        if ($role === null) {
            return Response::denyAsNotFound();
        }

        if ($allowed === null || $allowed($role)) {
            return Response::allow();
        }

        return Response::deny();
    }

    protected function checkMember(User $user, int $teamId): Response
    {
        return $this->checkRole($user, $teamId);
    }

    protected function checkManager(User $user, int $teamId): Response
    {
        return $this->checkRole($user, $teamId, fn (TeamRole $role) => $role->canManageTeam());
    }

    protected function checkOwner(User $user, int $teamId): Response
    {
        return $this->checkRole($user, $teamId, fn (TeamRole $role) => $role === TeamRole::Owner);
    }
}
