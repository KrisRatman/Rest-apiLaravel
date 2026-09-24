<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateTask
{
    /**
     * Частичное обновление: меняются только переданные поля.
     * Метки заменяются целиком, если передан label_ids.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Task $task, array $data): Task
    {
        return DB::transaction(function () use ($task, $data): Task {
            $task->update(Arr::except($data, 'label_ids'));

            if (array_key_exists('label_ids', $data)) {
                $task->labels()->sync($data['label_ids'] ?? []);
            }

            return $task;
        });
    }
}
