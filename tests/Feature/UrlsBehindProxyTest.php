<?php

use App\Models\Project;
use App\Models\Task;
use App\Providers\AppServiceProvider;

it('builds links from the https APP_URL when the API is served through a proxy', function () {
    config(['app.url' => 'https://task-api.example.workers.dev']);
    (new AppServiceProvider(app()))->boot();

    $project = Project::factory()->create();
    Task::factory()->for($project)->count(2)->create();
    signIn(memberOf($project->team));

    $next = $this->get("http://origin.example.test/api/v2/projects/{$project->id}/tasks?per_page=1", ['Accept' => 'application/json'])
        ->assertOk()
        ->json('links.next');

    expect($next)->toStartWith("https://task-api.example.workers.dev/api/v2/projects/{$project->id}/tasks?");
});

it('keeps links on the request host when APP_URL is plain http', function () {
    config(['app.url' => 'http://localhost']);
    (new AppServiceProvider(app()))->boot();

    $project = Project::factory()->create();
    Task::factory()->for($project)->count(2)->create();
    signIn(memberOf($project->team));

    $next = $this->get("http://origin.example.test/api/v2/projects/{$project->id}/tasks?per_page=1", ['Accept' => 'application/json'])
        ->json('links.next');

    expect($next)->toStartWith('http://origin.example.test/');
});
