<?php

namespace App\Http\Resources\V2;

use App\Http\Resources\LabelResource;
use App\Http\Resources\UserResource;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Задача в API v2. Отличия от v1: статус и приоритет приходят объектами
 * с подписью для показа, вместо creator_id — вложенный автор.
 *
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => [
                'value' => $this->status,
                'label' => $this->status->label(),
            ],
            'priority' => [
                'value' => $this->priority,
                'label' => $this->priority->label(),
            ],
            'due_date' => $this->due_date?->toDateString(),
            'is_overdue' => $this->isOverdue(),
            'completed_at' => $this->completed_at,
            'assignee' => UserResource::make($this->whenLoaded('assignee')),
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'labels' => LabelResource::collection($this->whenLoaded('labels')),
            'comments_count' => $this->whenCounted('comments'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
