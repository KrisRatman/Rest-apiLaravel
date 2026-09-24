<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\ProjectStatistics;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;

#[Group('Проекты')]
class ProjectStatsController extends Controller
{
    #[Endpoint('Статистика проекта', 'Сводка для дашборда: задачи по статусам, открытые по приоритетам и исполнителям, просроченные, закрытые за 7 дней. Ответ кэшируется в Redis и сбрасывается при любом изменении задач проекта; `generated_at` — время расчёта.')]
    #[ScribeResponse(['data' => [
        'total' => 8,
        'open' => 7,
        'overdue' => 2,
        'completed_last_7_days' => 1,
        'by_status' => ['todo' => 4, 'in_progress' => 2, 'review' => 1, 'done' => 1],
        'open_by_priority' => ['low' => 2, 'medium' => 2, 'high' => 1, 'urgent' => 2],
        'open_by_assignee' => [
            ['user_id' => 1, 'name' => 'Demo User', 'open' => 2],
            ['user_id' => 3, 'name' => 'Maria Sokolova', 'open' => 2],
            ['user_id' => null, 'name' => null, 'open' => 1],
        ],
        'generated_at' => '2026-09-24T10:00:00+00:00',
    ]])]
    public function show(Project $project, ProjectStatistics $statistics): JsonResponse
    {
        Gate::authorize('view', $project);

        return response()->json(['data' => $statistics->for($project)]);
    }
}
