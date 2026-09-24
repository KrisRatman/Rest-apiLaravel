<?php

namespace App\Queries\Sorts;

use App\Enums\TaskPriority;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Sorts\Sort;

/**
 * Сортировка по важности, а не по алфавиту: low < medium < high < urgent.
 *
 * @implements Sort<Task>
 */
class PrioritySort implements Sort
{
    public function __invoke(Builder $query, bool $descending, string $property): void
    {
        $cases = collect(TaskPriority::cases());

        $sql = 'CASE '.$query->getQuery()->getGrammar()->wrap($property)
            .$cases->map(fn () => ' WHEN ? THEN ?')->implode('')
            .' ELSE 0 END '.($descending ? 'DESC' : 'ASC');

        $bindings = $cases
            ->flatMap(fn (TaskPriority $priority) => [$priority->value, $priority->weight()])
            ->all();

        $query->orderByRaw($sql, $bindings);
    }
}
