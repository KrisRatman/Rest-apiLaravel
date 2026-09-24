<?php

use Illuminate\Support\Facades\Route;

/*
 * Версии API. Версия — часть URL (/api/v1, /api/v2): её видно в логах,
 * её легко указать в клиенте и в документации.
 */

Route::prefix('v1')->name('v1.')->group(base_path('routes/api/v1.php'));
Route::prefix('v2')->name('v2.')->group(base_path('routes/api/v2.php'));
