<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ручные оценки преподавателя.
 *
 * Зачем отдельная таблица: автоматические результаты лежат в
 * exam_attempts (доля правильных ответов), а преподавателю иногда
 * нужно поставить свою оценку — за устный ответ, за лабораторную
 * работу, за пересдачу. Правкой exam_attempts это не сделать: там
 * хранится сырой счёт, и «процент правильных» перестал бы означать
 * то же самое, на чём держатся пороги зачёта.
 *
 * Ручная оценка имеет приоритет над автоматической: грейдбук
 * показывает её, пока преподаватель её не снимет.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_overrides', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();

            // Оценка по пятибалльной шкале (2..5) — та же, что и
            // grade_boundaries, поэтому пороги применяются одинаково.
            $table->unsignedTinyInteger('grade');

            // Комментарий преподавателя виден обучаемому: без него
            // «5» невозможно отличить от «5 после пересдачи».
            $table->text('comment')->nullable();

            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Одна ручная оценка на пару «обучаемый, экзамен».
            $table->unique(['user_id', 'exam_id']);
            $table->index(['exam_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_overrides');
    }
};