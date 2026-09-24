<?php

namespace App\Http\Requests\Project;

use App\Models\Project;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('create', [Project::class, $this->route('team')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Название проекта.', 'example' => 'Релиз 2.0'],
            'description' => ['description' => 'Описание проекта.', 'example' => 'Новая корзина и оплата'],
        ];
    }
}
