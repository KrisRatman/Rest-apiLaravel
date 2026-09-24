<?php

use App\Http\Controllers\Api\V2\AssignedTaskController;
use App\Http\Controllers\Api\V2\TaskController;
use Illuminate\Support\Facades\Route;

/*
 * API v2. Здесь только ресурсы с несовместимыми изменениями: задачи
 * с курсорной пагинацией и новым форматом ответа. Всё остальное клиент v2
 * по-прежнему берёт из v1: авторизацию, команды, проекты, комментарии.
 * Имена маршрутов совпадают с v1, по ним v1 находит замену для заголовка Link.
 */

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me/tasks', [AssignedTaskController::class, 'index'])->name('me.tasks');

    Route::apiResource('projects.tasks', TaskController::class)->shallow();
});
