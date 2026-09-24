<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * Задача удалена раньше, чем ушло письмо, — задание просто отбрасывается.
 */
#[DeleteWhenMissingModels]
class TaskAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Task $task,
        public User $assignedBy,
    ) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("New task: {$this->task->title}")
            ->line("{$this->assignedBy->name} assigned you a task in \"{$this->task->project->name}\".")
            ->line("**{$this->task->title}**")
            ->line('Priority: '.$this->task->priority->value);

        if ($this->task->due_date !== null) {
            $message->line('Due: '.$this->task->due_date->toFormattedDateString());
        }

        return $message->action('Open task', rtrim(config('app.frontend_url'), '/')."/tasks/{$this->task->id}");
    }
}
