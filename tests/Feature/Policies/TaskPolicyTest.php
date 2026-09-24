<?php

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('every member can view, create and update tasks', function (TeamRole $role) {
    $task = Task::factory()->create();
    $gate = Gate::forUser(memberOf($task->project->team, $role));

    expect($gate->allows('viewAny', [Task::class, $task->project]))->toBeTrue()
        ->and($gate->allows('create', [Task::class, $task->project]))->toBeTrue()
        ->and($gate->allows('view', $task))->toBeTrue()
        ->and($gate->allows('update', $task))->toBeTrue();
})->with(TeamRole::cases());

test('deleting a task created by someone else', function (TeamRole $role, bool $allowed) {
    $task = Task::factory()->create();

    expect(Gate::forUser(memberOf($task->project->team, $role))->allows('delete', $task))->toBe($allowed);
})->with([
    'owner' => [TeamRole::Owner, true],
    'admin' => [TeamRole::Admin, true],
    'member' => [TeamRole::Member, false],
]);

test('a member can delete a task they created', function () {
    $project = Project::factory()->create();
    $member = memberOf($project->team);
    $task = Task::factory()->for($project)->create(['creator_id' => $member->id]);

    expect(Gate::forUser($member)->allows('delete', $task))->toBeTrue();
});

test('nobody can add tasks to an archived project', function (TeamRole $role) {
    $project = Project::factory()->archived()->create();

    $response = Gate::forUser(memberOf($project->team, $role))->inspect('create', [Task::class, $project]);

    expect($response->denied())->toBeTrue()
        ->and($response->message())->toBe('Tasks cannot be added to an archived project.');
})->with(TeamRole::cases());

test('outsiders are denied as not found', function (string $ability) {
    $response = Gate::forUser(User::factory()->create())->inspect($ability, Task::factory()->create());

    expect($response->status())->toBe(404);
})->with(['view', 'update', 'delete']);
