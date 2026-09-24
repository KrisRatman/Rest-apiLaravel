<?php

use App\Models\Project;
use App\Models\Task;

beforeEach(function () {
    config([
        'api.deprecations.v1' => [
            'deprecated_at' => '2026-09-24',
            'sunset_at' => '2027-03-31',
        ],
    ]);
});

it('marks v1 task endpoints as deprecated and links to the v2 successor', function () {
    $project = Project::factory()->create();
    signIn(memberOf($project->team));

    $this->getJson("/api/v1/projects/{$project->id}/tasks")
        ->assertOk()
        ->assertHeader('Deprecation', '@1790208000')
        ->assertHeader('Sunset', 'Wed, 31 Mar 2027 00:00:00 GMT')
        ->assertHeader('Link', '<'.url("/api/v2/projects/{$project->id}/tasks").'>; rel="successor-version"');
});

it('links a single v1 task to the same task in v2', function () {
    $task = Task::factory()->create();
    signIn(memberOf($task->project->team));

    $this->getJson("/api/v1/tasks/{$task->id}")
        ->assertOk()
        ->assertHeader('Link', '<'.url("/api/v2/tasks/{$task->id}").'>; rel="successor-version"');
});

it('adds the headers to error responses too', function () {
    $task = Task::factory()->create();
    signIn();

    $this->getJson("/api/v1/tasks/{$task->id}")
        ->assertNotFound()
        ->assertHeader('Deprecation');
});

it('does not mark v1 endpoints without a v2 successor', function () {
    signIn();

    $this->getJson('/api/v1/teams')
        ->assertOk()
        ->assertHeaderMissing('Deprecation')
        ->assertHeaderMissing('Sunset');
});

it('does not mark v2 endpoints', function () {
    signIn();

    $this->getJson('/api/v2/me/tasks')
        ->assertOk()
        ->assertHeaderMissing('Deprecation')
        ->assertHeaderMissing('Link');
});
