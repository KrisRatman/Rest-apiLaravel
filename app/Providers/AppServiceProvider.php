<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Лимиты читаются из config/api.php при каждом запросе, счётчики живут
     * в кэше по умолчанию (Redis в Docker), поэтому общие для всех контейнеров API.
     */
    private function configureRateLimiting(): void
    {
        $limit = fn (string $name): int => config("api.rate_limits.{$name}");

        // Общий лимит: авторизованного считаем по пользователю, а не по IP,
        // чтобы коллеги из одного офиса за NAT не делили квоту.
        RateLimiter::for('api', fn (Request $request) => $request->user()
            ? Limit::perMinute($limit('api'))->by('user:'.$request->user()->id)
            : Limit::perMinute($limit('guest'))->by('ip:'.$request->ip()));

        // Перебор паролей ограничиваем по паре email + IP: так один офис за NAT
        // не блокирует друг друга, а атака на один аккаунт всё равно упирается в лимит.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute($limit('login'))->by(
            Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip()),
        ));

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute($limit('register'))->by($request->ip()));

        // Каждое приглашение — письмо на произвольный адрес, поэтому ограничиваем рассылку.
        RateLimiter::for('invitations', fn (Request $request) => Limit::perMinute($limit('invitations'))->by($request->user()?->id ?: $request->ip()));

        // Выгрузка — тяжёлый фоновый запрос к базе.
        RateLimiter::for('exports', fn (Request $request) => Limit::perMinute($limit('exports'))->by($request->user()?->id ?: $request->ip()));
    }
}
