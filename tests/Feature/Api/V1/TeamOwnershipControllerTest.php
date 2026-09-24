<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

it('transfers ownership and demotes the previous owner to admin', function () {
    $team = Team::factory()->create();
    $previousOwner = signIn($team->owner);
    $newOwner = memberOf($team);

    $this->postJson("/api/v1/teams/{$team->id}/ownership", ['user_id' => $newOwner->id])
        ->assertOk()
        ->assertJsonPath('data.owner_id', $newOwner->id)
        ->assertJsonPath('data.my_role', 'admin');

    expect($team->fresh()->owner_id)->toBe($newOwner->id)
        ->and($newOwner->roleIn($team))->toBe(TeamRole::Owner)
        ->and($previousOwner->roleIn($team))->toBe(TeamRole::Admin);
});

it('returns 403 to an admin', function () {
    $team = Team::factory()->create();
    $admin = signIn(memberOf($team, TeamRole::Admin));

    $this->postJson("/api/v1/teams/{$team->id}/ownership", ['user_id' => $admin->id])
        ->assertForbidden();

    expect($team->fresh()->owner_id)->not->toBe($admin->id);
});

it('returns 422 when the new owner is not a member', function () {
    $team = Team::factory()->create();
    $outsider = User::factory()->create();
    signIn($team->owner);

    $this->postJson("/api/v1/teams/{$team->id}/ownership", ['user_id' => $outsider->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id' => 'The new owner must be a member of the team.']);
});

it('returns 422 when transferring to the current owner', function () {
    $team = Team::factory()->create();
    signIn($team->owner);

    $this->postJson("/api/v1/teams/{$team->id}/ownership", ['user_id' => $team->owner_id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id' => 'You already own this team.']);
});
