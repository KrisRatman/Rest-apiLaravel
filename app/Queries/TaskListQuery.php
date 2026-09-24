<?php

namespace App\Queries;

use App\Models\Task;
use App\Queries\Sorts\PrioritySort;
use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Фильтры и сортировка списка задач. Общие для задач проекта и «моих задач».
 *
 * Пример: ?filter[status]=todo,in_progress&filter[overdue]=1&sort=-priority,due_date
 */
class TaskListQuery
{
    /**
     * Выборка для постраничного списка (API v1) и выгрузки CSV.
     *
     * @param  Builder<Task>|Relation<Task, *, *>  $subject
     * @return QueryBuilder<Task>
     */
    public static function for(BuilderContract $subject, Request $request): QueryBuilder
    {
        return self::filtered($subject, $request)
            ->allowedSorts(
                'created_at',
                'due_date',
                'title',
                AllowedSort::custom('priority', new PrioritySort),
            )
            ->defaultSort('-created_at')
            ->orderByDesc('id')
            ->with(['assignee', 'labels'])
            ->withCount('comments');
    }

    /**
     * Выборка для курсорной пагинации (API v2).
     *
     * Курсор умеет продолжать выдачу только по обычным колонкам без NULL,
     * поэтому важность и срок сортируются по вычисляемым колонкам
     * priority_weight и due_date_sort. Задачи без срока идут после задач со сроком.
     *
     * @param  Builder<Task>|Relation<Task, *, *>  $subject
     * @return QueryBuilder<Task>
     */
    public static function forCursor(BuilderContract $subject, Request $request): QueryBuilder
    {
        return self::filtered($subject, $request)
            ->allowedSorts(
                'created_at',
                'title',
                AllowedSort::field('due_date', 'due_date_sort'),
                AllowedSort::field('priority', 'priority_weight'),
            )
            ->defaultSort('-created_at')
            ->orderByDesc('id')
            ->with(['assignee', 'creator', 'labels'])
            ->withCount('comments');
    }

    /**
     * @param  Builder<Task>|Relation<Task, *, *>  $subject
     * @return QueryBuilder<Task>
     */
    private static function filtered(BuilderContract $subject, Request $request): QueryBuilder
    {
        return QueryBuilder::for($subject, $request)
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('priority'),
                AllowedFilter::exact('assignee_id'),
                AllowedFilter::exact('project_id'),
                AllowedFilter::callback('label', fn (Builder $query, mixed $value) => $query
                    ->whereHas('labels', fn (Builder $labels) => $labels->whereIn('labels.id', (array) $value))),
                AllowedFilter::callback('overdue', fn (Builder $query, mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                    ? $query->overdue()
                    : $query),
                AllowedFilter::callback('due_from', fn (Builder $query, mixed $value) => $query->whereDate('due_date', '>=', $value)),
                AllowedFilter::callback('due_to', fn (Builder $query, mixed $value) => $query->whereDate('due_date', '<=', $value)),
                AllowedFilter::callback('search', fn (Builder $query, mixed $value) => $query->where(
                    fn (Builder $search) => $search
                        ->where('title', 'like', '%'.implode(',', (array) $value).'%')
                        ->orWhere('description', 'like', '%'.implode(',', (array) $value).'%'),
                )),
            );
    }
}
