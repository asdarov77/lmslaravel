<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Исправление структуры roles для полноценного RBAC.
 *
 * 1) Удаляется колонка roles.permissions (text). Права роли хранятся в
 *    pivot-таблице permissions_roles, а колонка перебивала отношение
 *    permissions(): обращение $role->permissions возвращало значение
 *    колонки (всегда null), из-за чего HasRolesAndPermissions::permissionSlugs()
 *    молча отбрасывал все права, выданные через роль. Перед удалением
 *    проверяем, что колонка действительно пуста.
 *
 * 2) rolename переводится из char(20) в string: PostgreSQL дополняет
 *    char(20) пробелами, из-за чего 'Наставник' сравнивается с
 *    'Наставник           ', ломаются уникальные ограничения, сортировка
 *    и генерация slug.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('roles', 'permissions')) {
            $filled = DB::table('roles')->whereNotNull('permissions')->where('permissions', '!=', '')->count();

            if ($filled > 0) {
                throw new \RuntimeException(
                    "roles.permissions содержит {$filled} заполненных строк — "
                    . 'сначала перенесите их в permissions_roles вручную.'
                );
            }

            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('permissions');
            });
        }

        if (Schema::hasColumn('roles', 'rolename')) {
            // char(20) -> varchar: снимает дополнение пробелами.
            DB::statement('ALTER TABLE roles ALTER COLUMN rolename TYPE varchar(255)');
            DB::statement('ALTER TABLE roles ALTER COLUMN rolename DROP DEFAULT');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('roles', 'rolename')) {
            DB::statement('ALTER TABLE roles ALTER COLUMN rolename TYPE char(20)');
        }

        if (! Schema::hasColumn('roles', 'permissions')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->text('permissions')->nullable();
            });
        }
    }
};
