<?php

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;

describe('index', function () {
    it('lists members with their roles', function () {
        $team = Team::factory()->create();
        $member = memberOf($team);
        signIn($member);

        $this->getJson("/api/v1/teams/{$team->id}/members")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $team->owner_id)
            ->assertJsonPath('data.0.role', 'owner')
            ->assertJsonPath('data.1.id', $member->id)
            ->assertJsonPath('data.1.role', 'member');
    });

    it('returns 404 to a user outside the team', function () {
        $team = Team::factory()->create();
        signIn();

        $this->getJson("/api/v1/teams/{$team->id}/members")->assertNotFound();
    });
});

describe('store', function () {
    it('adds a registered user by email', function () {
        $team = Team::factory()->create();
        $newcomer = User::factory()->create(['email' => 'ivan@example.com']);
        signIn(memberOf($team, TeamRole::Admin));

        $this->postJson("/api/v1/teams/{$team->id}/members", ['email' => 'ivan@example.com', 'role' => 'member'])
            ->assertCreated()
            ->assertJsonPath('data.id', $newcomer->id)
            ->assertJsonPath('data.role', 'member');

        expect($newcomer->roleIn($team))->toBe(TeamRole::Member);
    });

    it('returns 403 to a member', function () {
        $team = Team::factory()->create();
        $newcomer = User::factory()->create();
        signIn(memberOf($team));

        $this->postJson("/api/v1/teams/{$team->id}/members", ['email' => $newcomer->email, 'role' => 'member'])
            ->assertForbidden();

        expect($newcomer->roleIn($team))->toBeNull();
    });

    it('returns 422 for an unknown email', function () {
        $team = Team::factory()->create();
        signIn($team->owner);

        $this->postJson("/api/v1/teams/{$team->id}/members", ['email' => 'nobody@example.com', 'role' => 'member'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'No registered user has this email.']);
    });

    it('returns 422 when the user is already a member', function () {
        $team = Team::factory()->create();
        $member = memberOf($team);
        signIn($team->owner);

        $this->postJson("/api/v1/teams/{$team->id}/members", ['email' => $member->email, 'role' => 'admin'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'This user is already a member of the team.']);

        expect($member->roleIn($team))->toBe(TeamRole::Member);
    });

    it('returns 422 when trying to add a second owner', function () {
        $team = Team::factory()->create();
        $newcomer = User::factory()->create();
        signIn($team->owner);

        $this->postJson("/api/v1/teams/{$team->id}/members", ['email' => $newcomer->email, 'role' => 'owner'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role' => 'The selected role is invalid.']);
    });
});

describe('update', function () {
    it('changes the role of a member', function () {
        $team = Team::factory()->create();
        $member = memberOf($team);
        signIn($team->owner);

        $this->patchJson("/api/v1/teams/{$team->id}/members/{$member->id}", ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');

        expect($member->roleIn($team))->toBe(TeamRole::Admin);
    });

    it('returns 422 when changing the owner role', function () {
        $team = Team::factory()->create();
        signIn(memberOf($team, TeamRole::Admin));

        $this->patchJson("/api/v1/teams/{$team->id}/members/{$team->owner_id}", ['role' => 'member'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role' => 'The owner role can only be changed by transferring ownership.']);

        expect($team->owner->roleIn($team))->toBe(TeamRole::Owner);
    });

    it('returns 404 for a user who is not in the team', function () {
        $team = Team::factory()->create();
        $outsider = User::factory()->create();
        signIn($team->owner);

        $this->patchJson("/api/v1/teams/{$team->id}/members/{$outsider->id}", ['role' => 'admin'])
            ->assertNotFound();

        expect($outsider->roleIn($team))->toBeNull();
    });
});

describe('destroy', function () {
    it('removes a member and unassigns their tasks in this team only', function () {
        $team = Team::factory()->create();
        $member = memberOf($team);
        $teamTask = Task::factory()->for(Project::factory()->for($team))->create(['assignee_id' => $member->id]);
        $otherTeam = Team::factory()->create();
        $otherTeam->members()->attach($member, ['role' => TeamRole::Member]);
        $otherTask = Task::factory()->for(Project::factory()->for($otherTeam))->create(['assignee_id' => $member->id]);
        signIn(memberOf($team, TeamRole::Admin));

        $this->deleteJson("/api/v1/teams/{$team->id}/members/{$member->id}")->assertNoContent();

        expect($member->roleIn($team))->toBeNull()
            ->and($teamTask->fresh()->assignee_id)->toBeNull()
            ->and($otherTask->fresh()->assignee_id)->toBe($member->id);
    });

    it('lets a member leave the team', function () {
        $team = Team::factory()->create();
        $member = signIn(memberOf($team));

        $this->deleteJson("/api/v1/teams/{$team->id}/members/{$member->id}")->assertNoContent();

        expect($member->roleIn($team))->toBeNull();
    });

    it('returns 403 when a member removes someone else', function () {
        $team = Team::factory()->create();
        $other = memberOf($team);
        signIn(memberOf($team));

        $this->deleteJson("/api/v1/teams/{$team->id}/members/{$other->id}")->assertForbidden();

        expect($other->roleIn($team))->toBe(TeamRole::Member);
    });

    it('returns 422 when the owner tries to leave', function () {
        $team = Team::factory()->create();
        signIn($team->owner);

        $this->deleteJson("/api/v1/teams/{$team->id}/members/{$team->owner_id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user' => 'The team owner cannot leave the team. Transfer ownership first.']);
    });
});
