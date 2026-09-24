<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Models\Label;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Testing\TestResponse;

/**
 * Проходит все страницы курсорного списка по ссылке links.next и собирает ID задач.
 *
 * @return list<int>
 */
function collectTaskIdsAcrossPages(TestResponse $firstPage): array
{
    $ids = $firstPage->json('data.*.id');
    $next = $firstPage->json('links.next');

    while ($next !== null) {
        $page = test()->getJson($next)->assertOk();
        $ids = [...$ids, ...$page->json('data.*.id')];
        $next = $page->json('links.next');
    }

    return $ids;
}

describe('index', function () {
    it('returns tasks in the v2 format with a cursor instead of page numbers', function () {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->priority(TaskPriority::High)->create([
            'status' => TaskStatus::InProgress,
            'creator_id' => $project->team->owner_id,
        ]);
        signIn(memberOf($project->team));

        $this->getJson("/api/v2/projects/{$project->id}/tasks")
            ->assertOk()
            ->assertJsonPath('data.0.id', $task->id)
            ->assertJsonPath('data.0.status', ['value' => 'in_progress', 'label' => 'In progress'])
            ->assertJsonPath('data.0.priority', ['value' => 'high', 'label' => 'High'])
            ->assertJsonPath('data.0.creator.id', $project->team->owner_id)
            ->assertJsonMissingPath('data.0.creator_id')
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.next_cursor', null)
            ->assertJsonMissingPath('meta.total')
            ->assertJsonMissingPath('meta.current_page');
    });

    it('walks all pages by priority without gaps or duplicates', function () {
        $project = Project::factory()->create();
        $tasks = collect([TaskPriority::Low, TaskPriority::Urgent, TaskPriority::Medium, TaskPriority::Urgent, TaskPriority::High])
            ->map(fn (TaskPriority $priority) => Task::factory()->for($project)->priority($priority)->create());
        signIn(memberOf($project->team));

        $firstPage = $this->getJson("/api/v2/projects/{$project->id}/tasks?sort=-priority&per_page=2")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $expected = $tasks
            ->sortBy([fn (Task $a, Task $b) => $b->priority->weight() <=> $a->priority->weight(), ['id', 'desc']])
            ->pluck('id')
            ->all();

        expect(collectTaskIdsAcrossPages($firstPage))->toBe($expected);
    });

    it('walks all pages by due date and puts tasks without a due date last', function () {
        $project = Project::factory()->create();
        $withoutDate = Task::factory()->for($project)->count(2)->create(['due_date' => null]);
        $later = Task::factory()->for($project)->create(['due_date' => '2026-10-20']);
        $sooner = Task::factory()->for($project)->create(['due_date' => '2026-10-01']);
        signIn(memberOf($project->team));

        $firstPage = $this->getJson("/api/v2/projects/{$project->id}/tasks?sort=due_date&per_page=1");

        expect(collectTaskIdsAcrossPages($firstPage))
            ->toBe([$sooner->id, $later->id, $withoutDate[1]->id, $withoutDate[0]->id]);
    });

    it('goes back to the previous page with prev_cursor', function () {
        $project = Project::factory()->create();
        Task::factory()->for($project)->count(3)->create();
        signIn(memberOf($project->team));

        $firstPage = $this->getJson("/api/v2/projects/{$project->id}/tasks?per_page=2")->assertOk();
        $secondPage = $this->getJson($firstPage->json('links.next'))->assertOk()->assertJsonCount(1, 'data');

        $this->getJson($secondPage->json('links.prev'))
            ->assertOk()
            ->assertJsonPath('data.*.id', $firstPage->json('data.*.id'));
    });

    it('keeps filters in the next page link', function () {
        $project = Project::factory()->create();
        Task::factory()->for($project)->count(3)->create(['status' => TaskStatus::Todo]);
        Task::factory()->for($project)->done()->create();
        signIn(memberOf($project->team));

        $firstPage = $this->getJson("/api/v2/projects/{$project->id}/tasks?filter[status]=todo&per_page=2")->assertOk();

        expect($firstPage->json('links.next'))->toContain('filter%5Bstatus%5D=todo')
            ->and(collectTaskIdsAcrossPages($firstPage))->toHaveCount(3);
    });

    it('returns 422 when the cursor was issued for another sort', function () {
        $project = Project::factory()->create();
        Task::factory()->for($project)->count(3)->create();
        signIn(memberOf($project->team));

        $cursor = $this->getJson("/api/v2/projects/{$project->id}/tasks?sort=due_date&per_page=1")
            ->json('meta.next_cursor');

        $this->getJson("/api/v2/projects/{$project->id}/tasks?sort=priority&cursor={$cursor}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cursor' => 'The cursor does not match the requested sort. Request the first page again.']);
    });

    it('returns 400 for a sort field that is not allowed', function () {
        $project = Project::factory()->create();
        signIn(memberOf($project->team));

        $this->getJson("/api/v2/projects/{$project->id}/tasks?sort=description")->assertBadRequest();
    });

    it('returns 404 to a user from another team', function () {
        $project = Project::factory()->create();
        signIn();

        $this->getJson("/api/v2/projects/{$project->id}/tasks")->assertNotFound();
    });

    it('returns 401 without a token', function () {
        $project = Project::factory()->create();

        $this->getJson("/api/v2/projects/{$project->id}/tasks")->assertUnauthorized();
    });
});

describe('store', function () {
    it('creates a task and returns it in the v2 format', function () {
        $project = Project::factory()->create();
        $creator = signIn(memberOf($project->team));
        $label = Label::factory()->for($project->team)->create();

        $response = $this->postJson("/api/v2/projects/{$project->id}/tasks", [
            'title' => 'Build the cart screen',
            'priority' => 'urgent',
            'label_ids' => [$label->id],
        ])->assertCreated()
            ->assertJsonPath('data.status', ['value' => 'todo', 'label' => 'To do'])
            ->assertJsonPath('data.priority', ['value' => 'urgent', 'label' => 'Urgent'])
            ->assertJsonPath('data.creator.id', $creator->id)
            ->assertJsonPath('data.labels.0.id', $label->id);

        $this->assertModelExists(Task::findOrFail($response->json('data.id')));
    });

    it('returns 422 for a status outside the enum', function () {
        $project = Project::factory()->create();
        signIn($project->team->owner);

        $this->postJson("/api/v2/projects/{$project->id}/tasks", ['title' => 'Task', 'status' => 'blocked'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'The selected status is invalid.']);
    });
});

describe('show', function () {
    it('returns the task with its creator and comment count', function () {
        $task = Task::factory()->create();
        signIn(memberOf($task->project->team));

        $this->getJson("/api/v2/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.creator.id', $task->creator_id)
            ->assertJsonPath('data.comments_count', 0);
    });

    it('returns 404 to a user from another team', function () {
        $task = Task::factory()->create();
        signIn(memberOf(Team::factory()->create()));

        $this->getJson("/api/v2/tasks/{$task->id}")->assertNotFound();
    });
});

describe('update', function () {
    it('changes the status and returns it as an object', function () {
        $task = Task::factory()->create();
        signIn(memberOf($task->project->team));

        $this->patchJson("/api/v2/tasks/{$task->id}", ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('data.status', ['value' => 'done', 'label' => 'Done'])
            ->assertJsonPath('data.is_overdue', false);

        expect($task->fresh()->completed_at)->not->toBeNull();
    });
});

describe('destroy', function () {
    it('lets an admin delete any task', function () {
        $task = Task::factory()->create();
        signIn(memberOf($task->project->team, TeamRole::Admin));

        $this->deleteJson("/api/v2/tasks/{$task->id}")->assertNoContent();

        $this->assertModelMissing($task);
    });

    it('returns 403 when a member deletes a task created by someone else', function () {
        $project = Project::factory()->create();
        $task = Task::factory()->for($project)->create(['creator_id' => $project->team->owner_id]);
        signIn(memberOf($project->team));

        $this->deleteJson("/api/v2/tasks/{$task->id}")->assertForbidden();

        $this->assertModelExists($task);
    });
});
