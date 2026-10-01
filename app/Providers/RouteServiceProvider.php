<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/home';

    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));

            // Раньше routes/api.php монтировался ещё и под префиксом 'api/v1',
            // а внутри самого файла есть Route::prefix('v1')-группа. В итоге
            // версионированные маршруты регистрировались дважды и часть путей
            // становилась некорректной: /api/v1/v1/me, /api/api/v1/login и т.п.
            // Клиентам (фронт зовёт /api/login и /api/v1/me) нужен ровно один
            // корректный набор — он даётся единственным mount ниже, где
            // вложенная v1-группа формирует /api/v1/*.

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}