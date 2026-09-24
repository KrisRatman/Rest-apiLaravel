<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

class IssueToken
{
    /**
     * Проверяет email и пароль и выдаёт токен для устройства.
     *
     * @throws ValidationException
     */
    public function handle(string $email, string $password, string $deviceName): NewAccessToken
    {
        $user = User::where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        return $user->createToken($deviceName);
    }
}
