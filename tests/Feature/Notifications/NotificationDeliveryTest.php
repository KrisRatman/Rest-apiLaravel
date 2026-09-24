<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Support\Facades\Notification;

it('queues a welcome email after registration', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Anna',
        'email' => 'anna@example.com',
        'password' => 'secret-pass-123',
        'password_confirmation' => 'secret-pass-123',
    ])->assertCreated();

    Notification::assertSentTo(User::firstWhere('email', 'anna@example.com'), WelcomeNotification::class);
});

it('notifies the assignee when a task is created for them', function () {
    Notification::fake();
    $project = Project::factory()->create();
    $assignee = memberOf($project->team);
    $creator = signIn(memberOf($project->team));

    $this->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Task', 'assignee_id' => $assignee->id])
        ->assertCreated();

    Notification::assertSentTo(
        $assignee,
        TaskAssignedNotification::class,
        fn (TaskAssignedNotification $notification) => $notification->assignedBy->is($creator),
    );
});

it('does not notify users who assign a task to themselves', function () {
    Notification::fake();
    $project = Project::factory()->create();
    $member = signIn(memberOf($project->team));

    $this->postJson("/api/v1/projects/{$project->id}/tasks", ['title' => 'Task', 'assignee_id' => $member->id])
        ->assertCreated();

    Notification::assertNothingSent();
});

it('notifies only the new assignee when the assignee changes', function () {
    Notification::fake();
    $project = Project::factory()->create();
    $previous = memberOf($project->team);
    $next = memberOf($project->team);
    $task = Task::factory()->for($project)->create(['assignee_id' => $previous->id]);
    signIn(memberOf($project->team));

    $this->patchJson("/api/v1/tasks/{$task->id}", ['assignee_id' => $next->id])->assertOk();

    Notification::assertSentTo($next, TaskAssignedNotification::class);
    Notification::assertNotSentTo($previous, TaskAssignedNotification::class);
});

it('does not notify when other fields change', function () {
    Notification::fake();
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create(['assignee_id' => memberOf($project->team)->id]);
    signIn(memberOf($project->team));

    $this->patchJson("/api/v1/tasks/{$task->id}", ['title' => 'Renamed', 'assignee_id' => $task->assignee_id])->assertOk();

    Notification::assertNothingSent();
});
