<?php

use App\Enums\TeamRole;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Str;

it('adds the user to the team with the invited role', function () {
    $token = Str::random(40);
    $invitation = TeamInvitation::factory()->withToken($token)->create(['email' => 'anna@example.com', 'role' => TeamRole::Admin]);
    $user = signIn(User::factory()->create(['email' => 'Anna@Example.com']));

    $this->postJson('/api/v1/invitations/accept', ['token' => $token])
        ->assertOk()
        ->assertJsonPath('data.id', $invitation->team_id)
        ->assertJsonPath('data.my_role', 'admin');

    expect($user->roleIn($invitation->team_id))->toBe(TeamRole::Admin);
    $this->assertModelMissing($invitation);
});

it('returns 403 when the invitation was sent to another email', function () {
    $token = Str::random(40);
    $invitation = TeamInvitation::factory()->withToken($token)->create(['email' => 'anna@example.com']);
    $user = signIn();

    $this->postJson('/api/v1/invitations/accept', ['token' => $token])
        ->assertForbidden()
        ->assertExactJson(['message' => 'This invitation was sent to another email address.']);

    expect($user->roleIn($invitation->team_id))->toBeNull();
    $this->assertModelExists($invitation);
});

it('returns 422 for an expired invitation', function () {
    $token = Str::random(40);
    TeamInvitation::factory()->withToken($token)->expired()->create(['email' => 'anna@example.com']);
    signIn(User::factory()->create(['email' => 'anna@example.com']));

    $this->postJson('/api/v1/invitations/accept', ['token' => $token])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token' => 'The invitation is invalid or has expired.']);
});

it('returns 422 for an unknown token', function () {
    signIn();

    $this->postJson('/api/v1/invitations/accept', ['token' => Str::random(40)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token' => 'The invitation is invalid or has expired.']);
});

it('keeps the current role when the user is already a member', function () {
    $token = Str::random(40);
    $invitation = TeamInvitation::factory()->withToken($token)->create(['role' => TeamRole::Member]);
    $user = User::factory()->create(['email' => $invitation->email]);
    $invitation->team->members()->attach($user, ['role' => TeamRole::Admin]);
    signIn($user);

    $this->postJson('/api/v1/invitations/accept', ['token' => $token])->assertOk();

    expect($user->roleIn($invitation->team_id))->toBe(TeamRole::Admin);
    $this->assertModelMissing($invitation);
});

it('returns 401 without a token', function () {
    $this->postJson('/api/v1/invitations/accept', ['token' => Str::random(40)])->assertUnauthorized();
});
