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
