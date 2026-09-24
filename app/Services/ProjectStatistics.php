<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;

/**
 * Сводка по задачам проекта для дашборда клиента.
 *
 * Считается несколькими агрегатными запросами, поэтому кэшируется. Кэш сбрасывает
 * TaskObserver при любом изменении задач проекта, а TTL — страховка на случай
 * изменений в обход моделей.
 */
class ProjectStatistics
{
    /**
     * @return array{
     *     total: int,
     *     open: int,
     *     overdue: int,
     *     completed_last_7_days: int,
     *     by_status: array<string, int>,
     *     open_by_priority: array<string, int>,
     *     open_by_assignee: list<array{user_id: int|null, name: string|null, open: int}>,
     *     generated_at: string
     * }
     */
    public function for(Project $project): array
    {
        return Cache::remember(
            self::cacheKey($project->id),
            config('api.cache_ttl.project_stats'),
            fn () => $this->calculate($project),
        );
    }

    public static function forget(int $projectId): void
    {
        Cache::forget(self::cacheKey($projectId));
    }

    public static function cacheKey(int $projectId): string
    {
        return "project:{$projectId}:stats";
    }

    /**
     * @return array<string, mixed>
     */
    private function calculate(Project $project): array
    {
        $byStatus = $project->tasks()->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $openByPriority = $project->tasks()->toBase()
            ->where('status', '!=', TaskStatus::Done)
            ->selectRaw('priority, count(*) as aggregate')
            ->groupBy('priority')
            ->pluck('aggregate', 'priority');

        $openByAssignee = $project->tasks()->toBase()
            ->leftJoin('users', 'users.id', '=', 'tasks.assignee_id')
            ->where('tasks.status', '!=', TaskStatus::Done)
            ->selectRaw('tasks.assignee_id as user_id, users.name as name, count(*) as open')
            ->groupBy('tasks.assignee_id', 'users.name')
            ->orderByDesc('open')
            ->orderBy('tasks.assignee_id')
            ->get()
            ->map(fn (object $row) => [
                'user_id' => $row->user_id === null ? null : (int) $row->user_id,
                'name' => $row->name,
                'open' => (int) $row->open,
            ])
            ->all();

        $total = (int) $byStatus->sum();

        return [
            'total' => $total,
            'open' => $total - (int) ($byStatus[TaskStatus::Done->value] ?? 0),
            'overdue' => $project->tasks()->overdue()->count(),
            'completed_last_7_days' => $project->tasks()
                ->where('completed_at', '>=', now()->subDays(7))
                ->count(),
            // Все значения enum, даже нулевые: клиенту не нужно гадать про отсутствующие ключи.
            'by_status' => collect(TaskStatus::cases())
                ->mapWithKeys(fn (TaskStatus $status) => [$status->value => (int) ($byStatus[$status->value] ?? 0)])
                ->all(),
            'open_by_priority' => collect(TaskPriority::cases())
                ->mapWithKeys(fn (TaskPriority $priority) => [$priority->value => (int) ($openByPriority[$priority->value] ?? 0)])
                ->all(),
            'open_by_assignee' => $openByAssignee,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
