<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Серверный прогресс чтения курсов (resume-point).
 * Закрывает дефект Д6: API писал last_file/last_anchor, но читать было некуда —
 * «продолжить чтение» работало только через localStorage текущего устройства.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('course_progress')) {
            return;
        }

        Schema::create('course_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('course_id')->index();
            // последняя открытая структура АУК (материал)
            $table->unsignedBigInteger('last_item_id')->nullable();
            // якорь/позиция внутри документа (для прокрутки)
            $table->string('last_anchor', 255)->nullable();
            // процент просмотренных материалов курса (кэш, источник истины — links)
            $table->unsignedTinyInteger('percent')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_progress');
    }
};
