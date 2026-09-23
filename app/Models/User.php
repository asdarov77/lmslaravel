<?php

namespace App\Models;

use App\Traits\HasRolesAndPermissions;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;



class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRolesAndPermissions;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'fio',
        'password',

    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // public function roles()
    // {
    //     return $this->belongsToMany(Role::class);
    // }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }


    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function courses()
    {
        return $this->hasManyThrough(Course::class, Group::class);
    }

    public function testResults()
    {
        return $this->hasMany(TestResult::class);
    }



    // связь многие ко многим с таблицей категорий
//    public function categories()
//    {
//        return $this->belongsToMany(Category::class);
//    }

    // public function courses()
    // {
    //     return $this->hasManyThrough(Course::class,Category::class);
    // }

    //----------------------------------------------
    // из связанной таблицы Role (если) для текущего пользователя проверить есть ли имя роли равное инструктору или администратору
    // public function isAdmin()
    // {
    //     //return $this->where('role', 'Администратор')->exists();
    //     return $this->roles()->where('rolename', 'Администратор')->exists();
    // }
    // public function isInstructor()
    // {
    //     //return $this->where('role', 'Инструктор')->exists();
    //     return $this->roles()->where('rolename', 'Инструктор')->exists();
    // }
    // public function isStudent()
    // {
    //     //return $this->where('role', 'Обучаемый')->exists();
    //     return $this->roles()->where('rolename', 'Обучаемый')->exists();
    // }
    //----------------------------------------------
    public function getAllPermissionsAttribute()
    {
        $permissions = [];
        foreach (Permission::all() as $permission) {
            if ($this->user()->can($permission->name)) {
                $permissions[] = $permission->name;
            }
        }
        return $permissions;
    }
}
