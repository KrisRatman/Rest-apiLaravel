<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Models\Label;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;

describe('index', function () {
    it('lists tasks of the project newest first', function () {
        $project = Project::factory()->create();
        $older = Task::factory()->for($project)->create(['created_at' => now()->subDay()]);
        $newer = Task::factory()->for($project)->create();
        Task::factory()->create();
        signIn(memberOf($project->team));

        $this->getJson("/api/v1/projects/{$project->id}/tasks")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('meta.total', 2);
    });

    it('filters by several statuses', function () {
        $project = Project::factory()->create();
        $todo = Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);
        $review = Task::factory()->for($project)->create(['status' => TaskStatus::Review]);
        Task::factory()->for($project)->done()->create();
        signIn(memberOf($project->team));

        $ids = $this->getJson("/api/v1/projects/{$project->id}/tasks?filter[status]=todo,review")
            ->assertOk()
            ->json('data.*.id');

        expect($ids)->toEqualCanonicalizing([$todo->id, $review->id]);
    });

    it('filters overdue tasks excluding done ones', function () {
        $project = Project::factory()->create();
        $overdue = Task::factory()->for($project)->overdue()->create();
        Task::factory()->for($project)->overdue()->done()->create();
        Task::factory()->for($project)->create(['due_date' => today()->addDay()]);
        signIn(memberOf($project->team));

        $this->getJson("/api/v1/projects/{$project->id}/tasks?filter[overdue]=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $overdue->id)
            ->assertJsonPath('data.0.is_overdue', true);
    });

    it('filters by label, assignee, due date range and search', function (string $query, string $expected) {
        $project = Project::factory()->create();
        $assignee = memberOf($project->team);
        $label = Label::factory()->for($project->team)->create();
        $plain = ['description' => null, 'due_date' => null];
        $tasks = [
            'labeled' => Task::factory()->for($project)->create(['title' => 'Plain one', ...$plain]),
            'assigned' => Task::factory()->for($project)->create(['title' => 'Plain two', 'assignee_id' => $assignee->id, ...$plain]),
            'due' => Task::factory()->for($project)->create(['title' => 'Plain three', ...$plain, 'due_date' => '2026-10-10']),
            'searched' => Task::factory()->for($project)->create(['title' => 'Checkout screen', ...$plain]),
        ];
        $tasks['labeled']->labels()->attach($label);
        signIn($assignee);

        $query = strtr($query, [':label' => $label->id, ':assignee' => $assignee->id]);

        $this->getJson("/api/v1/projects/{$project->id}/tasks?{$query}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $tasks[$expected]->id);
    })->with([
        'label' => ['filter[label]=:label', 'labeled'],
        'assignee' => ['filter[assignee_id]=:assignee', 'assigned'],
        'due date range' => ['filter[due_from]=2026-10-01&filter[due_to]=2026-10-31', 'due'],
        'search' => ['filter[search]=checkout', 'searched'],
    ]);

    it('sorts by priority weight rather than alphabetically', function () {
        $project = Project::factory()->create();
        $low = Task::factory()->for($project)->priority(TaskPriority::Low)->create();
        $urgent = Task::factory()->for($project)->priority(TaskPriority::Urgent)->create();
        $medium = Task::factory()->for($project)->priority(TaskPriority::Medium)->create();
        $high = Task::factory()->for($project)->priority(TaskPriority::High)->create();
        signIn(memberOf($project->team));

        $this->getJson("/api/v1/projects/{$project->id}/tasks?sort=-priority")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$urgent->id, $high->id, $medium->id, $low->id]);
    });

    it('paginates with per_page and keeps the query string in links', function () {
        $project = Project::factory()->create();
        Task::factory()->for($project)->count(3)->create();
        signIn(memberOf($project->team));

        $response = $this->getJson("/api/v1/projects/{$project->id}/tasks?per_page=2&sort=title")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 2);

        expect($response->json('links.next'))->toContain('sort=title');
    });

    it('returns 400 for an unknown filter', function () {
        $project = Project::factory()->create();
        signIn(memberOf($project->team));

        $this->getJson("/api/v1/projects/{$project->id}/tasks?filter[colour]=red")
            ->assertBadRequest()
            ->assertJsonStructure(['message']);
    });

    it('returns 400 for a sort field that is not allowed', function () {
        $project = Project::factory()->create();
        signIn(memberOf($project->team));

        $this->getJson("/api/v1/projects/{$project->id}/tasks?sort=description")
            ->assertBadRequest();
    });

    it('returns 422 for per_page over the limit', function () {
        $project = Project::factory()->create();
        signIn(memberOf($project->team));

        $this->getJson("/api/v1/projects/{$project->id}/tasks?per_page=500")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page' => 'The per page field must be between 1 and 100.']);
    });

    it('returns 404 to a user from another team', function () {
        $project = Project::factory()->create();
        signIn();

        $this->getJson("/api/v1/projects/{$project->id}/tasks?per_page=500")->assertNotFound();
    });
});

