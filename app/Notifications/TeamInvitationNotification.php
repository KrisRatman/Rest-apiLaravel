<?php

namespace App\Notifications;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * Письмо с приглашением в команду. Открытый токен есть только здесь, поэтому
 * задание в очереди шифруется (ShouldBeEncrypted), а в базе хранится лишь хеш.
 */
#[DeleteWhenMissingModels]
class TeamInvitationNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        public TeamInvitation $invitation,
        public string $token,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $team = $this->invitation->team;
        $inviter = $this->invitation->inviter?->name ?? 'A teammate';
        $url = rtrim(config('app.frontend_url'), '/').'/invitations/accept?token='.$this->token;

        return (new MailMessage)
            ->subject("Invitation to join {$team->name}")
            ->line("{$inviter} invited you to the team \"{$team->name}\" as {$this->invitation->role->value}.")
            ->action('Accept invitation', $url)
            ->line('Sign in or register with this email address to accept. Invitation code:')
            ->line($this->token)
            ->line('The invitation expires on '.$this->invitation->expires_at->toFormattedDateString().'.');
    }
}
