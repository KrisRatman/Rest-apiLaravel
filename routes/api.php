<?php

use App\Http\Controllers\Api\V1\AssignedTaskController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\LabelController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TeamMemberController;
use App\Http\Controllers\Api\V1\TeamOwnershipController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function () {
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
        Route::get('me/tasks', [AssignedTaskController::class, 'index'])->name('me.tasks');

        Route::apiResource('teams', TeamController::class);

        Route::get('teams/{team}/members', [TeamMemberController::class, 'index'])->name('teams.members.index');
        Route::post('teams/{team}/members', [TeamMemberController::class, 'store'])->name('teams.members.store');
        Route::patch('teams/{team}/members/{user}', [TeamMemberController::class, 'update'])->name('teams.members.update');
        Route::delete('teams/{team}/members/{user}', [TeamMemberController::class, 'destroy'])->name('teams.members.destroy');
        Route::post('teams/{team}/ownership', [TeamOwnershipController::class, 'store'])->name('teams.ownership.store');

        Route::apiResource('teams.projects', ProjectController::class)->shallow();
        Route::apiResource('teams.labels', LabelController::class)->shallow()->except('show');
        Route::apiResource('projects.tasks', TaskController::class)->shallow();
        Route::apiResource('tasks.comments', CommentController::class)->shallow()->except('show');
    });
});