describe('store', function () {
    it('creates a task with an assignee and labels', function () {
        $project = Project::factory()->create();
        $creator = signIn(memberOf($project->team));
        $assignee = memberOf($project->team);
        $labels = Label::factory()->for($project->team)->count(2)->create();

        $response = $this->postJson("/api/v1/projects/{$project->id}/tasks", [
            'title' => 'Build the cart screen',
            'priority' => 'high',
            'assignee_id' => $assignee->id,
            'due_date' => '2026-10-15',
            'label_ids' => $labels->pluck('id')->all(),
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Build the cart screen')
            ->assertJsonPath('data.status', 'todo')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.due_date', '2026-10-15')
            ->assertJsonPath('data.assignee.id', $assignee->id)
            ->assertJsonPath('data.creator_id', $creator->id)
            ->assertJsonCount(2, 'data.labels');

        $task = Task::findOrFail($response->json('data.id'));
        expect($task->project_id)->toBe($project->id)
            ->and($task->labels()->pluck('labels.id')->all())->toEqualCanonicalizing($labels->pluck('id')->all());
    });

    it('returns 422 when the assignee is not a team member', function () {
        $project = Project::factory()->create();
        $outsider = User::factory()->create();
        signIn($project->team->owner);

        $this->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Task', 'assignee_id' => $outsider->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assignee_id' => 'The assignee must be a member of the team.']);
    });

    it('returns 422 when a label belongs to another team', function () {
        $project = Project::factory()->create();
        $foreignLabel = Label::factory()->create();
        signIn($project->team->owner);

        $this->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Task', 'label_ids' => [$foreignLabel->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['label_ids.0' => 'The label must belong to the team.']);
    });

    it('returns 422 for an empty payload', function () {
        $project = Project::factory()->create();
        signIn($project->team->owner);

        $this->postJson("/api/v1/projects/{$project->id}/tasks", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title' => 'The title field is required.']);
    });

    it('returns 422 for a status outside the enum', function () {
        $project = Project::factory()->create();
        signIn($project->team->owner);

        $this->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Task', 'status' => 'blocked'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'The selected status is invalid.']);
    });

    it('returns 403 for an archived project', function () {
        $project = Project::factory()->archived()->create();
        signIn($project->team->owner);

        $this->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Task'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Tasks cannot be added to an archived project.']);

        expect($project->tasks()->count())->toBe(0);
    });

    it('returns 404 to a user from another team', function () {
        $project = Project::factory()->create();
        signIn();

        $this->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Task'])->assertNotFound();
    });
});

