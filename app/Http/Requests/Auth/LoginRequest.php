<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'email' => ['description' => 'Email пользователя.', 'example' => 'demo@example.com'],
            'password' => ['description' => 'Пароль.', 'example' => 'password'],
            'device_name' => ['description' => 'Название устройства. По умолчанию `api`.', 'example' => 'Pixel 8'],
        ];
    }
}
