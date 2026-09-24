<?php

use App\Enums\ProjectStatus;
use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;

describe('index', function () {
    it('lists projects of the team with task counts', function () {
        $team = Team::factory()->create();
        $project = Project::factory()->for($team)->create(['name' => 'Alpha']);
        Task::factory()->for($project)->create();
        Task::factory()->for($project)->done()->create();
        Project::factory()->for($team)->create(['name' => 'Beta']);
        Project::factory()->create(['name' => 'Foreign']);
        signIn(memberOf($team));

        $this->getJson("/api/v1/teams/{$team->id}/projects")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $project->id)
            ->assertJsonPath('data.0.tasks_count', 2)
            ->assertJsonPath('data.0.open_tasks_count', 1);
    });

    it('filters projects by status', function () {
        $team = Team::factory()->create();
        Project::factory()->for($team)->create();
        $archived = Project::factory()->for($team)->archived()->create();
        signIn(memberOf($team));

        $this->getJson("/api/v1/teams/{$team->id}/projects?status=archived")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $archived->id);
    });

    it('returns 422 for an unknown status filter', function () {
        $team = Team::factory()->create();
        signIn(memberOf($team));

        $this->getJson("/api/v1/teams/{$team->id}/projects?status=deleted")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'The selected status is invalid.']);
    });

    it('returns 404 to a user outside the team', function () {
        $team = Team::factory()->create();
        signIn();

        $this->getJson("/api/v1/teams/{$team->id}/projects")->assertNotFound();
    });
});

describe('store', function () {
    it('creates a project for an admin', function () {
        $team = Team::factory()->create();
        $admin = signIn(memberOf($team, TeamRole::Admin));

        $response = $this->postJson("/api/v1/teams/{$team->id}/projects", ['name' => 'Release 2.0'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Release 2.0')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.team_id', $team->id)
            ->assertJsonPath('data.created_by', $admin->id)
            ->assertJsonPath('data.tasks_count', 0);

        $this->assertModelExists(Project::find($response->json('data.id')));
    });

    it('returns 403 to a member', function () {
        $team = Team::factory()->create();
        signIn(memberOf($team));

        $this->postJson("/api/v1/teams/{$team->id}/projects", ['name' => 'Release 2.0'])
            ->assertForbidden();

        expect($team->projects()->count())->toBe(0);
    });

    it('returns 422 without a name', function () {
        $team = Team::factory()->create();
        signIn($team->owner);

        $this->postJson("/api/v1/teams/{$team->id}/projects", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name field is required.']);
    });

    it('ignores a team_id passed in the payload', function () {
        $team = Team::factory()->create();
        $foreignTeam = Team::factory()->create();
        signIn($team->owner);

        $this->postJson("/api/v1/teams/{$team->id}/projects", ['name' => 'Sneaky', 'team_id' => $foreignTeam->id])
            ->assertCreated()
            ->assertJsonPath('data.team_id', $team->id);

        expect($foreignTeam->projects()->count())->toBe(0);
    });
});

describe('show', function () {
    it('returns the project to a member', function () {
        $project = Project::factory()->create();
        signIn(memberOf($project->team));

        $this->getJson("/api/v1/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $project->id);
    });

    it('returns 404 to a user from another team', function () {
        $project = Project::factory()->create();
        signIn(memberOf(Team::factory()->create()));

        $this->getJson("/api/v1/projects/{$project->id}")->assertNotFound();
    });
});

describe('update', function () {
    it('archives the project', function () {
        $project = Project::factory()->create();
        signIn($project->team->owner);

        $this->patchJson("/api/v1/projects/{$project->id}", ['status' => 'archived'])
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        expect($project->fresh()->status)->toBe(ProjectStatus::Archived);
    });

    it('returns 403 to a member', function () {
        $project = Project::factory()->create(['name' => 'Original']);
        signIn(memberOf($project->team));

        $this->patchJson("/api/v1/projects/{$project->id}", ['name' => 'Renamed'])->assertForbidden();

        expect($project->fresh()->name)->toBe('Original');
    });

    it('returns 422 for an unknown status', function () {
        $project = Project::factory()->create();
        signIn($project->team->owner);

        $this->patchJson("/api/v1/projects/{$project->id}", ['status' => 'deleted'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'The selected status is invalid.']);
    });
});

describe('destroy', function () {
    it('deletes the project with its tasks', function () {
        $task = Task::factory()->create();
        $project = $task->project;
        signIn(memberOf($project->team, TeamRole::Admin));

        $this->deleteJson("/api/v1/projects/{$project->id}")->assertNoContent();

        $this->assertModelMissing($project);
        $this->assertModelMissing($task);
    });

    it('returns 403 to a member', function () {
        $project = Project::factory()->create();
        signIn(memberOf($project->team));

        $this->deleteJson("/api/v1/projects/{$project->id}")->assertForbidden();

        $this->assertModelExists($project);
    });
});
