<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Право всегда идентифицируется слагом. Если слаг не задан явно,
     * получаем его из имени, чтобы не требовать дублирования данных.
     */
    protected static function booted(): void
    {
        static::saving(function (self $permission) {
            if (filled($permission->slug) || blank($permission->name)) {
                return;
            }

            // Идентификаторы в проекте используют оба стиля — edit_courses и
            // create-tasks, поэтому имя без пробелов считаем готовым слагом,
            // а остальные приводим к kebab-case.
            $permission->slug = preg_match('/^[\w\-]+$/u', $permission->name) === 1
                ? mb_strtolower($permission->name)
                : (Str::slug($permission->name) ?: mb_strtolower($permission->name));
        });
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'permissions_roles');
    }
}
