<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Размер страницы из ?per_page, в пределах 1–100.
     */
    protected function perPage(Request $request, int $default = 15): int
    {
        return max(1, min(100, $request->integer('per_page', $default)));
    }
}
