<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Общие правила для создания и изменения задачи.
 * Исполнитель и метки обязаны принадлежать команде проекта.
 */
abstract class TaskRequest extends FormRequest
{
    /**
     * ID команды, в которой проверяются исполнитель и метки.
     * null — модель маршрута не привязана (так правила читает генератор документации).
     */
    abstract protected function teamId(): ?int;

    /**
     * true для PATCH: проверяются только переданные поля.
     */
    abstract protected function isPartial(): bool;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->teamId();

        return [
            'title' => [...($this->isPartial() ? ['sometimes'] : []), 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'status' => ['sometimes', 'required', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', 'required', Rule::enum(TaskPriority::class)],
            'assignee_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('team_user', 'user_id')->where('team_id', $teamId),
            ],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'label_ids' => ['sometimes', 'array', 'max:20'],
            'label_ids.*' => [
                'integer', 'distinct',
                Rule::exists('labels', 'id')->where('team_id', $teamId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assignee_id.exists' => 'The assignee must be a member of the team.',
            'label_ids.*.exists' => 'The label must belong to the team.',
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'title' => ['description' => 'Заголовок задачи.', 'example' => 'Сверстать экран корзины'],
            'description' => ['description' => 'Подробное описание.', 'example' => 'По макету из Figma, с пустым состоянием'],
            'status' => ['description' => 'Статус: `todo`, `in_progress`, `review`, `done`. При переходе в `done` проставляется `completed_at`.', 'example' => 'in_progress'],
            'priority' => ['description' => 'Приоритет: `low`, `medium`, `high`, `urgent`.', 'example' => 'high'],
            'assignee_id' => ['description' => 'ID исполнителя — участника команды. `null` снимает исполнителя.', 'example' => 2],
            'due_date' => ['description' => 'Срок в формате `Y-m-d`.', 'example' => '2026-10-15'],
            'label_ids' => ['description' => 'ID меток команды. Список заменяет текущие метки целиком.', 'example' => [1, 3]],
            'label_ids.*' => ['description' => 'ID метки.', 'example' => 1],
        ];
    }
}
