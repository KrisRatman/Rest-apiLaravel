<?php

namespace App\Notifications;

use App\Models\Export;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * Сообщает автору выгрузки, что файл готов или что собрать его не удалось.
 */
#[DeleteWhenMissingModels]
class ExportFinishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Export $export) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $project = $this->export->project->name;

        if (! $this->export->isCompleted()) {
            return (new MailMessage)
                ->error()
                ->subject("Export of \"{$project}\" failed")
                ->line("We could not export the tasks of \"{$project}\". Please try again later.");
        }

        return (new MailMessage)
            ->subject("Export of \"{$project}\" is ready")
            ->line("{$this->export->rows_count} tasks of \"{$project}\" were exported to CSV.")
            ->line('Download the file with the API request:')
            ->line("`GET /api/v1/exports/{$this->export->id}/download`")
            ->line('The file is kept for '.Export::KEEP_DAYS.' days.');
    }
}
