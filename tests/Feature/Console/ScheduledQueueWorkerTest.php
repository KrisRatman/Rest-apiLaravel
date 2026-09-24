<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

function scheduledQueueWorker(): Event
{
    return collect(app(Schedule::class)->events())
        ->first(fn (Event $event) => str_contains($event->command, 'queue:work'));
}

it('runs the queue worker from the scheduler on shared hosting', function () {
    config(['api.scheduled_queue_worker' => true]);

    expect(scheduledQueueWorker()->filtersPass(app()))->toBeTrue()
        ->and(scheduledQueueWorker()->command)->toContain('--stop-when-empty');
});

it('does not start a second worker where a queue container already runs', function () {
    config(['api.scheduled_queue_worker' => false]);

    expect(scheduledQueueWorker()->filtersPass(app()))->toBeFalse();
});
