<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/**
 * Монтирование маршрутов и HOME.
 *
 * routes/api.php подключается ОДИН раз под префиксом 'api', версионирование
 * сделано вложенной группой prefix('v1'). Отсюда маршруты вида '/v1/login',
 * а не '/api/v1/login'. Константа HOME указывает на '/home' — при смене
 * стартовой страницы поправить и RedirectIfAuthenticated.
 */
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

        /*
         * Ограничения на вход и регистрацию.
         *
         * Раньше здесь стоял закомментированный throttle:2,5, и на
         * логин/регистрацию не было никакого ограничения: перебор паролей
         * и массовая регистрация шли без препятствий.
         *
         * Лимит считается по IP и по имени пользователя: иначе один
         * пользователь с нескольких адресов обходил бы ограничение.
         */
        $perMinute = static function (int $limit): int {
            /*
             * Вне production лимит выше.
             *
             * Зачем: лимит защищает от перебора паролей, а не от
             * нормальной работы. E2E-тесты входят в приложение десятки
             * раз за прогон, и лимит 10/мин исчерпывался на середине
             * прогона: дальше КАЖДЫЙ следующий тест падал с 429
             * («логин администратора должен succeed»), причём падал
             * тот маршрут, который попался на израсходованный лимит, —
             * выглядело как случайный набор не связанных падений.
             *
             * На боевой машине поведение прежнее: 10 попыток в минуту.
             * Проверка не в .env, а в окружении: её нельзя случайно
             * выключить одной переменной на сервере, забыв её убрать.
             */
            return app()->environment('production') ? $limit : 1000;
        };

        // Стрелочные функции, а не обычные замыкания: обычное замыкание
        // переменную $perMinute не захватывает, и в логин уходил 500
        // «Undefined variable $perMinute».
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute($perMinute(10))
            ->by('login:' . $request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute($perMinute(5))
            ->by('register:' . $request->ip()));
    }
}