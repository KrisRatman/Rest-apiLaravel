<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteToTeam
{
    /**
     * Создаёт приглашение и ставит письмо с токеном в очередь.
     * У адресата может ещё не быть аккаунта — он зарегистрируется и примет приглашение.
     *
     * @throws ValidationException
     */
    public function handle(Team $team, User $inviter, string $email, TeamRole $role): TeamInvitation
    {
        $email = Str::lower(trim($email));

        $isMember = $team->members()->where('email', $email)->exists();
        if ($isMember) {
            throw ValidationException::withMessages([
                'email' => 'This user is already a member of the team.',
            ]);
        }

        return DB::transaction(function () use ($team, $inviter, $email, $role): TeamInvitation {
            $existing = $team->invitations()->where('email', $email)->lockForUpdate()->first();

            if ($existing !== null && ! $existing->isExpired()) {
                throw ValidationException::withMessages([
                    'email' => 'This email has already been invited.',
                ]);
            }

            // Просроченное приглашение заменяем новым: у пары команда + email оно одно.
            $existing?->delete();

            $token = Str::random(40);

            $invitation = new TeamInvitation(['email' => $email, 'role' => $role]);
            $invitation->team()->associate($team);
            $invitation->inviter()->associate($inviter);
            $invitation->token_hash = TeamInvitation::hashToken($token);
            $invitation->expires_at = now()->addDays(TeamInvitation::LIFETIME_DAYS);
            $invitation->save();

            Notification::route('mail', $email)->notify(
                (new TeamInvitationNotification($invitation, $token))->afterCommit(),
            );

            return $invitation;
        });
    }
}
