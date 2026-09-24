<?php

namespace App\Http\Requests\Team;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('team'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Новое название команды.', 'example' => 'Мобильная команда'],
            'description' => ['description' => 'Новое описание.', 'example' => null],
        ];
    }
}
