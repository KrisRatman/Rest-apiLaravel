<?php

namespace App\Http\Requests\Task;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Проверяет значения фильтров списка задач. Сами имена фильтров и сортировок
 * проверяет spatie/laravel-query-builder (неизвестные → 400).
 */
class ListTasksRequest extends FormRequest
{
    /**
     * Для задач проекта сначала проверяем членство, чтобы чужой получил 404, а не 422.
     * «Мои задачи» доступны любому авторизованному пользователю.
     */
    public function authorize(): Response
    {
        $project = $this->route('project');

        return $project instanceof Project
            ? Gate::inspect('viewAny', [Task::class, $project])
            : Response::allow();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.due_from' => ['sometimes', 'date_format:Y-m-d'],
            'filter.due_to' => ['sometimes', 'date_format:Y-m-d'],
            'filter.search' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'cursor' => ['sometimes', 'string', 'max:1000'],
        ];
    }
}
