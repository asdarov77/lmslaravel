<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Домашние задания и работы обучаемых.
 *
 * Задание привязано к курсу и (необязательно) к уроку, а также к
 * группе: задание для группы А не должно попасть в список группы Б,
 * иначе домашняя работа становится способом увидеть чужой список.
 *
 * Сдача хранится отдельно от задания: один и тот же студент сдаёт
 * работу несколько раз (исправление после замечаний), поэтому у
 * задания и сдачи разные таблицы, а не одна с колонкой.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->text('description')->nullable();

            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('aukstructure_id')->nullable()->constrained('aukstructures')->nullOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();

            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            // Срок сдачи. null означает «без срока»: задание открыто,
            // но не горит, и показывать вместо этого ноль было бы враньём.
            $table->dateTime('due_at')->nullable();

            // Максимальный балл задаётся явно: без него «оценка 5» из 10
            // и из 100 выглядели бы одинаково.
            $table->unsignedSmallInteger('max_score')->default(5);

            $table->dateTime('published_at')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'due_at']);
            $table->index('course_id');
        });

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Файл работы хранится в storage/public/assignments/... под
            // случайным именем: оригинальное имя файла не используется,
            // иначе можно было бы перезаписать чужую работу.
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->text('comment')->nullable();

            // new → submitted → reviewed. Отдельный статус удобнее
            // вычислять из наличия оценки: отдельная колонка разошлась
            // бы с оценкой при первом же изменении.
            $table->string('status')->default('new');

            $table->unsignedSmallInteger('score')->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();

            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();

            // Одна работа на студента и задание: пересдача обновляет
            // ту же строку, а не плодит дубликаты.
            $table->unique(['assignment_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');
    }
};