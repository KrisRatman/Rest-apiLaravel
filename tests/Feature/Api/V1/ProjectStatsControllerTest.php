<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

it('returns aggregated task statistics of the project', function () {
    $project = Project::factory()->create();
    $anna = memberOf($project->team);
    Task::factory()->for($project)->priority(TaskPriority::Urgent)->overdue()->create(['assignee_id' => $anna->id]);
    Task::factory()->for($project)->priority(TaskPriority::Urgent)->create(['assignee_id' => $anna->id, 'status' => TaskStatus::InProgress]);
    Task::factory()->for($project)->priority(TaskPriority::Low)->create(['due_date' => null]);
    Task::factory()->for($project)->done()->create(['assignee_id' => $anna->id]);
    Task::factory()->done()->create();
    signIn($anna);

    $this->getJson("/api/v1/projects/{$project->id}/stats")
        ->assertOk()
        ->assertJsonPath('data.total', 4)
        ->assertJsonPath('data.open', 3)
        ->assertJsonPath('data.overdue', 1)
        ->assertJsonPath('data.completed_last_7_days', 1)
        ->assertJsonPath('data.by_status', ['todo' => 2, 'in_progress' => 1, 'review' => 0, 'done' => 1])
        ->assertJsonPath('data.open_by_priority', ['low' => 1, 'medium' => 0, 'high' => 0, 'urgent' => 2])
        ->assertJsonPath('data.open_by_assignee', [
            ['user_id' => $anna->id, 'name' => $anna->name, 'open' => 2],
            ['user_id' => null, 'name' => null, 'open' => 1],
        ]);
});

it('serves the cached result until a task of the project changes', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create();
    signIn(memberOf($project->team));

    $this->getJson("/api/v1/projects/{$project->id}/stats")->assertJsonPath('data.by_status.todo', 1);

    // Изменение в обход моделей не сбрасывает кэш — ответ остаётся прежним.
    DB::table('tasks')->where('id', $task->id)->update(['status' => TaskStatus::Review->value]);
    $this->getJson("/api/v1/projects/{$project->id}/stats")->assertJsonPath('data.by_status.todo', 1);

    // Изменение через модель (как делает API) сбрасывает кэш.
    $task->refresh()->update(['status' => TaskStatus::Done]);
    $this->getJson("/api/v1/projects/{$project->id}/stats")
        ->assertJsonPath('data.by_status.todo', 0)
        ->assertJsonPath('data.by_status.done', 1);
});

it('refreshes the statistics after a task is created through the api', function () {
    $project = Project::factory()->create();
    signIn($project->team->owner);
    $this->getJson("/api/v1/projects/{$project->id}/stats")->assertJsonPath('data.total', 0);

    $this->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'New'])->assertCreated();

    $this->getJson("/api/v1/projects/{$project->id}/stats")->assertJsonPath('data.total', 1);
});

it('refreshes the statistics when a removed member loses their tasks', function () {
    $project = Project::factory()->create();
    $member = memberOf($project->team);
    Task::factory()->for($project)->create(['assignee_id' => $member->id]);
    signIn($project->team->owner);
    $this->getJson("/api/v1/projects/{$project->id}/stats")->assertJsonPath('data.open_by_assignee.0.user_id', $member->id);

    $this->deleteJson("/api/v1/teams/{$project->team_id}/members/{$member->id}")->assertNoContent();

    $this->getJson("/api/v1/projects/{$project->id}/stats")->assertJsonPath('data.open_by_assignee.0.user_id', null);
});

it('returns 404 to a user from another team', function () {
    $project = Project::factory()->create();
    signIn(Team::factory()->create()->owner);

    $this->getJson("/api/v1/projects/{$project->id}/stats")->assertNotFound();
});
