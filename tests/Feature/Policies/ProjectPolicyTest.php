<?php

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('project abilities per role', function (string $ability, bool $onTeam, array $allowed) {
    $project = Project::factory()->create();
    $arguments = $onTeam ? [Project::class, $project->team] : $project;

    foreach (TeamRole::cases() as $role) {
        $response = Gate::forUser(memberOf($project->team, $role))->inspect($ability, $arguments);

        expect($response->allowed())->toBe(in_array($role, $allowed, true), "{$role->value} → {$ability}");
    }
})->with([
    'view any' => ['viewAny', true, [TeamRole::Owner, TeamRole::Admin, TeamRole::Member]],
    'create' => ['create', true, [TeamRole::Owner, TeamRole::Admin]],
    'view' => ['view', false, [TeamRole::Owner, TeamRole::Admin, TeamRole::Member]],
    'update' => ['update', false, [TeamRole::Owner, TeamRole::Admin]],
    'delete' => ['delete', false, [TeamRole::Owner, TeamRole::Admin]],
]);

test('outsiders are denied as not found', function (string $ability) {
    $response = Gate::forUser(User::factory()->create())->inspect($ability, Project::factory()->create());

    expect($response->status())->toBe(404);
})->with(['view', 'update', 'delete']);
