<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Сообщает клиенту, что эндпоинт устарел и у него есть замена в новой версии API.
 *
 * Deprecation (RFC 9745) — с какой даты версия устарела, Sunset (RFC 8594) — когда
 * её отключат, Link с rel="successor-version" — адрес того же ресурса в новой версии.
 * Замена ищется по имени маршрута: v1.projects.tasks.index → v2.projects.tasks.index.
 *
 * Использование: ->middleware('deprecated:v1,v2').
 */
class MarkDeprecatedVersion
{
    public function handle(Request $request, Closure $next, string $version, string $successor): Response
    {
        $response = $next($request);

        $dates = config("api.deprecations.{$version}");

        if (! is_array($dates)) {
            return $response;
        }

        $response->headers->set('Deprecation', '@'.Carbon::parse($dates['deprecated_at'])->getTimestamp());
        $response->headers->set('Sunset', Carbon::parse($dates['sunset_at'])->toRfc7231String());

        $successorUrl = $this->successorUrl($request, $version, $successor);

        if ($successorUrl !== null) {
            $response->headers->set('Link', "<{$successorUrl}>; rel=\"successor-version\"");
        }

        return $response;
    }

    private function successorUrl(Request $request, string $version, string $successor): ?string
    {
        $route = $request->route();
        $name = $route?->getName();

        if ($name === null || ! Str::startsWith($name, "{$version}.")) {
            return null;
        }

        $successorName = $successor.'.'.Str::after($name, "{$version}.");

        return Route::has($successorName)
            ? route($successorName, $route->originalParameters())
            : null;
    }
}
