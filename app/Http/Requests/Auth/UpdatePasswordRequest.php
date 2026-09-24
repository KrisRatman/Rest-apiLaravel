<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:sanctum'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'current_password' => ['description' => 'Текущий пароль.', 'example' => 'password'],
            'password' => ['description' => 'Новый пароль, минимум 8 символов.', 'example' => 'new-secret-456'],
            'password_confirmation' => ['description' => 'Повтор нового пароля.', 'example' => 'new-secret-456'],
        ];
    }
}
