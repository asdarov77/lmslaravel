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
        'email',
        'surname',
        'name',
        'patronymic',
        'role',
        'group_id',
        'phonenumber',
        'city',
        'country',
        'organization',
        'position',
        'rank',
        'spfere',
        'specialization',
    ];

    /**
     * Роли пользователя. Приложение исторически использует русские
     * наименования, API-тесты — английские. Поддерживаем оба варианта.
     *
     * @var array<string, array<int, string>>
     */
    public const ROLE_ALIASES = [
        'admin' => ['admin', 'Администратор'],
        'instructor' => ['instructor', 'Инструктор'],
        'trainee' => ['trainee', 'Обучаемый'],
    ];

    /**
     * Собирает fio из фамилии/имени/отчества, если они заданы.
     */
    protected static function booted(): void
    {
        static::saving(function (self $user) {
            if (!$user->isDirty(['surname', 'name', 'patronymic'])) {
                return;
            }

            $parts = array_filter(
                [$user->surname, $user->name, $user->patronymic],
                static fn ($value) => filled($value)
            );

            if ($parts !== []) {
                $user->fio = implode(' ', $parts);
            }
        });
    }

    /**
     * Приводит произвольное написание роли к каноническому slug'у.
     * Возвращает null, если значение не опознано.
     */
    public static function canonicalRoleSlug(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (self::ROLE_ALIASES as $canonical => $variants) {
            foreach ($variants as $variant) {
                if (mb_strtolower($variant) === mb_strtolower($value)) {
                    return $canonical;
                }
            }
        }

        return null;
    }

    /**
     * Канонические slug'ы ролей пользователя — объединение ДВУХ источников:
     * строковой колонки users.role и связи role_user.
     *
     * Раньше isAdmin()/isTrainee() читали только колонку `role`. Но роль,
     * назначенная через UI, идёт через AuthController::chroll, который
     * синхронизирует ТОЛЬКО role_user и колонку не трогает. В итоге такой
     * пользователь считался «без роли»: role_matrix не выдавал ему базовых
     * прав, а Home.vue вообще не находил компонент и показывал пустой
     * экран. Теперь оба источника равноправны.
     *
     * @return array<int,string>
     */
    public function roleSlugs(): array
    {
        $slugs = [];

        $fromColumn = self::canonicalRoleSlug($this->attributes['role'] ?? null);

        if ($fromColumn !== null) {
            $slugs[] = $fromColumn;
        } else {
            // Неизвестное значение не теряем: фронт должен показать его как
            // есть, а не получить пустоту и не догадаться.
            $raw = trim((string) ($this->attributes['role'] ?? ''));

            if ($raw !== '') {
                $slugs[] = $raw;
            }
        }

        try {
            $this->loadMissing('roles');

            foreach ($this->roles as $role) {
                $slug = self::canonicalRoleSlug($role->slug ?? null)
                    ?? self::canonicalRoleSlug($role->rolename ?? null);

                if ($slug !== null) {
                    $slugs[] = $slug;
                    continue;
                }

                $fallback = trim((string) ($role->slug ?? $role->rolename ?? ''));

                if ($fallback !== '') {
                    $slugs[] = $fallback;
                }
            }
        } catch (\Throwable $e) {
            // Связи может не быть в схеме (часть установок). Роль из
            // колонки мы уже получили — падать из-за этого нельзя.
            \Illuminate\Support\Facades\Log::warning('roleSlugs: role relation check failed: '.$e->getMessage());
        }

        return array_values(array_unique($slugs));
    }

    /**
     * Единый формат ролей для /api/login и /api/v1/me.
     * Раньше login отдавал только $user->roles->pluck('rolename') —
     * список названий без slug'ов, а /me — массив объектов. Фронт сравнивал
     * строки и разбирался с этим в двух местах по-разному.
     *
     * @return array<int,array{id:int|null,name:string,slug:string}>
     */
    public function rolePayloads(): array
    {
        $payload = [];

        foreach ($this->roleSlugs() as $slug) {
            $payload[$slug] = [
                'id' => $this->roles->firstWhere('slug', $slug)?->id,
                'name' => $slug,
                'slug' => $slug,
            ];
        }

        try {
            $this->loadMissing('roles');

            foreach ($this->roles as $role) {
                $slug = self::canonicalRoleSlug($role->slug ?? null)
                    ?? self::canonicalRoleSlug($role->rolename ?? null)
                    ?? trim((string) ($role->slug ?? $role->rolename ?? ''));

                if ($slug === '') {
                    continue;
                }

                $payload[$slug] = [
                    'id' => $role->id ?? null,
                    'name' => (string) ($role->rolename ?: $role->slug),
                    'slug' => $slug,
                ];
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('rolePayloads: role relation check failed: '.$e->getMessage());
        }

        return array_values($payload);
    }

    protected function hasRoleAlias(string $role): bool
    {
        return in_array($role, $this->roleSlugs(), true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRoleAlias('admin');
    }

    public function isInstructor(): bool
    {
        return $this->hasRoleAlias('instructor');
    }

    public function isTrainee(): bool
    {
        return $this->hasRoleAlias('trainee');
    }

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
            if ($this->hasPermission($permission->slug)) {
                $permissions[] = $permission->name;
            }
        }
        return $permissions;
    }
}
