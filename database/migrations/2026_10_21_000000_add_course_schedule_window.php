<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Окно доступа к курсу: opens_at / closes_at.
 *
 * Раньше расписание курса жило только в голове составителя: курс был
 * «виден» или «скрыт», и переключение расписания руками в момент X
 * забывали. Теперь окно задаётся в конструкторе курса и проверяется
 * на выдаче материалов, не требуя ручных переключений.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->date('opens_at')->nullable();
            $table->date('closes_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['opens_at', 'closes_at']);
        });
    }
};
