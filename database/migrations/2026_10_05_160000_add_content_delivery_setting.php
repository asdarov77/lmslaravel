<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Настройка способа раздачи приватного контента.
 *
 * Зачем миграция, а не только сидер: переключатель «отдаёт Laravel или
 * nginx» должен существовать в рабочей базе сразу после обновления
 * кода. Иначе настройки берутся из config, и администратор включает
 * nginx в интерфейсе, а страница продолжает работать по-старому без
 * единого предупреждения.
 *
 * insertOrIgnore, а не upsert: если администратор уже переключил режим,
 * миграция не должна молча затирать его выбор обратно на 'php'.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->insertOrIgnore([
            'name' => 'content_delivery',
            'value' => 'php',
            'type' => 'content_delivery',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->where('name', 'content_delivery')->delete();
    }
};
