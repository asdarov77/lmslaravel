<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M4 (design-manifest-2026 §2.3 / course-player-blueprint §3.1):
 * правила доступа к узлу курса — «drip content» из Inspector-панели
 * конструктора курса.
 *
 * Режимы (unlock_mode):
 *  - null        — без правила, узел открыт сразу (историческое поведение);
 *  - 'date'      — открыть по дате (unlock_at);
 *  - 'prereq'    — после завершения prerequisite_id (ссылка на aukstructure
 *                  того же курса, тип 3 = материал);
 *  - 'test'      — после сдачи экзамена min_score баллами (min_score 0..100).
 *
 * Колонки nullable и не участвуют ни в одном существующем запросе — до
 * подключения плеер мягко деградирует (manifest §3.1 п.2 тот же принцип).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aukstructures', function (Blueprint $table) {
            $table->string('unlock_mode', 16)->nullable()->after('position');
            $table->timestamp('unlock_at')->nullable()->after('unlock_mode');
            $table->unsignedBigInteger('prerequisite_id')->nullable()->after('unlock_at');
            $table->unsignedTinyInteger('min_score')->nullable()->after('prerequisite_id');
        });
    }

    public function down(): void
    {
        Schema::table('aukstructures', function (Blueprint $table) {
            $table->dropColumn(['unlock_mode', 'unlock_at', 'prerequisite_id', 'min_score']);
        });
    }
};
