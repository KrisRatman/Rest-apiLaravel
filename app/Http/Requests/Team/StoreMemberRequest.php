<?php

namespace App\Http\Requests\Team;

use App\Enums\TeamRole;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('manageMembers', $this->route('team'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', Rule::exists('users', 'email')],
            'role' => ['required', Rule::in(TeamRole::assignable())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.exists' => 'No registered user has this email.',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'email' => ['description' => 'Email зарегистрированного пользователя.', 'example' => 'ivan@example.com'],
            'role' => ['description' => 'Роль в команде: `admin` или `member`.', 'example' => 'member'],
        ];
    }
}
