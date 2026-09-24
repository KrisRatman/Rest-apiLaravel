<?php

use App\Http\Middleware\MarkDeprecatedVersion;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Страницы входа у API нет: гостю отвечаем 401 JSON, а не редиректом на route('login').
        $middleware->redirectGuestsTo(fn () => null);

        // Общий лимит запросов на всю группу api (лимитер 'api' в AppServiceProvider).
        $middleware->throttleApi();

        $middleware->alias([
            'deprecated' => MarkDeprecatedVersion::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Все ошибки API отдаются JSON-ом вида {"message": "...", "errors": {...}},
        // даже если клиент не прислал Accept: application/json.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Стандартный текст 404 для ненайденной модели раскрывает имя класса
        // («No query results for model [App\Models\Task] 7»), а отказ политики
        // через denyAsNotFound() отдаёт «Not Found». Приводим оба к одному ответу,
        // чтобы чужой ресурс было не отличить от несуществующего.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() === 404 && $request->is('api/*')) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }
        });
    })->create();
