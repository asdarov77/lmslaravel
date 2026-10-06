<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Настройка «тренажёр включён».
 *
 * Создаётся миграцией, а не только сидером: переключатель должен
 * существовать сразу после обновления кода, иначе страница настроек
 * покажет «включено», а значение возьмётся из .env — и получится
 * ровно то расхождение, ради которого всё затевалось.
 *
 * insertOrIgnore: выбор методиста переживает обновление.
 *
 * Значение по умолчанию — выключено. Генерация вопросов сторонней
 * моделью не должна включаться сама.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->insertOrIgnore([
            'name' => 'tutor_enabled',
            'value' => '0',
            'type' => 'tutor',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->where('name', 'tutor_enabled')->delete();
    }
};
