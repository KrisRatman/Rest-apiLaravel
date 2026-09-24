<?php

use App\Enums\TaskPriority;
use App\Models\Task;

it('computes priority_weight with the same weights as the enum', function (TaskPriority $priority) {
    $task = Task::factory()->priority($priority)->create();

    expect($task->fresh()->priority_weight)->toBe($priority->weight());
})->with(TaskPriority::cases());

it('puts tasks without a due date after any real date in due_date_sort', function () {
    $withoutDate = Task::factory()->create(['due_date' => null]);
    $withDate = Task::factory()->create(['due_date' => '2099-12-31']);

    expect(Task::query()->orderBy('due_date_sort')->pluck('id')->all())
        ->toBe([$withDate->id, $withoutDate->id]);
});
