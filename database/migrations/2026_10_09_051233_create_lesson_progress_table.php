<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Прогресс обучаемого по отдельным урокам курса.
 *
 * Зачем: прогресс раньше жил только в localStorage браузера
 * (CourseManifest::markVisited). Из-за этого «продолжить обучение» и
 * процент на дашборде терялись при смене устройства, при очистке кэша
 * и вообще не работали у другого браузера. Теперь состояние живёт в
 * базе и принадлежит конкретному пользователю.
 *
 * Урок здесь — это запись aukstructures конкретного курса: верхний
 * уровень (parent_id IS NULL) — модуль, вложенные — разделы.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('aukstructures')->cascadeOnDelete();

            // Процент прохождения урока: 0 — открыт, 100 — материал пройден.
            $table->unsignedTinyInteger('percent')->default(0);

            // Где остановился: имя последнего открытого файла и, если
            // материал это HTML, якорь внутри документа. Без этого
            // «продолжить» открывало бы курс с начала.
            $table->string('last_file')->nullable();
            $table->string('last_anchor')->nullable();

            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // Прогресс урока у пользователя уникален: повторное открытие
            // обновляет строку, а не плодит дубликаты.
            $table->unique(['user_id', 'lesson_id']);
            $table->index(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
    }
};