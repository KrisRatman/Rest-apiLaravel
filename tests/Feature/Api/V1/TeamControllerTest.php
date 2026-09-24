<?php

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;

describe('index', function () {
    it('lists only teams of the current user with the role in each', function () {
        $user = signIn();
        $owned = Team::factory()->for($user, 'owner')->create(['name' => 'Alpha']);
        $joined = Team::factory()->create(['name' => 'Beta']);
        $joined->members()->attach($user, ['role' => TeamRole::Member]);
        Team::factory()->create(['name' => 'Foreign']);

        $this->getJson('/api/v1/teams')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $owned->id)
            ->assertJsonPath('data.0.my_role', 'owner')
            ->assertJsonPath('data.1.id', $joined->id)
            ->assertJsonPath('data.1.my_role', 'member')
            ->assertJsonPath('data.1.members_count', 2)
            ->assertJsonPath('meta.total', 2);
    });

    it('returns 401 without a token', function () {
        $this->getJson('/api/v1/teams')->assertUnauthorized();
    });
});

describe('store', function () {
    it('creates a team owned by the current user', function () {
        $user = signIn();

        $response = $this->postJson('/api/v1/teams', ['name' => 'Mobile', 'description' => 'iOS & Android'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Mobile')
            ->assertJsonPath('data.owner_id', $user->id)
            ->assertJsonPath('data.my_role', 'owner')
            ->assertJsonPath('data.members_count', 1);

        $team = Team::findOrFail($response->json('data.id'));
        expect($user->roleIn($team))->toBe(TeamRole::Owner);
    });

    it('returns 422 without a name', function () {
        signIn();

        $this->postJson('/api/v1/teams', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name field is required.']);
    });
});

describe('show', function () {
    it('returns the team to a member', function () {
        $team = Team::factory()->create();
        signIn(memberOf($team));

        $this->getJson("/api/v1/teams/{$team->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $team->id)
            ->assertJsonPath('data.my_role', 'member');
    });

    it('returns 404 to a user outside the team', function () {
        $team = Team::factory()->create();
        signIn();

        $this->getJson("/api/v1/teams/{$team->id}")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);
    });

    it('returns 404 for a missing team without leaking the model name', function () {
        signIn();

        $this->getJson('/api/v1/teams/999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);
    });
});

describe('update', function () {
    it('lets an admin rename the team', function () {
        $team = Team::factory()->create();
        signIn(memberOf($team, TeamRole::Admin));

        $this->patchJson("/api/v1/teams/{$team->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');

        expect($team->fresh()->name)->toBe('Renamed');
    });

    it('returns 403 to a member', function () {
        $team = Team::factory()->create(['name' => 'Original']);
        signIn(memberOf($team));

        $this->patchJson("/api/v1/teams/{$team->id}", ['name' => 'Renamed'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'This action is unauthorized.']);

        expect($team->fresh()->name)->toBe('Original');
    });

    it('returns 404 before validating the payload of an outsider', function () {
        $team = Team::factory()->create();
        signIn();

        $this->patchJson("/api/v1/teams/{$team->id}", ['name' => ''])->assertNotFound();
    });
});

describe('destroy', function () {
    it('deletes the team with its projects and tasks', function () {
        $team = Team::factory()->create();
        $task = Task::factory()->for(Project::factory()->for($team))->create();
        signIn($team->owner);

        $this->deleteJson("/api/v1/teams/{$team->id}")->assertNoContent();

        $this->assertModelMissing($team);
        $this->assertModelMissing($task);
    });

    it('returns 403 to an admin', function () {
        $team = Team::factory()->create();
        signIn(memberOf($team, TeamRole::Admin));

        $this->deleteJson("/api/v1/teams/{$team->id}")->assertForbidden();

        $this->assertModelExists($team);
    });
});
