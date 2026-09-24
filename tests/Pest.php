<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Пользователь с указанной ролью в команде. Для owner возвращает владельца команды.
 */
function memberOf(Team $team, TeamRole $role = TeamRole::Member): User
{
    if ($role === TeamRole::Owner) {
        return $team->owner;
    }

    $user = User::factory()->create();
    $team->members()->attach($user, ['role' => $role]);

    return $user;
}

/**
 * Авторизует пользователя через Sanctum и возвращает его.
 */
function signIn(?User $user = null): User
{
    $user ??= User::factory()->create();

    Sanctum::actingAs($user);

    return $user;
}
