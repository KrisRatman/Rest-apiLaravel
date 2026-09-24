<?php

use App\Models\Export;
use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

// Раз в сутки удаляем выгрузки старше 7 дней (вместе с CSV-файлами)
// и просроченные приглашения.
Schedule::command('model:prune', ['--model' => [Export::class, TeamInvitation::class]])
    ->daily()
    ->onOneServer()
    ->withoutOverlapping();

// На виртуальном хостинге нет постоянных процессов: воркер очереди запускает
// cron через планировщик, и он разбирает задания, пока они есть. В Docker
// работает отдельный контейнер queue, поэтому по умолчанию выключено.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->when(fn (): bool => (bool) config('api.scheduled_queue_worker'));
