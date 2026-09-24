<?php

use App\Enums\TeamRole;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('every member can read and write comments', function (TeamRole $role) {
    $task = Task::factory()->create();
    $gate = Gate::forUser(memberOf($task->project->team, $role));

    expect($gate->allows('viewAny', [Comment::class, $task]))->toBeTrue()
        ->and($gate->allows('create', [Comment::class, $task]))->toBeTrue();
})->with(TeamRole::cases());

test('abilities on someone else\'s comment', function (TeamRole $role, bool $canUpdate, bool $canDelete) {
    $task = Task::factory()->create();
    $comment = Comment::factory()->for($task)->for(memberOf($task->project->team), 'author')->create();
    $gate = Gate::forUser(memberOf($task->project->team, $role));

    expect($gate->allows('update', $comment))->toBe($canUpdate)
        ->and($gate->allows('delete', $comment))->toBe($canDelete);
})->with([
    'owner' => [TeamRole::Owner, false, true],
    'admin' => [TeamRole::Admin, false, true],
    'member' => [TeamRole::Member, false, false],
]);

test('the author can update and delete their comment', function () {
    $task = Task::factory()->create();
    $author = memberOf($task->project->team);
    $comment = Comment::factory()->for($task)->for($author, 'author')->create();
    $gate = Gate::forUser($author);

    expect($gate->allows('update', $comment))->toBeTrue()
        ->and($gate->allows('delete', $comment))->toBeTrue();
});

test('an author who left the team loses access to their comment', function () {
    $comment = Comment::factory()->create();
    $formerMember = User::findOrFail($comment->user_id);

    expect(Gate::forUser($formerMember)->inspect('update', $comment)->status())->toBe(404);
});
