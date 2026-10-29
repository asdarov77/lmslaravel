<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage; //added
use Illuminate\Support\Facades\URL; //added
use App\Http\Controllers\GiftController;

/**
 * Общие регистрации сервисов приложения. Содержимое минимально: основные
 * правила живут в AuthServiceProvider и PermissionServiceProvider.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // регистрируем класс контроллера для вызова его метода из другого контроллера
        $this->app->singleton(GiftController::class, function ($app) {
            return new GiftController();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    // https://webstool.ru/ustanovit-laravel-v-otdelnuyu-poddirektoriyu-site.rularavel.html
    // public function boot()
    // {
    //     $this->app->bind('path.public', function() {
    //         return base_path().'/../docs/lc';
    //       });
    // }
    public function boot()
    {
        // П1 (аудит): инвалидация кэша доступа CourseAccess при изменении
        // учебных записей. Без неё только что записанная группа получит 403
        // до конца TTL, а снятая — сохранит доступ сверх положенного.
        \App\Models\Group2learning::observe(\App\Observers\Group2learningObserver::class);

        // Storage::disk('local')->buildTemporaryUrlsUsing (function ($path, $expiration, $options) {
        //     return URL::temporarySignedRoute(
        //         'local.temp',
        //         $expiration,
        //         array_merge($options, ['path' => $path])
        //     );
        // });
    }




}
