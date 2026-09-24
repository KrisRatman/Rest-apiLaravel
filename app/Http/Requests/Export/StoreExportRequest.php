<?php

namespace App\Http\Requests\Export;

use App\Models\Export;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Параметры выгрузки повторяют фильтры списка задач, только передаются в теле запроса.
 */
class StoreExportRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('create', [Export::class, $this->route('project')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // При вложенных правилах validated() оставляет в filter только описанные ключи,
        // поэтому перечислены все фильтры списка задач. Неизвестные имена отсекает
        // TaskListQuery в контроллере (400).
        return [
            'filter' => ['sometimes', 'array'],
            'filter.status' => ['sometimes'],
            'filter.priority' => ['sometimes'],
            'filter.assignee_id' => ['sometimes'],
            'filter.label' => ['sometimes'],
            'filter.overdue' => ['sometimes', 'boolean'],
            'filter.due_from' => ['sometimes', 'date_format:Y-m-d'],
            'filter.due_to' => ['sometimes', 'date_format:Y-m-d'],
            'filter.search' => ['sometimes', 'string', 'max:100'],
            'sort' => ['sometimes', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'filter' => ['description' => 'Фильтры как у списка задач: `status`, `priority`, `assignee_id`, `label`, `overdue`, `due_from`, `due_to`, `search`. Пусто — все задачи проекта.', 'example' => ['status' => 'todo,in_progress', 'overdue' => '1']],
            'filter.status' => ['description' => 'Статус или несколько через запятую.', 'example' => 'No-example'],
            'filter.priority' => ['description' => 'Приоритет или несколько через запятую.', 'example' => 'No-example'],
            'filter.assignee_id' => ['description' => 'ID исполнителя.', 'example' => 'No-example'],
            'filter.label' => ['description' => 'ID метки или несколько через запятую.', 'example' => 'No-example'],
            'filter.overdue' => ['description' => '`1` — только просроченные.', 'example' => 'No-example'],
            'filter.due_from' => ['description' => 'Срок не раньше даты, `Y-m-d`.', 'example' => 'No-example'],
            'filter.due_to' => ['description' => 'Срок не позже даты, `Y-m-d`.', 'example' => 'No-example'],
            'filter.search' => ['description' => 'Поиск по заголовку и описанию.', 'example' => 'No-example'],
            'sort' => ['description' => 'Порядок строк в файле, как у списка задач.', 'example' => '-priority,due_date'],
        ];
    }

    /**
     * Только параметры выборки — их job подставит в TaskListQuery.
     *
     * @return array{filter?: array<string, mixed>, sort?: string}
     */
    public function exportFilters(): array
    {
        return $this->safe()->only(['filter', 'sort']);
    }
}
