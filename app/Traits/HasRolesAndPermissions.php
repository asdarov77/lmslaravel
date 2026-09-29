<?php

namespace App\Traits;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Cache;

trait HasRolesAndPermissions
{
    /**
     * Кэш набора slug'ов прав текущего пользователя на время запроса.
     *
     * @var array<int,string>|null
     */
    protected ?array $permissionSlugsCache = null;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permissions_users');
    }

    public function hasRole(...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->roles->contains('slug', $role)
                || $this->roles->contains('rolename', $role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Супер-администратор: роль admin (или «Администратор» в role_user).
     * Совпадает с User::isAdmin() — единый критерий для Gate и middleware.
     */
    public function isSuperAdmin(): bool
    {
        if (method_exists($this, 'isAdmin') && $this->isAdmin()) {
            return true;
        }

        return $this->hasRole('admin', 'Администратор');
    }

    /**
     * Есть ли у пользователя право через его роли (permissions_roles).
     * Раньше было закомментировано и не работало — восстановлено.
     */
    public function hasPermissionThroughRole(string $permission): bool
    {
        foreach ($this->roles as $role) {
            if ($role->permissions->contains('slug', $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Полный набор slug'ов прав пользователя:
     * прямые назначения + права через роли + legacy-алиасы каталога.
     *
     * @return array<int,string>
     */
    public function permissionSlugs(): array
    {
        if ($this->permissionSlugsCache !== null) {
            return $this->permissionSlugsCache;
        }

        // Eager-load связей, если модель ещё не загружена — один запрос
        // вместо N+1 при последовательных проверках в рамках запроса.
        $this->loadMissing(['permissions', 'roles.permissions']);

        $slugs = $this->permissions->pluck('slug')
            ->merge($this->roles->flatMap->permissions->pluck('slug'))
            ->filter()
            ->unique();

        // Legacy-алиасы: если у пользователя есть старый slug
        // (например manage-users), он даёт и новый (users.view), и наоборот.
        $aliases = PermissionCatalog::legacyAliases();
        $expanded = $slugs->map(function ($slug) use ($aliases) {
            return $aliases[$slug] ?? null;
        })->filter();

        return $this->permissionSlugsCache = $slugs->merge($expanded)->values()->all();
    }

    /**
     * Проверка права с учётом ролей и алиасов.
     * Используется middleware, Gate и контроллерами.
     */
    public function hasPermission($permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array((string) $permission, $this->permissionSlugs(), true);
    }

    /**
     * @param mixed ...$permissions
     */
    public function hasAnyPermission(...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed ...$permissions
     */
    public function hasAllPermissions(...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Сброс кэша прав (после chperm / синхронизации).
     */
    public function forgetPermissionCache(): void
    {
        $this->permissionSlugsCache = null;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllPermissions(array $permissions)
    {
        return Permission::whereIn('slug', $permissions)->get();
    }

    /**
     * @param mixed ...$permissions
     * @return $this
     */
    public function givePermissionsTo(...$permissions)
    {
        $permissions = $this->getAllPermissions($permissions);
        if ($permissions->isEmpty()) {
            return $this;
        }
        $this->permissions()->saveMany($permissions);
        $this->forgetPermissionCache();

        return $this;
    }

    /**
     * @param mixed ...$permissions
     * @return $this
     */
    public function deletePermissions(...$permissions)
    {
        $permissions = $this->getAllPermissions($permissions);
        $this->permissions()->detach($permissions);
        $this->forgetPermissionCache();

        return $this;
    }

    /**
     * @param mixed ...$permissions
     * @return $this
     */
    public function refreshPermissions(...$permissions)
    {
        $this->permissions()->detach();
        $this->forgetPermissionCache();

        return $this->givePermissionsTo(...$permissions);
    }
}
