<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;

it('lists tasks assigned to the current user page by page', function () {
    $user = signIn();
    $project = Project::factory()->for(Team::factory()->for($user, 'owner'))->create();
    $sooner = Task::factory()->for($project)->create(['assignee_id' => $user->id, 'due_date' => '2026-10-01']);
    $later = Task::factory()->for($project)->create(['assignee_id' => $user->id, 'due_date' => '2026-10-05']);
    $withoutDate = Task::factory()->for($project)->create(['assignee_id' => $user->id, 'due_date' => null]);
    Task::factory()->for($project)->create();

    $firstPage = $this->getJson('/api/v2/me/tasks?sort=due_date&per_page=2')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$sooner->id, $later->id])
        ->assertJsonPath('data.0.status.value', 'todo');

    $this->getJson($firstPage->json('links.next'))
        ->assertOk()
        ->assertJsonPath('data.*.id', [$withoutDate->id])
        ->assertJsonPath('meta.next_cursor', null);
});

it('returns 401 without a token', function () {
    $this->getJson('/api/v2/me/tasks')->assertUnauthorized();
});
