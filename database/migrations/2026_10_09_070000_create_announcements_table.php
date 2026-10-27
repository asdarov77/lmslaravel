<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Объявления для обучаемых и сотрудников.
 *
 * Аудитория задаётся набором флагов, а не ссылкой на одну таблицу:
 * объявление может быть для всех, для конкретной группы, курса или
 * роли — и это комбинация, а не «одна таблица одна колонка».
 * Отдельные таблицы-цели (announcement_groups и подобные) при таком
 * наборе размножились бы быстрее, чем давали пользу.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->text('body');

            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();

            // Аудитория: комбинация флагов. Пустая выборка флагов —
            // это «всем», а не «никому»: так объявление, созданное без
            // уточнения, не исчезает молча.
            $table->boolean('audience_all')->default(true);
            $table->json('audience_groups')->nullable();
            $table->json('audience_courses')->nullable();
            $table->json('audience_roles')->nullable();

            // Закреплённое показывается первым и не тонет в ленте.
            $table->boolean('pinned')->default(false);

            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['published_at', 'pinned']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};