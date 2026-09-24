<?php

namespace App\Http\Requests\Team;

use App\Enums\TeamRole;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::in(TeamRole::assignable())],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'email' => ['description' => 'Email приглашённого. Аккаунт не обязателен — можно зарегистрироваться позже.', 'example' => 'new.colleague@example.com'],
            'role' => ['description' => 'Роль после принятия: `admin` или `member`.', 'example' => 'member'],
        ];
    }
}
