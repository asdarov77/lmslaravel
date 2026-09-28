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
        'role',
        'group_id',
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
    // Проверка роли по атрибуту `role` таблицы users.
    // Поддерживаются как русские названия ('Администратор', 'Инструктор', 'Обучаемый'),
    // так и англоязычные слаги ('admin', 'instructor', 'trainee'/'student').
    public function isAdmin(): bool
    {
        return in_array($this->role, ['Администратор', 'admin'], true);
    }

    public function isInstructor(): bool
    {
        return in_array($this->role, ['Инструктор', 'instructor'], true);
    }

    public function isStudent(): bool
    {
        return in_array($this->role, ['Обучаемый', 'trainee', 'student'], true);
    }

    // Алиас для isStudent() (используется в тестах/API)
    public function isTrainee(): bool
    {
        return $this->isStudent();
    }
    //----------------------------------------------
    public function getAllPermissionsAttribute()
    {
        $permissions = [];
        foreach (Permission::all() as $permission) {
            if ($this->hasPermission($permission->slug)) {
                $permissions[] = $permission->name;
            }
        }
        return $permissions;
    }
}
