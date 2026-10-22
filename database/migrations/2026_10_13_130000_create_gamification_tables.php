<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Геймификация: журнал начислений и выданные бейджи.
 *
 * XP не хранится столбиком на пользователе, а складывается из журнала
 * `point_events`. Иначе нельзя объяснить, откуда взялось число: при
 * рассылке одноразовых «+10 за вход» итог сходился бы с суммой, но
 * при удалении ошибочной записи не сходился бы уже никогда. Журнал
 * даёт и историю, и возможность пересчёта, и защиту от повторных
 * начислений за одно и то же действие.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Не обязателен: экзамен и сертификат привязаны к курсу,
            // а достижение вроде «первый урок» — нет.
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();

            // Что произошло: lesson_completed, exam_passed и т. п.
            $table->string('kind');

            $table->integer('points')->default(0);

            /*
             * Ключ источника — защита от повторного начисления.
             *
             * Для урока это "lesson:12", для экзамена "exam:7:user:3".
             * Уникальный индекс: без него повторный запрос прогресса
             * или переотправка формы удваивали бы баллы, а исправить
             * это можно было бы только вручную в базе.
             */
            $table->string('source_key');

            $table->timestamp('occurred_at');

            $table->timestamps();

            $table->unique(['user_id', 'source_key']);
            $table->index(['user_id', 'occurred_at']);
        });

        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('badge_key');

            // Когда выдан: показывается в галерее, и по дате видно
            // прогресс, а не просто список всего доступного.
            $table->timestamp('awarded_at');

            $table->timestamps();

            $table->unique(['user_id', 'badge_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('point_events');
    }
};