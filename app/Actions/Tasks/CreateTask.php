<?php

namespace App\Actions\Tasks;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateTask
{
    /**
     * @param  array<string, mixed>  $data  провалидированные поля задачи и необязательный label_ids
     */
    public function handle(Project $project, User $creator, array $data): Task
    {
        return DB::transaction(function () use ($project, $creator, $data): Task {
            $task = new Task(Arr::except($data, 'label_ids'));
            $task->project()->associate($project);
            $task->creator()->associate($creator);
            $task->save();

            $task->labels()->sync($data['label_ids'] ?? []);

            return $task;
        });
    }
}