describe('show', function () {
    it('returns the task with its labels and comment count', function () {
        $task = Task::factory()->create();
        $task->labels()->attach(Label::factory()->for($task->project->team)->create());
        signIn(memberOf($task->project->team));

        $this->getJson("/api/v1/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonCount(1, 'data.labels')
            ->assertJsonPath('data.comments_count', 0);
    });

    it('returns 404 to a user from another team', function () {
        $task = Task::factory()->create();
        signIn(memberOf(Team::factory()->create()));

        $this->getJson("/api/v1/tasks/{$task->id}")->assertNotFound();
    });
});

describe('update', function () {
    it('sets completed_at when the task is done and clears it when reopened', function () {
        Carbon::setTestNow('2026-09-24 12:00:00');
        $task = Task::factory()->create();
        signIn(memberOf($task->project->team));

        $this->patchJson("/api/v1/tasks/{$task->id}", ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.completed_at', '2026-09-24T12:00:00.000000Z');

        $this->patchJson("/api/v1/tasks/{$task->id}", ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.completed_at', null);

        expect($task->fresh()->completed_at)->toBeNull();
    });

    it('changes only the passed fields', function () {
        $task = Task::factory()->create(['title' => 'Original', 'priority' => TaskPriority::Low]);
        signIn(memberOf($task->project->team));

        $this->patchJson("/api/v1/tasks/{$task->id}", ['priority' => 'urgent'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Original')
            ->assertJsonPath('data.priority', 'urgent');
    });

    it('replaces labels and keeps them when label_ids is absent', function () {
        $task = Task::factory()->create();
        [$old, $new] = Label::factory()->for($task->project->team)->count(2)->create();
        $task->labels()->attach($old);
        signIn(memberOf($task->project->team));

        $this->patchJson("/api/v1/tasks/{$task->id}", ['title' => 'Renamed'])->assertOk();
        expect($task->labels()->pluck('labels.id')->all())->toBe([$old->id]);

        $this->patchJson("/api/v1/tasks/{$task->id}", ['label_ids' => [$new->id]])->assertOk();
        expect($task->labels()->pluck('labels.id')->all())->toBe([$new->id]);
    });

    it('unassigns the task with a null assignee', function () {
        $task = Task::factory()->create();
        $task->update(['assignee_id' => memberOf($task->project->team)->id]);
        signIn(memberOf($task->project->team));

        $this->patchJson("/api/v1/tasks/{$task->id}", ['assignee_id' => null])
            ->assertOk()
            ->assertJsonPath('data.assignee', null);
    });

    it('returns 422 for an empty title', function () {
        $task = Task::factory()->create(['title' => 'Original']);
        signIn(memberOf($task->project->team));

        $this->patchJson("/api/v1/tasks/{$task->id}", ['title' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title' => 'The title field is required.']);
    });

    it('does not let the payload move the task to another project', function () {
        $task = Task::factory()->create();
        $foreignProject = Project::factory()->create();
        signIn(memberOf($task->project->team));

        $this->patchJson("/api/v1/tasks/{$task->id}", ['project_id' => $foreignProject->id, 'creator_id' => 1])
            ->assertOk();

        expect($task->fresh()->project_id)->toBe($task->project_id);
    });
});

describe('destroy', function () {
    it('lets the creator delete their task', function () {
        $project = Project::factory()->create();
        $member = memberOf($project->team);
        $task = Task::factory()->for($project)->create(['creator_id' => $member->id]);
        signIn($member);

        $this->deleteJson("/api/v1/tasks/{$task->id}")->assertNoContent();

        $this->assertModelMissing($task);
    });

    it('returns 403 when a member deletes a task created by someone else', function () {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create(['creator_id' => $project->team->owner_id]);
        signIn(memberOf($project->team));

        $this->deleteJson("/api/v1/tasks/{$task->id}")->assertForbidden();

        $this->assertModelExists($task);
    });

    it('lets an admin delete any task', function () {
        $task = Task::factory()->create();
        signIn(memberOf($task->project->team, TeamRole::Admin));

        $this->deleteJson("/api/v1/tasks/{$task->id}")->assertNoContent();

        $this->assertModelMissing($task);
    });
});
