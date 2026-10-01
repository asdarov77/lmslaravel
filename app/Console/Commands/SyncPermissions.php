<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Синхронизация каталога прав (config/permissions.php) с БД:
 *  - создаёт отсутствующие права по slug/name;
 *  - назначает матрицу прав ролям (кроме администратора — у него bypass);
 *  - сбрасывает кэш алиасов.
 *
 * Идемпотентна, безопасна для повторного запуска.
 */
class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync {--fresh : предварительно очистить permissions_roles}';
    protected $description = 'Синхронизировать каталог прав из config/permissions.php с таблицами permissions и permissions_roles';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            DB::table('permissions_roles')->delete();
            $this->warn('Связи permissions_roles очищены.');
        }

        // 1. Права из каталога
        $created = 0;
        foreach (config('permissions.permissions', []) as $slug => $meta) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $meta['name'] ?? $slug]
            );
            if ($permission->wasRecentlyCreated) {
                $created++;
                $this->line("создано право: <info>{$slug}</info>");
            }
        }

        // 2. Системные роли + матрица прав
        //
        // Раньше здесь стояло `if (!$role) { warn(...); continue; }`: на базе
        // без предварительного db:seed ролей не было, и команда рапортовала
        // «роль не найдена — пропущена», оставляя систему без ролей вообще.
        // Теперь отсутствующие системные роли создаются, а существующие
        // legacy-роли (с русскими slug'ами) переиспользуются.
        $systemRoles = (array) config('permissions.system_roles', []);
        $matrix = (array) config('permissions.role_matrix', []);
        $allPermissionIds = Permission::pluck('id')->all();
        $assigned = 0;

        foreach ($systemRoles as $slug => $definition) {
            $name = $definition['name'] ?? $slug;
            $role = $this->findOrCreateSystemRole($slug, $name);

            if ($role->wasRecentlyCreated) {
                $this->line("создана роль: <info>{$name}</info> (slug: {$slug})");
            }

            $mode = $definition['permissions'] ?? 'matrix';

            if ($mode === '*') {
                $ids = $allPermissionIds;
            } else {
                $slugs = (array) ($matrix[$name] ?? $matrix[$slug] ?? []);
                $ids = Permission::whereIn('slug', $slugs)->pluck('id')->all();

                foreach (array_diff($slugs, Permission::whereIn('id', $ids)->pluck('slug')->all()) as $missing) {
                    $this->warn("  право {$missing} отсутствует в БД — пропущено");
                }
            }

            $existing = DB::table('permissions_roles')->where('role_id', $role->id)->pluck('permission_id')->all();
            $newForRole = 0;

            foreach ($ids as $id) {
                if (!in_array($id, $existing)) {
                    DB::table('permissions_roles')->insert([
                        'role_id' => $role->id,
                        'permission_id' => $id,
                    ]);
                    $assigned++;
                    $newForRole++;
                }
            }

            $this->line("роль «{$name}»: прав всего " . count($ids) . ", добавлено {$newForRole}");
        }

        // Роли из матрицы, которых нет в system_roles, — тоже не теряем:
        // создаём по имени, чтобы конфиг и БД не расходились.
        foreach ($matrix as $roleName => $slugs) {
            $slug = $this->slugForRoleName($roleName);

            if (Role::where('slug', $slug)->exists() || Role::where('rolename', $roleName)->exists()) {
                continue;
            }

            Role::create(['rolename' => $roleName, 'slug' => $slug]);
            $this->line("создана роль из role_matrix: <info>{$roleName}</info> (slug: {$slug})");
        }

        PermissionCatalog::flushCache();
        $this->info("Готово. Создано прав: {$created}, назначено связей: {$assigned}.");

        return self::SUCCESS;
    }

    /**
     * Находит или создаёт системную роль с каноническим slug'ом.
     * Legacy-роль с тем же rolename переиспользуется (slug мигрируется).
     */
    private function findOrCreateSystemRole(string $slug, string $name): Role
    {
        $role = Role::where('slug', $slug)->first();

        if ($role) {
            return $role;
        }

        $legacy = Role::where('rolename', $name)->first();

        if ($legacy) {
            $legacy->slug = $slug;
            $legacy->save();

            return $legacy;
        }

        return Role::create(['rolename' => $name, 'slug' => $slug]);
    }

    /**
     * Канонический slug для названия роли из role_matrix.
     */
    private function slugForRoleName(string $roleName): string
    {
        foreach ((array) config('permissions.system_roles', []) as $slug => $definition) {
            if (($definition['name'] ?? null) === $roleName) {
                return (string) $slug;
            }
        }

        return \Illuminate\Support\Str::slug($roleName) ?: \Illuminate\Support\Str::ascii($roleName);
    }
}
