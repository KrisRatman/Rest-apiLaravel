<?php

use App\Http\Controllers\Api\V1\AssignedTaskController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\InvitationAcceptanceController;
use App\Http\Controllers\Api\V1\LabelController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectStatsController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TeamInvitationController;
use App\Http\Controllers\Api\V1\TeamMemberController;
use App\Http\Controllers\Api\V1\TeamOwnershipController;
use Illuminate\Support\Facades\Route;

/*
 * API v1. Задачи и «мои задачи» устарели в пользу v2 и отдают заголовки
 * Deprecation, Sunset и Link на замену. Остальные эндпоинты v1 актуальны.
 */

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('register', [AuthController::class, 'register'])
        ->middleware('throttle:register')
        ->name('register');
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login');
    Route::post('logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('logout');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [ProfileController::class, 'show'])->name('me.show');
    Route::patch('me', [ProfileController::class, 'update'])->name('me.update');
    Route::put('me/password', [ProfileController::class, 'updatePassword'])->name('me.password');
    Route::get('me/tasks', [AssignedTaskController::class, 'index'])
        ->middleware('deprecated:v1,v2')
        ->name('me.tasks');

    Route::apiResource('teams', TeamController::class);

    Route::get('teams/{team}/members', [TeamMemberController::class, 'index'])->name('teams.members.index');
    Route::post('teams/{team}/members', [TeamMemberController::class, 'store'])->name('teams.members.store');
    Route::patch('teams/{team}/members/{user}', [TeamMemberController::class, 'update'])->name('teams.members.update');
    Route::delete('teams/{team}/members/{user}', [TeamMemberController::class, 'destroy'])->name('teams.members.destroy');
    Route::post('teams/{team}/ownership', [TeamOwnershipController::class, 'store'])->name('teams.ownership.store');

    Route::get('teams/{team}/invitations', [TeamInvitationController::class, 'index'])->name('teams.invitations.index');
    Route::post('teams/{team}/invitations', [TeamInvitationController::class, 'store'])
        ->middleware('throttle:invitations')
        ->name('teams.invitations.store');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'destroy'])->name('invitations.destroy');
    Route::post('invitations/accept', [InvitationAcceptanceController::class, 'store'])->name('invitations.accept');

    Route::get('me/exports', [ExportController::class, 'index'])->name('me.exports');
    Route::post('projects/{project}/exports', [ExportController::class, 'store'])
        ->middleware('throttle:exports')
        ->name('projects.exports.store');
    Route::get('projects/{project}/stats', [ProjectStatsController::class, 'show'])->name('projects.stats');
    Route::get('exports/{export}', [ExportController::class, 'show'])->name('exports.show');
    Route::get('exports/{export}/download', [ExportController::class, 'download'])->name('exports.download');

    Route::apiResource('teams.projects', ProjectController::class)->shallow();
    Route::apiResource('teams.labels', LabelController::class)->shallow()->except('show');
    Route::apiResource('projects.tasks', TaskController::class)
        ->shallow()
        ->middleware('deprecated:v1,v2');
    Route::apiResource('tasks.comments', CommentController::class)->shallow()->except('show');
});
