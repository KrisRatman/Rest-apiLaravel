<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\IssueToken;
use App\Actions\Auth\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Laravel\Sanctum\NewAccessToken;

#[Group('Авторизация', 'Регистрация, вход и выход. Токен передаётся в заголовке `Authorization: Bearer {token}`.')]
class AuthController extends Controller
{
    private const TOKEN_EXAMPLE = [
        'data' => [
            'user' => ['id' => 1, 'name' => 'Анна Смирнова', 'email' => 'anna@example.com', 'created_at' => '2026-09-24T10:00:00.000000Z'],
            'token' => '1|m3Qx7lJ8u0bEw3kS0fVb2cN4pT6rY9aD1gH5jK8l',
            'token_type' => 'Bearer',
        ],
    ];

    #[Unauthenticated]
    #[Endpoint('Регистрация', 'Создаёт пользователя и сразу возвращает токен доступа.')]
    #[ScribeResponse(self::TOKEN_EXAMPLE, 201)]
    #[ScribeResponse(['message' => 'The email has already been taken.', 'errors' => ['email' => ['The email has already been taken.']]], 422, 'Email уже занят')]
    public function register(RegisterRequest $request, RegisterUser $register): JsonResponse
    {
        $token = $register->handle(
            $request->safe()->only(['name', 'email', 'password']),
            $request->input('device_name', 'api'),
        );

        return $this->tokenResponse($token, Response::HTTP_CREATED);
    }

    #[Unauthenticated]
    #[Endpoint('Вход', 'Выдаёт новый токен для устройства. Не больше 5 попыток в минуту на пару email + IP.')]
    #[ScribeResponse(self::TOKEN_EXAMPLE)]
    #[ScribeResponse(['message' => 'These credentials do not match our records.', 'errors' => ['email' => ['These credentials do not match our records.']]], 422, 'Неверный email или пароль')]
    #[ScribeResponse(['message' => 'Too Many Attempts.'], 429, 'Превышен лимит попыток')]
    public function login(LoginRequest $request, IssueToken $issueToken): JsonResponse
    {
        $token = $issueToken->handle(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->input('device_name', 'api'),
        );

        return $this->tokenResponse($token);
    }

    #[Authenticated]
    #[Endpoint('Выход', 'Отзывает текущий токен. Токены других устройств продолжают работать.')]
    #[ScribeResponse(status: 204)]
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    private function tokenResponse(NewAccessToken $token, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'data' => [
                'user' => UserResource::make($token->accessToken->tokenable),
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
            ],
        ], $status);
    }
}
