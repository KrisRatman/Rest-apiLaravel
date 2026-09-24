<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\QueryBuilder;
use UnexpectedValueException;

abstract class Controller
{
    /**
     * Размер страницы из ?per_page, в пределах 1–100.
     */
    protected function perPage(Request $request, int $default = 15): int
    {
        return max(1, min(100, $request->integer('per_page', $default)));
    }

    /**
     * Курсорная страница из ?cursor и ?per_page.
     *
     * Курсор хранит значения полей сортировки последней строки. Если клиент сменил
     * сортировку, но прислал старый курсор, нужных полей в нём нет и Laravel
     * бросает исключение. Отвечаем 422 вместо 500.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  QueryBuilder<TModel>  $query
     * @return CursorPaginator<int, TModel>
     */
    protected function cursorPage(QueryBuilder $query, Request $request): CursorPaginator
    {
        try {
            return $query->cursorPaginate($this->perPage($request))->withQueryString();
        } catch (UnexpectedValueException) {
            throw ValidationException::withMessages([
                'cursor' => 'The cursor does not match the requested sort. Request the first page again.',
            ]);
        }
    }
}
