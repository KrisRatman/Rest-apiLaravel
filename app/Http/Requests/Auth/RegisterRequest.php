<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Имя пользователя.', 'example' => 'Анна Смирнова'],
            'email' => ['description' => 'Email, он же логин.', 'example' => 'anna@example.com'],
            'password' => ['description' => 'Пароль, минимум 8 символов.', 'example' => 'secret-pass-123'],
            'password_confirmation' => ['description' => 'Повтор пароля.', 'example' => 'secret-pass-123'],
            'device_name' => ['description' => 'Название устройства — так токен будет подписан в списке сессий. По умолчанию `api`.', 'example' => 'iPhone 15'],
        ];
    }
}
