<?php

namespace App\Actions\Auth;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

class RegisterUser
{
    /**
     * Создаёт пользователя и сразу выдаёт токен, чтобы клиенту не нужен был отдельный вход.
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function handle(array $data, string $deviceName): NewAccessToken
    {
        $user = User::create($data);

        return $user->createToken($deviceName);
    }
}
