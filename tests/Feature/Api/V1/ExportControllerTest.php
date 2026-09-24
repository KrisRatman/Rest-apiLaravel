<?php

use App\Enums\ExportStatus;
use App\Enums\TeamRole;
use App\Jobs\ExportProjectTasks;
use App\Models\Export;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

describe('store', function () {
    it('creates a pending export and queues the job with the filters', function () {
        Queue::fake();
        $project = Project::factory()->create();
        $member = signIn(memberOf($project->team));

        $response = $this->postJson("/api/v1/projects/{$project->id}/exports", [
            'filter' => ['status' => 'todo,in_progress'],
            'sort' => '-priority',
        ])->assertAccepted()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.download_url', null)
            ->assertJsonPath('data.filters.filter.status', 'todo,in_progress');

        $export = Export::findOrFail($response->json('data.id'));
        expect($export->user_id)->toBe($member->id)
            ->and($export->filters)->toEqual(['filter' => ['status' => 'todo,in_progress'], 'sort' => '-priority']);
        Queue::assertPushed(ExportProjectTasks::class, fn (ExportProjectTasks $job) => $job->export->is($export));
    });

    it('returns 400 for an unknown filter without queueing', function () {
        Queue::fake();
        $project = Project::factory()->create();
        signIn(memberOf($project->team));

        $this->postJson("/api/v1/projects/{$project->id}/exports", ['filter' => ['colour' => 'red']])
            ->assertBadRequest();

        expect(Export::count())->toBe(0);
        Queue::assertNothingPushed();
    });

    it('returns 422 for an invalid due date', function () {
        Queue::fake();
        $project = Project::factory()->create();
        signIn(memberOf($project->team));

        $this->postJson("/api/v1/projects/{$project->id}/exports", ['filter' => ['due_from' => 'tomorrow']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter.due_from');
    });

    it('returns 404 to a user from another team', function () {
        Queue::fake();
        $project = Project::factory()->create();
        signIn();

        $this->postJson("/api/v1/projects/{$project->id}/exports")->assertNotFound();

        Queue::assertNothingPushed();
    });
});

describe('index', function () {
    it('lists only exports of the current user', function () {
        $project = Project::factory()->create();
        $member = signIn(memberOf($project->team));
        $mine = Export::factory()->for($project)->for($member)->create();
        Export::factory()->for($project)->create();

        $this->getJson('/api/v1/me/exports')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
    });
});

describe('show', function () {
    it('returns a completed export with the download url', function () {
        $export = Export::factory()->completed()->create();
        signIn($export->user);

        $this->getJson("/api/v1/exports/{$export->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.download_url', route('v1.exports.download', $export));
    });

    it('returns 404 to a teammate who is not the author', function () {
        $export = Export::factory()->create();
        signIn(memberOf($export->project->team, TeamRole::Admin));

        $this->getJson("/api/v1/exports/{$export->id}")->assertNotFound();
    });

    it('returns 404 to the author after leaving the team', function () {
        $export = Export::factory()->create();
        $author = memberOf($export->project->team);
        $export->user()->associate($author)->save();
        $export->project->team->members()->detach($author);
        signIn($author);

        $this->getJson("/api/v1/exports/{$export->id}")->assertNotFound();
    });
});

describe('download', function () {
    it('streams the csv file', function () {
        Storage::fake('local');
        Storage::disk('local')->put('exports/1.csv', "ID,Title\n1,Task\n");
        $export = Export::factory()->completed('exports/1.csv')->create();
        signIn($export->user);

        $response = $this->get("/api/v1/exports/{$export->id}/download")
            ->assertOk()
            ->assertDownload($export->fileName())
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        expect($response->streamedContent())->toBe("ID,Title\n1,Task\n");
    });

    it('returns 409 while the export is being prepared', function () {
        $export = Export::factory()->create(['status' => ExportStatus::Processing]);
        signIn($export->user);

        $this->getJson("/api/v1/exports/{$export->id}/download")
            ->assertConflict()
            ->assertExactJson(['message' => 'The export is not ready yet.']);
    });

    it('returns 404 to another user', function () {
        $export = Export::factory()->completed()->create();
        signIn(Team::factory()->create()->owner);

        $this->getJson("/api/v1/exports/{$export->id}/download")->assertNotFound();
    });
});
