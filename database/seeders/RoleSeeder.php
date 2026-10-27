<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Создаёт три системные роли: Администратор, Инструктор, Обучаемый.
 *
 * Раньше здесь стояло `new Role(); ->save()` с русскими slug'ами
 * ('Администратор'), без назначения прав и без идемпотентности:
 * повторный `db:seed` падал на roles_slug_unique. Кроме того, DatabaseSeeder
 * этот сидер вообще не вызывал, поэтому на чистой установке в базе не было
 * ни одной роли, а artisan permissions:sync пропускал их с предупреждением.
 *
 * Теперь:
 *  - роли берутся из config('permissions.system_roles') — единственный
 *    источник правды;
 *  - slug'ы канонические (admin/instructor/trainee), они же используются
 *    в User::ROLE_ALIASES и в проверке суперпользователя;
 *  - права назначаются согласно role_matrix (для администратора — все);
 *  - повторный запуск ничего не дублирует.
 */
class RoleSeeder extends Seeder
{
    public function run()
    {
        $systemRoles = (array) config('permissions.system_roles', []);
        $matrix = (array) config('permissions.role_matrix', []);

        foreach ($systemRoles as $slug => $definition) {
            $name = $definition['name'] ?? $slug;

            $role = Role::firstOrNew(['slug' => $slug]);
            $role->rolename = $name;

            // Если роль уже существовала под другим slug'ом (например,
            // legacy 'Инструктор'), переиспользуем её вместо дубля.
            if (! $role->exists) {
                $legacy = Role::where('rolename', $name)
                    ->where('slug', '!=', $slug)
                    ->first();

                if ($legacy) {
                    $role = $legacy;
                    $role->slug = $slug;
                }
            }

            $role->save();

            $permissionIds = $this->resolvePermissionSlugs($definition, $matrix, $name);

            if ($permissionIds !== []) {
                // sync, а не syncWithoutDetaching: набор системной роли —
                // эталон, который должен совпадать с конфигом.
                $role->permissions()->sync($permissionIds);
            }

            $this->command?->info(sprintf(
                '  роль «%s» (slug: %s) — прав: %d',
                $name,
                $slug,
                count($permissionIds)
            ));
        }
    }

    /**
     * Возвращает id прав для роли.
     *
     * @param  array  $definition  запись из permissions.system_roles
     * @param  array  $matrix  role_matrix
     * @param  string $name  имя роли (для поиска в матрице)
     * @return array<int>
     */
    private function resolvePermissionSlugs(array $definition, array $matrix, string $name): array
    {
        $mode = $definition['permissions'] ?? 'matrix';

        if ($mode === '*') {
            $ids = Permission::pluck('id')->all();

            return $ids === [] ? $this->idsByCatalogSlugs() : $ids;
        }

        $slugs = (array) ($matrix[$name] ?? []);

        if ($slugs === []) {
            // Матрица может быть названа каноническим slug'ом.
            $canonical = array_search($name, (array) config('permissions.system_roles', []), true);
            $slugs = is_string($canonical) ? (array) ($matrix[$canonical] ?? []) : [];
        }

        if ($slugs === []) {
            $this->command?->warn("  для роли «{$name}» не задана role_matrix — прав не назначено");

            return [];
        }

        return $this->idsByCatalogSlugs($slugs);
    }

    /**
     * Резолвит каталоговые slug'ы в id. Legacy-алиасы тоже принимаются.
     *
     * @param  array<int|string>|null  $slugs
     * @return array<int>
     */
    private function idsByCatalogSlugs(?array $slugs = null): array
    {
        $slugs ??= array_keys((array) config('permissions.permissions', []));

        if ($slugs === []) {
            return [];
        }

        $bySlug = Permission::pluck('id', 'slug');

        $ids = [];
        foreach ($slugs as $slug) {
            if ($bySlug->has($slug)) {
                $ids[] = (int) $bySlug->get($slug);
            }
        }

        return array_values(array_unique($ids));
    }
}
