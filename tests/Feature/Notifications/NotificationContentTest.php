<?php

use App\Enums\ExportStatus;
use App\Models\Export;
use App\Models\Task;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\ExportFinishedNotification;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Notifications\AnonymousNotifiable;

it('puts the team name, role and token into the invitation email', function () {
    config(['app.frontend_url' => 'https://app.example.com']);
    $invitation = TeamInvitation::factory()->create();
    $invitation->team->update(['name' => 'Mobile Team']);
    $token = str_repeat('a', 40);

    $mail = (new TeamInvitationNotification($invitation->fresh(), $token))
        ->toMail(new AnonymousNotifiable)
        ->render();

    expect((string) $mail)
        ->toContain('Mobile Team')
        ->toContain('as member')
        ->toContain($token)
        ->toContain('https://app.example.com/invitations/accept?token='.$token);
});

it('encrypts the queued invitation email because it carries the token', function () {
    expect(new TeamInvitationNotification(TeamInvitation::factory()->make(), 'token'))
        ->toBeInstanceOf(ShouldBeEncrypted::class);
});

it('escapes the task title in the assignment email', function () {
    $task = Task::factory()->create(['title' => '<script>alert("x")</script>']);

    $mail = (string) (new TaskAssignedNotification($task, User::factory()->create()))
        ->toMail($task->project->team->owner)
        ->render();

    expect($mail)
        ->toContain('&lt;script&gt;')
        ->not->toContain('<script>alert');
});

it('describes a completed and a failed export differently', function () {
    $completed = Export::factory()->completed()->create(['rows_count' => 42]);
    $failed = Export::factory()->create(['status' => ExportStatus::Failed]);

    $ready = (new ExportFinishedNotification($completed))->toMail($completed->user);
    $error = (new ExportFinishedNotification($failed))->toMail($failed->user);

    expect($ready->subject)->toEndWith('is ready')
        ->and(implode(' ', $ready->introLines))->toContain('42 tasks')
        ->and($error->subject)->toEndWith('failed')
        ->and($error->level)->toBe('error');
});
