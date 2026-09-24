<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('reads the role from the cache after the first lookup', function () {
    $team = Team::factory()->create();
    $member = memberOf($team);
    $member->roleIn($team);

    DB::enableQueryLog();
    $role = $member->roleIn($team);

    expect($role)->toBe(TeamRole::Member)
        ->and(DB::getQueryLog())->toBeEmpty();
});

it('caches the absence of membership too', function () {
    $team = Team::factory()->create();
    $outsider = User::factory()->create();
    $outsider->roleIn($team);

    DB::enableQueryLog();

    expect($outsider->roleIn($team))->toBeNull()
        ->and(DB::getQueryLog())->toBeEmpty();
});

it('forgets the cached role when membership changes', function (Closure $change, ?TeamRole $expected) {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member]);
    expect($user->roleIn($team))->toBe(TeamRole::Member);

    $change($team, $user);

    expect($user->roleIn($team))->toBe($expected);
})->with([
    'role changed' => [fn (Team $team, User $user) => $team->members()->updateExistingPivot($user->id, ['role' => TeamRole::Admin]), TeamRole::Admin],
    'member removed' => [fn (Team $team, User $user) => $team->members()->detach($user->id), null],
]);

it('forgets the cached absence when the user joins', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    expect($user->roleIn($team))->toBeNull();

    $team->members()->attach($user, ['role' => TeamRole::Member]);

    expect($user->roleIn($team))->toBe(TeamRole::Member);
});
