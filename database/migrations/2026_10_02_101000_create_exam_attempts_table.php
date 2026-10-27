<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Попытки сдачи экзамена.
 *
 * Зачем отдельная таблица, а не существующая test_results:
 *  - test_results хранит по строке на ОТВЕТ (user_id, answer_id), то
 *    есть попытку из неё не собрать — нет ни номера попытки, ни общего
 *    балла, ни времени сдачи как одного события;
 *  - test_results объявлена в ClearDBController как очищаемая «под вопросы»,
 *    то есть это часть справочника, а не журнал успеваемости.
 *
 * Новую таблицу оставляем отдельной: попытка — цельная единица, и её
 * можно посчитать для ограничения max_attempts и показать в кабинете.
 *
 * exam_id nullable: экзамен может быть не назначен (свободный прогон по
 * паре модуль+специальность, как раньше), и попытку всё равно пишем.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('exam_id')->nullable()->constrained('exams')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Итог попытки. score — доля верных ответов, 0..1.
            $table->unsignedSmallInteger('total_count')->default(0);
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->decimal('score', 5, 4)->default(0.0000);
            $table->boolean('passed')->default(false);

            $table->timestamp('submitted_at')->useCurrent();

            $table->timestamps();

            $table->index(['user_id', 'exam_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
    }
};
