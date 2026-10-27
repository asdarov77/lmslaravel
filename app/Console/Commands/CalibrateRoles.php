<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Калибровка источников роли.
 *
 * Роль приходит из двух мест: колонка users.role и связь role_user.
 * Модель нормализует оба источника, но данные могут разойтись: если
 * роль задана только в колонке, а связь пуста (или наоборот), то при
 * переходе на связь пользователь потеряет права.
 *
 * Команда синхронизирует связь role_user из колонки users.role: для
 * каждого пользователя с заполненной колонкой создаёт связь с
 * соответствующей ролью. Если роль не найдена в справочнике — выводит
 * предупреждение.
 */
class CalibrateRoles extends Command
{
    protected $signature = 'roles:calibrate
        {--dry : только показать, что будет сделано, без изменений}';

    protected $description = 'Синхронизировать связь role_user из колонки users.role';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        /*
         * Ищем роль и по slug, и по русскому названию: в колонке
         * users.role лежат русские названия («Администратор»), а в
         * справочнике ролей есть и slug, и rolename.
         */
        $bySlug = Role::pluck('id', 'slug')->all();
        $byName = Role::pluck('id', 'rolename')->all();
        $aliases = User::ROLE_ALIASES;

        $created = 0;
        $missing = [];

        foreach (User::whereNotNull('role')->where('role', '!=', '')->get() as $user) {
            $slug = strtolower(trim($user->role));

            // Колонка может содержать русское название или slug — ищем оба.
            $roleId = $bySlug[$slug] ?? $byName[$user->role] ?? null;

            if ($roleId === null) {
                foreach ($aliases[$slug] ?? [] as $alias) {
                    if (isset($bySlug[$alias]) || isset($byName[$alias])) {
                        $roleId = $bySlug[$alias] ?? $byName[$alias];
                        break;
                    }
                }
            }

            if ($roleId === null) {
                $missing[] = $user->fio . ' (' . $user->role . ')';
                continue;
            }

            $exists = $user->roles()->where('role_id', $roleId)->exists();

            if (!$exists) {
                if (!$dry) {
                    $user->roles()->attach($roleId);
                }
                $created++;
            }
        }

        if ($dry) {
            $this->info('Режим просмотра: изменений не внесено.');
        }

        $this->info('Связей создано: ' . $created);

        if ($missing) {
            $this->warn('Роль не найдена для: ' . implode(', ', $missing));
        }

        return self::SUCCESS;
    }
}
