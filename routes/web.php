<?php

use Illuminate\Support\Facades\Route;

// У API нет своих страниц: главная и /docs ведут на сгенерированную документацию.
// Сервер (FrankenPHP, php artisan serve) не ищет index.html в папке сам.
Route::redirect('/', '/docs/index.html');
Route::redirect('/docs', '/docs/index.html');
