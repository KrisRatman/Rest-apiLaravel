<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Notifications\WelcomeNotification;
use Laravel\Sanctum\NewAccessToken;

class RegisterUser
{
    /**
     * Создаёт пользователя и сразу выдаёт токен, чтобы клиенту не нужен был отдельный вход.
     * Приветственное письмо уходит через очередь и не задерживает ответ.
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function handle(array $data, string $deviceName): NewAccessToken
    {
        $user = User::create($data);

        $user->notify(new WelcomeNotification);

        return $user->createToken($deviceName);
    }
}
