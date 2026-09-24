<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

#[Group('Профиль', 'Данные текущего пользователя.')]
class ProfileController extends Controller
{
    #[Endpoint('Текущий пользователь')]
    #[ResponseFromApiResource(UserResource::class, User::class)]
    public function show(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    #[Endpoint('Обновить профиль', 'Передавайте только те поля, которые нужно изменить.')]
    #[ResponseFromApiResource(UserResource::class, User::class)]
    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $user->update($request->validated());

        return UserResource::make($user);
    }

    #[Endpoint('Сменить пароль', 'Требует текущий пароль. Все токены, кроме текущего, отзываются — другие устройства придётся войти заново.')]
    #[ScribeResponse(status: 204)]
    #[ScribeResponse(['message' => 'The password is incorrect.', 'errors' => ['current_password' => ['The password is incorrect.']]], 422, 'Неверный текущий пароль')]
    public function updatePassword(UpdatePasswordRequest $request): Response
    {
        $user = $request->user();
        $user->update(['password' => $request->validated('password')]);

        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        return response()->noContent();
    }
}
