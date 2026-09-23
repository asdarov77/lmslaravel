<?php

namespace App\Traits;

use App\Models\Role;
use App\Models\Permission;

trait HasRolesAndPermissions
{
    /**
     * @return mixed
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role-user');
    }
    /**
     * @return mixed
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permissions_users');
    }

    public function hasRole(... $roles ) {
        foreach ($roles as $role) {
            if ($this->roles->contains('slug', $role)) {
                return true;
            }
        }
        return false;
    }

    public function hasPermission($permission)
    {
        return (bool) $this->permissions->where('slug', $permission)->count();
        //return $this->permissions->where('slug', $permission)->count();
        
    }
    /**
     * @param $permission
     * @return bool
     */

    //проверять, есть ли у Пользователя Права через его Роль
    // public function hasPermissionThroughRole($permission)
    // {
    //     foreach ($permission->roles as $role) {
    //         if ($this->roles->contains($role)) {
    //             return true;
    //         }
    //     }
    //     return false;
    // }
    
    // public function hasPermissionTo($permission)
    // {
    //     return $this->hasPermissionThroughRole($permission) || $this->hasPermission($permission->slug);
    // }
    //выдача прав
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
        if ($permissions === null) {
            return $this;
        }
        $this->permissions()->saveMany($permissions);
        return $this;
    }
    // удалить права
    public function deletePermissions(...$permissions)
    {
        $permissions = $this->getAllPermissions($permissions);
        $this->permissions()->detach($permissions);
        return $this;
    }
    /**
     * @param mixed ...$permissions
     * @return HasRolesAndPermissions
     */
    public function refreshPermissions(...$permissions)
    {
        $this->permissions()->detach();
        return $this->givePermissionsTo($permissions);
    }
    // тестовый удалить
    public function hasPerm($permission)
    {
        //return (bool) $this->permissions->where('slug', $permission)->count();
        return $this->permissions->where('slug', $permission)->count();
    }
}
