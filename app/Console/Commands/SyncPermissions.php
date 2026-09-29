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

        // 2. Матрица ролей
        $assigned = 0;
        foreach (config('permissions.role_matrix', []) as $roleName => $slugs) {
            $role = Role::where('rolename', $roleName)->first()
                ?? Role::where('slug', $roleName)->first();

            if (!$role) {
                $this->warn("роль «{$roleName}» не найдена — пропущена");
                continue;
            }

            $ids = Permission::whereIn('slug', $slugs)->pluck('id');
            $missing = array_diff($slugs, Permission::whereIn('id', $ids)->pluck('slug')->all());
            foreach ($missing as $m) {
                $this->warn("  право {$m} отсутствует в БД — пропущено");
            }

            $before = DB::table('permissions_roles')->where('role_id', $role->id)->count();
            $existing = DB::table('permissions_roles')->where('role_id', $role->id)->pluck('permission_id')->all();
            foreach ($ids as $id) {
                if (!in_array($id, $existing)) {
                    DB::table('permissions_roles')->insert([
                        'role_id' => $role->id,
                        'permission_id' => $id,
                    ]);
                    $assigned++;
                }
            }
            $this->line("роль «{$roleName}»: было {$before}, добавлено " . ($assigned));
        }

        PermissionCatalog::flushCache();
        $this->info("Готово. Создано прав: {$created}, назначено связей: {$assigned}.");

        return self::SUCCESS;
    }
}
