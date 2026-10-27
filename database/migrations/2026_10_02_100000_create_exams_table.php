<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Экзамены: назначение экзамена конкретной группе/обучаемому.
 *
 * Зачем: экзамен в системе существовал только как «взять вопросы по
 * модулю и специальности и пройти». Не было сущности «экзамен», поэтому
 * негде хранить: кому назначен, когда открыт, сколько попыток, какой
 * проходной балл. Попыток в базе было 0 — результаты никто не
 * сохранял.
 *
 * Отбор вопросов опирается на существующую структуру банка: вопросы
 * принадлежат паре (category_id, aukstructure_id), поэтому у экзамена
 * те же поля. Новой модели вопросов не заводим.
 *
 * group_id и user_id nullable: NULL означает «всем группам» (с exams.
 * publish, см. контроллер) либо индивидуальное назначение.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();

            $table->string('title')->default('');

            // Отбор вопросов — как в банке: модуль + специальность.
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('aukstructure_id')->nullable()->constrained('aukstructures')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();

            // Кому назначен. Одно из двух заполнено (проверяется в
            // контроллере): либо все группы, либо конкретный обучаемый.
            $table->foreignId('group_id')->nullable()->constrained('groups')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();

            // Окно доступности и срок сдачи.
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->timestamp('due_at')->nullable();

            $table->unsignedSmallInteger('max_attempts')->default(1);
            // Доля верных ответов, 0..1. 0.5 — как в grade_boundaries.
            $table->decimal('passing_score', 5, 4)->default(0.5000);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
