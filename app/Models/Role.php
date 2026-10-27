<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Роль (roles: rolename + slug).
 *
 * Права берутся из pivot permissions_roles. Одноимённая колонка rights удалена
 * миграцией намеренно: пока она существовала, часть кода читала права из неё и
 * получала пустоту, хотя роль была настроена.
 */
class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'rolename',
        'slug',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Права роли живут в pivot-таблице permissions_roles.
     *
     * Раньше в таблице roles была колонка permissions (text), которая
     * перебивала это отношение: обращение $role->permissions возвращало
     * значение колонки (null), а не связь, из-за чего permissionSlugs()
     * молча терял все права, выданные через роль. Колонка удалена
     * миграцией 2026_09_30_000001_fix_roles_rbac_structure.
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permissions_roles');
    }
}
