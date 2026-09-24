<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AcceptInvitation
{
    /**
     * Токен из письма доказывает, что пользователь владеет адресом, на который
     * пришло приглашение. Дополнительно email аккаунта должен совпадать с адресом
     * приглашения, чтобы пересланное письмо не добавило в команду другого человека.
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function handle(User $user, string $token): Team
    {
        return DB::transaction(function () use ($user, $token): Team {
            $invitation = TeamInvitation::query()
                ->where('token_hash', TeamInvitation::hashToken($token))
                ->lockForUpdate()
                ->first();

            if ($invitation === null || $invitation->isExpired()) {
                throw ValidationException::withMessages([
                    'token' => 'The invitation is invalid or has expired.',
                ]);
            }

            if ($invitation->email !== Str::lower($user->email)) {
                throw new AuthorizationException('This invitation was sent to another email address.');
            }

            $team = $invitation->team;

            // Повторное принятие ничего не ломает: пользователь уже в команде.
            if ($user->roleIn($team) === null) {
                $team->members()->attach($user, ['role' => $invitation->role]);
            }

            $invitation->delete();

            return $team;
        });
    }
}
