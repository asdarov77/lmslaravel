<?php

namespace App\Providers;

use App\Models\Permission;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Policies\GroupPolicy;

/**
 * Регистрация прав как «abilities» Gate.
 *
 * Тело boot() закомментировано: проверки прав идут через middleware
 * `permission`/`permission.all` и HasRolesAndPermissions, а не через
 * $user->can('slug'). Не раскомментировать без необходимости — появятся
 * две разные проверки с разными правилами.
 */
class PermissionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {        

        // try {
        //     Permission::get()->map(function ($permission) {
        //         Gate::define($permission->slug, function ($user) use ($permission) {
        //             return $user->hasPermissionTo($permission);
        //         });
        //     });
        // } catch (\Exception $e) {
        //     report($e);
        //     return false;
        // }
    }
}
