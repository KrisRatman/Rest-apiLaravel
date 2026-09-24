<?php

namespace App\Actions\Exports;

use App\Jobs\ExportProjectTasks;
use App\Models\Export;
use App\Models\Project;
use App\Models\User;

class StartExport
{
    /**
     * Записывает выгрузку со статусом pending и отдаёт сборку файла очереди.
     *
     * @param  array{filter?: array<string, mixed>, sort?: string}  $filters  те же параметры, что у списка задач
     */
    public function handle(Project $project, User $user, array $filters): Export
    {
        $export = new Export(['filters' => $filters]);
        $export->project()->associate($project);
        $export->user()->associate($user);
        $export->save();

        ExportProjectTasks::dispatch($export)->afterCommit();

        return $export;
    }
}
