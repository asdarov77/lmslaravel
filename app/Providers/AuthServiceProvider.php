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
