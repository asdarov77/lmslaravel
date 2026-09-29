<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Индексы целостности для RBAC-каталога.
 *
 * Дубликаты slug в permissions ломали sync/lookup (hasPermission мог
 * вернуть два объекта на одно право), а отсутствие unique в pivot-таблицах
 * допускало повторные attach одних и тех же прав.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Чистим дубликаты перед постановкой unique-индексов.
        DB::table('permissions')
            ->selectRaw('slug, MIN(id) as keep_id')
            ->groupBy('slug')
            ->havingRaw('COUNT(*) > 1')
            ->each(function ($row) {
                DB::table('permissions')
                    ->where('slug', $row->slug)
                    ->where('id', '!=', $row->keep_id)
                    ->delete();
            });

        Schema::table('permissions', function (Blueprint $table) {
            $table->unique('slug');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->unique('slug');
        });

        // Pivot-таблицы с составным primary key уже защищены от дублей,
        // но если исторически они создавались без primary key — добавляем
        // уникальный индекс условно.
        foreach (['permissions_users' => ['user_id', 'permission_id'],
                  'permissions_roles' => ['role_id', 'permission_id']] as $t => $cols) {
            if (!Schema::hasTable($t)) {
                continue;
            }
            $indexes = collect(DB::select("SELECT indexname FROM pg_indexes WHERE tablename = '{$t}'"))
                ->pluck('indexname')->all();
            $hasPkOrUnique = collect($indexes)->contains(
                fn ($name) => str_starts_with($name, 'pkey') || str_contains($name, '_unique')
            );
            if (!$hasPkOrUnique) {
                Schema::table($t, function (Blueprint $table) use ($cols) {
                    $table->unique($cols);
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropUnique(['slug']);
        });
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['slug']);
        });
    }
};
