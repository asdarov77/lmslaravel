<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Форум: темы вопросов и ответы на них.
 *
 * Тема привязана к курсу и (необязательно) к уроку — вопрос «про этот
 * материал» и вопрос «про этот курс» живут в одной ленте, но фильтр
 * работает по обоим привязкам.
 *
 * Ответы хранятся отдельно от темы: тема — это вопрос и его состояние
 * (закреплена, закрыта, решена), ответы — плоский список реплик под ним.
 * Иначе состояние пришлось бы хранить в теле первого сообщения и
 * разбирать его при каждом чтении.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_topics', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->text('body');

            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('aukstructure_id')->nullable()->constrained('aukstructures')->nullOnDelete();

            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();

            // Приватность темы: null — видна всем, кто открыл курс,
            // иначе только своей группе.
            $table->foreignId('group_id')->nullable()->constrained()->cascadeOnDelete();

            $table->boolean('pinned')->default(false);
            // Закрытая тема не принимает новых ответов: разбор вопроса
            // окончен, иначе обсуждение расползается на 200 сообщений.
            $table->boolean('locked')->default(false);

            // Отмеченный преподавателем ответ с решением.
            $table->foreignId('solution_post_id')->nullable();

            $table->unsignedInteger('views')->default(0);
            $table->timestamp('last_activity_at')->nullable();

            $table->timestamps();

            $table->index(['course_id', 'pinned']);
            $table->index('author_id');
        });

        Schema::create('forum_posts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('forum_topic_id')->constrained()->cascadeOnDelete();
            // constrained() без аргумента выводит имя таблицы из имени
            // колонки: author_id → «authors», такой таблицы нет.
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();

            $table->text('body');

            // Ответ преподавателя помечается отдельно от «решения»:
            // решить тему можно и без флага, а вот «это ответил
            // преподаватель» — полезная подсказка для читателя.
            $table->boolean('from_staff')->default(false);

            $table->timestamps();

            $table->index('forum_topic_id');
            $table->index('author_id');
        });

        // Внешний ключ на solution_post_id добавляется отдельно: колонка
        // объявлена в forum_topics до того, как существует forum_posts.
        Schema::table('forum_topics', function (Blueprint $table) {
            $table->foreign('solution_post_id')
                ->references('id')
                ->on('forum_posts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_posts');
        Schema::dropIfExists('forum_topics');
    }
};