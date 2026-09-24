<?php

namespace App\Http\Requests\Task;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class StoreTaskRequest extends TaskRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('create', [Task::class, $this->project()]);
    }

    protected function teamId(): ?int
    {
        return $this->project()?->team_id;
    }

    protected function isPartial(): bool
    {
        return false;
    }

    private function project(): ?Project
    {
        return $this->route('project');
    }
}
