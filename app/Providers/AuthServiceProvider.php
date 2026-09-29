<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Policies\GroupPolicy;
use App\Policies\PermissionPolicy;
class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
        'App\Models\Group' => 'App\Policies\GroupPolicy',
        'App\Models\Permission' => 'App\Policies\PermissionPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Супер-администратор проходит любую проверку Gate (Moodle site admin /
        // Canvas root admin). ВАЖНО: возвращать true только для admin, иначе — null,
        // чтобы не переопределять политики других пользователей.
        Gate::before(function ($user, $ability) {
            return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()
                ? true
                : null;
        });

        // Каждое право из каталога (config/permissions.php) становится
        // ability'ю Gate: $user->can('users.view'), @can('courses.manage') в Blade.
        foreach (array_keys(config('permissions.permissions', [])) as $slug) {
            Gate::define($slug, fn ($user) => $user->hasPermission($slug));
        }

        Gate::define('view-group', [GroupPolicy::class, 'view']);
        Gate::define('delete-group', [GroupPolicy::class, 'delete']);
        
        //Gate::define('view-user', [PermissionPolicy::class, 'viewAny']);
        //Gate::define('delete-user', [PermissionPolicy::class, 'update']);
        //Gate::define('update-user', [PermissionPolicy::class, 'delete']);
        Gate::resource('permissions', PermissionPolicy::class);
        //Gate::define('manage-users', [PermissionPolicy::class, 'delete']);
        //Gate::define('manage-users', [PermissionPolicy::class, 'update']);

        
   
    }
}
