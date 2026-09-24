<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\ProjectStatistics;

/**
 * Любое изменение задачи делает статистику её проекта устаревшей.
 */
class TaskObserver
{
    public function saved(Task $task): void
    {
        ProjectStatistics::forget($task->project_id);
    }

    public function deleted(Task $task): void
    {
        ProjectStatistics::forget($task->project_id);
    }
}
