<?php

use App\Enums\TeamRole;
use App\Models\Comment;
use App\Models\Task;
use App\Models\Team;

describe('index', function () {
    it('lists comments of the task oldest first', function () {
        $task = Task::factory()->create();
        $first = Comment::factory()->for($task)->create(['created_at' => now()->subHour()]);
        $second = Comment::factory()->for($task)->create();
        Comment::factory()->create();
        signIn(memberOf($task->project->team));

        $this->getJson("/api/v1/tasks/{$task->id}/comments")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$first->id, $second->id])
            ->assertJsonPath('data.0.author.id', $first->user_id);
    });

    it('returns 404 to a user from another team', function () {
        $task = Task::factory()->create();
        signIn();

        $this->getJson("/api/v1/tasks/{$task->id}/comments")->assertNotFound();
    });
});

describe('store', function () {
    it('adds a comment by the current user', function () {
        $task = Task::factory()->create();
        $member = signIn(memberOf($task->project->team));

        $this->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Looks good'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Looks good')
            ->assertJsonPath('data.author.id', $member->id);

        expect($task->comments()->sole()->user_id)->toBe($member->id);
    });

    it('returns 422 for an empty body', function () {
        $task = Task::factory()->create();
        signIn(memberOf($task->project->team));

        $this->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body' => 'The body field is required.']);
    });

    it('returns 404 to a user from another team', function () {
        $task = Task::factory()->create();
        signIn(memberOf(Team::factory()->create()));

        $this->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Hi'])->assertNotFound();

        expect($task->comments()->count())->toBe(0);
    });
});

describe('update', function () {
    it('lets the author edit the comment', function () {
        $task = Task::factory()->create();
        $author = memberOf($task->project->team);
        $comment = Comment::factory()->for($task)->for($author, 'author')->create();
        signIn($author);

        $this->patchJson("/api/v1/comments/{$comment->id}", ['body' => 'Edited'])
            ->assertOk()
            ->assertJsonPath('data.body', 'Edited');
    });

    it('returns 403 even to the owner when editing someone else\'s comment', function () {
        $task = Task::factory()->create();
        $comment = Comment::factory()->for($task)->for(memberOf($task->project->team), 'author')->create(['body' => 'Original']);
        signIn($task->project->team->owner);

        $this->patchJson("/api/v1/comments/{$comment->id}", ['body' => 'Edited'])->assertForbidden();

        expect($comment->fresh()->body)->toBe('Original');
    });
});

describe('destroy', function () {
    it('lets an admin moderate any comment', function () {
        $task = Task::factory()->create();
        $comment = Comment::factory()->for($task)->for(memberOf($task->project->team), 'author')->create();
        signIn(memberOf($task->project->team, TeamRole::Admin));

        $this->deleteJson("/api/v1/comments/{$comment->id}")->assertNoContent();

        $this->assertModelMissing($comment);
    });

    it('returns 403 when a member deletes someone else\'s comment', function () {
        $task = Task::factory()->create();
        $comment = Comment::factory()->for($task)->for(memberOf($task->project->team), 'author')->create();
        signIn(memberOf($task->project->team));

        $this->deleteJson("/api/v1/comments/{$comment->id}")->assertForbidden();

        $this->assertModelExists($comment);
    });
});
