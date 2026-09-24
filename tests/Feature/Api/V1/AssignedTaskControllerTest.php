<?php

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;

it('lists tasks assigned to the current user across teams', function () {
    $user = signIn();
    $firstTeam = Team::factory()->for($user, 'owner')->create();
    $secondTeam = Team::factory()->for($user, 'owner')->create();
    $first = Task::factory()->for(Project::factory()->for($firstTeam))->create(['assignee_id' => $user->id]);
    $second = Task::factory()->for(Project::factory()->for($secondTeam))->create(['assignee_id' => $user->id]);
    Task::factory()->for(Project::factory()->for($firstTeam))->create();

    $ids = $this->getJson('/api/v1/me/tasks')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->json('data.*.id');

    expect($ids)->toEqualCanonicalizing([$first->id, $second->id]);
});

it('applies the same filters as the project task list', function () {
    $user = signIn();
    $project = Project::factory()->for(Team::factory()->for($user, 'owner'))->create();
    $open = Task::factory()->for($project)->create(['assignee_id' => $user->id]);
    Task::factory()->for($project)->done()->create(['assignee_id' => $user->id]);

    $this->getJson('/api/v1/me/tasks?filter[status]='.TaskStatus::Todo->value)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $open->id);
});

it('returns 401 without a token', function () {
    $this->getJson('/api/v1/me/tasks')->assertUnauthorized();
});
