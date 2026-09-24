<?php

use App\Enums\TeamRole;
use App\Models\Label;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('label abilities per role', function (string $ability, bool $onTeam, array $allowed) {
    $label = Label::factory()->create();
    $arguments = $onTeam ? [Label::class, $label->team] : $label;

    foreach (TeamRole::cases() as $role) {
        $response = Gate::forUser(memberOf($label->team, $role))->inspect($ability, $arguments);

        expect($response->allowed())->toBe(in_array($role, $allowed, true), "{$role->value} → {$ability}");
    }
})->with([
    'view any' => ['viewAny', true, [TeamRole::Owner, TeamRole::Admin, TeamRole::Member]],
    'create' => ['create', true, [TeamRole::Owner, TeamRole::Admin]],
    'update' => ['update', false, [TeamRole::Owner, TeamRole::Admin]],
    'delete' => ['delete', false, [TeamRole::Owner, TeamRole::Admin]],
]);

test('outsiders are denied as not found', function (string $ability) {
    $response = Gate::forUser(User::factory()->create())->inspect($ability, Label::factory()->create());

    expect($response->status())->toBe(404);
})->with(['update', 'delete']);
