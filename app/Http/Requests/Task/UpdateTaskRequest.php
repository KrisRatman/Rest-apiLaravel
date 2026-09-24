<?php

namespace App\Http\Requests\Task;

use App\Models\Task;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

class UpdateTaskRequest extends TaskRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->task());
    }

    protected function teamId(): ?int
    {
        return $this->task()?->project->team_id;
    }

    protected function isPartial(): bool
    {
        return true;
    }

    private function task(): ?Task
    {
        return $this->route('task');
    }
}
