<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

/**
 * Каталог прав из config/permissions.php.
 *
 * Регресс: сидер создавал ровно три legacy-права
 * (manage-users / create-tasks / manage-course) и на этом останавливался.
 * RoleSeeder назначает ролям права по slug'ам из role_matrix, поэтому
 * остальные права (content.manage, courses.manage, system.maintenance,
 * users.manage и т. д.) в БД отсутствовали, resolvePermissionSlugs()
 * молча возвращал [], и на чистой базе роли оставались без прав вообще.
 * Меню и кнопки, скрытые по правам, исчезали.
 *
 * Теперь сидер идемпотентно синхронизирует полный каталог config, не
 * трогая уже существующие строки (в том числе legacy) и связи ролей.
 */
class PermissionSeeder extends Seeder
{
    public function run()
    {
        $catalog = (array) config('permissions.permissions', []);
        $created = 0;

        foreach ($catalog as $slug => $meta) {
            $permission = Permission::firstOrCreate(
                ['slug' => (string) $slug],
                ['name' => $meta['name'] ?? $slug]
            );

            if ($permission->wasRecentlyCreated) {
                $created++;
            }
        }

        // Legacy-права, которых нет в каталоге, оставляем: на них могут
        // ссылаться уже существующие связи permissions_users.
        // Права из role_matrix, которых нет в permissions.permissions,
        // тоже обязаны существовать: иначе resolvePermissionSlugs()
        // вернёт [] и роль останется без прав.
        foreach ($this->matrixSlugs() as $slug) {
            if (Permission::where('slug', $slug)->exists()) {
                continue;
            }

            Permission::create(['slug' => $slug, 'name' => $slug]);
            $created++;
        }

        $this->command?->info(sprintf(
            '  прав в каталоге: %d, создано: %d',
            count($catalog),
            $created
        ));

        PermissionCatalog::flushCache();
    }

    /**
     * Все slug'ы, встречающиеся в role_matrix.
     *
     * @return array<int, string>
     */
    private function matrixSlugs(): array
    {
        $slugs = [];

        foreach ((array) config('permissions.role_matrix', []) as $roleSlugs) {
            foreach ((array) $roleSlugs as $slug) {
                $slugs[(string) $slug] = true;
            }
        }

        return array_keys($slugs);
    }
}