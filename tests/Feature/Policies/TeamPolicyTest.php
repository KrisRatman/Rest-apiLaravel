<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('team abilities per role', function (string $ability, array $allowed) {
    $team = Team::factory()->create();

    foreach (TeamRole::cases() as $role) {
        $response = Gate::forUser(memberOf($team, $role))->inspect($ability, $team);

        expect($response->allowed())->toBe(in_array($role, $allowed, true), "{$role->value} → {$ability}");
    }
})->with([
    'view' => ['view', [TeamRole::Owner, TeamRole::Admin, TeamRole::Member]],
    'update' => ['update', [TeamRole::Owner, TeamRole::Admin]],
    'delete' => ['delete', [TeamRole::Owner]],
    'manage members' => ['manageMembers', [TeamRole::Owner, TeamRole::Admin]],
    'transfer ownership' => ['transferOwnership', [TeamRole::Owner]],
]);

test('outsiders are denied as not found', function (string $ability) {
    $response = Gate::forUser(User::factory()->create())->inspect($ability, Team::factory()->create());

    expect($response->status())->toBe(404);
})->with(['view', 'update', 'delete', 'manageMembers', 'transferOwnership']);
