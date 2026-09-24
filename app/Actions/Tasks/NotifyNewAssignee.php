<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;

class NotifyNewAssignee
{
    /**
     * Пишет исполнителю, если задачу на него назначил кто-то другой.
     * Письмо уходит после коммита транзакции, в которой сохранялась задача.
     */
    public function handle(Task $task, User $actor): void
    {
        if ($task->assignee_id === null || $task->assignee_id === $actor->id) {
            return;
        }

        // Связь могла быть загружена до смены исполнителя — перечитываем.
        $task->load('assignee')->assignee->notify(
            (new TaskAssignedNotification($task, $actor))->afterCommit(),
        );
    }
}
