<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ответы пользователя в тренажёре.
 * 
 * Отдельная таблица, а не колонка в items: вопрос задаётся много раз
 * (пока не износится), и без отдельных строк нельзя посчитать ни
 * историю попыток, ни «сколько раз и с каким результатом», ни динамику
 * по конкретному вопросу.
 * 
 * verdict — оценка: correct | partial | wrong | ungraded.
 * 'ungraded' означает, что оценка не выставлялась: свободный ответ без
 * эталона или движок недоступен. Считать его как wrong нельзя — иначе
 * в статистике неотвеченное выглядит как незнание.
 * 
 * auto_score — оценка открытого ответа по эталону, 0..1. Для mcq и
 * короткого ответа проверка детерминированная (сравнение), и оценка
 * ставится сервером без модели.
 * 
 * Отдельного user_id здесь нет намеренно: он есть в сессии, а
 * session_id уже есть в items. Дублировать колонку — значит получить
 * два источника правды о том, чей это ответ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutor_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('tutor_items')->cascadeOnDelete();

            // correct | partial | wrong | ungraded
            $table->string('verdict', 10)->default('ungraded');

            $table->text('answer')->nullable();
            $table->text('feedback')->nullable();
            $table->float('auto_score')->nullable();

            // Сколько секунд думал: полезно для «вопрос слишком сложный».
            $table->unsignedInteger('seconds_spent')->nullable();

            $table->jsonb('llm_meta')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'created_at']);
            $table->index('verdict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_responses');
    }
};
