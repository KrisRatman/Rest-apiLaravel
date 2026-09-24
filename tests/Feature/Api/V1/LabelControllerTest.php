<?php

use App\Enums\TeamRole;
use App\Models\Label;
use App\Models\Task;
use App\Models\Team;

describe('index', function () {
    it('lists labels of the team sorted by name', function () {
        $team = Team::factory()->create();
        Label::factory()->for($team)->create(['name' => 'ux']);
        Label::factory()->for($team)->create(['name' => 'bug']);
        Label::factory()->create(['name' => 'foreign']);
        signIn(memberOf($team));

        $this->getJson("/api/v1/teams/{$team->id}/labels")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'bug')
            ->assertJsonPath('data.1.name', 'ux');
    });

    it('returns 404 to a user outside the team', function () {
        $team = Team::factory()->create();
        signIn();

        $this->getJson("/api/v1/teams/{$team->id}/labels")->assertNotFound();
    });
});

describe('store', function () {
    it('creates a label', function () {
        $team = Team::factory()->create();
        signIn(memberOf($team, TeamRole::Admin));

        $this->postJson("/api/v1/teams/{$team->id}/labels", ['name' => 'bug', 'color' => '#E5484D'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'bug')
            ->assertJsonPath('data.color', '#E5484D');

        expect($team->labels()->sole()->name)->toBe('bug');
    });

    it('returns 403 to a member', function () {
        $team = Team::factory()->create();
        signIn(memberOf($team));

        $this->postJson("/api/v1/teams/{$team->id}/labels", ['name' => 'bug', 'color' => '#E5484D'])
            ->assertForbidden();
    });

    it('returns 422 for a duplicate name within the team', function () {
        $team = Team::factory()->create();
        Label::factory()->for($team)->create(['name' => 'bug']);
        signIn($team->owner);

        $this->postJson("/api/v1/teams/{$team->id}/labels", ['name' => 'bug', 'color' => '#E5484D'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The name has already been taken.']);
    });

    it('allows the same name in another team', function () {
        Label::factory()->create(['name' => 'bug']);
        $team = Team::factory()->create();
        signIn($team->owner);

        $this->postJson("/api/v1/teams/{$team->id}/labels", ['name' => 'bug', 'color' => '#E5484D'])
            ->assertCreated();
    });

    it('returns 422 for a color that is not #RRGGBB', function (string $color) {
        $team = Team::factory()->create();
        signIn($team->owner);

        $this->postJson("/api/v1/teams/{$team->id}/labels", ['name' => 'bug', 'color' => $color])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('color');
    })->with([
        'named color' => 'red',
        'short hex' => '#F00',
        'without hash' => 'E5484D',
    ]);
});

describe('update', function () {
    it('renames the label', function () {
        $label = Label::factory()->create(['name' => 'bug']);
        signIn($label->team->owner);

        $this->patchJson("/api/v1/labels/{$label->id}", ['name' => 'defect'])
            ->assertOk()
            ->assertJsonPath('data.name', 'defect');
    });

    it('returns 404 to a user from another team', function () {
        $label = Label::factory()->create();
        signIn(Team::factory()->create()->owner);

        $this->patchJson("/api/v1/labels/{$label->id}", ['name' => 'defect'])->assertNotFound();
    });
});

describe('destroy', function () {
    it('deletes the label and detaches it from tasks', function () {
        $label = Label::factory()->create();
        $task = Task::factory()->create();
        $task->labels()->attach($label);
        signIn($label->team->owner);

        $this->deleteJson("/api/v1/labels/{$label->id}")->assertNoContent();

        $this->assertModelMissing($label);
        expect($task->labels()->count())->toBe(0);
    });

    it('returns 403 to a member', function () {
        $label = Label::factory()->create();
        signIn(memberOf($label->team));

        $this->deleteJson("/api/v1/labels/{$label->id}")->assertForbidden();

        $this->assertModelExists($label);
    });
});
