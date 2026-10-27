<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Сгенерированные вопросы.
 * 
 * Вопросы генерируются пачками и хранятся, а не создаются по клику:
 * на CPU генерация занимает секунды-десятки секунд, и клик, ждущий
 * модель, недопустим. Запас вопросов делается заранее, пользователь
 * получает их мгновенно.
 * 
 * options JSONB — массив вариантов для mcq. В БАЗУ не кладётся признак
 * правильности (как и is_correct в Question): он хранится в
 * reference_answer и отдаётся пользователю только по запросу «показать
 * эталон», поэтому угадать правильный вариант из ответа API нельзя.
 * 
 * reference_answer и source_quote обязательны по смыслу, а не по
 * NOT NULL: без эталона и цитаты вопрос нельзя ни проверить, ни
 * обосновать, и он не должен был попасть в таблицу. Пустые значения
 * остаются возможными — валидация на этапе генерации, а не здесь:
 * один битый вопрос не должен ронять всю пачку.
 * 
 * asked_count — счётчик показов. Нужен для требования «вопросы каждый раз
 * новые»: при исчерпании чанка вопрос помечается изношенным и не
 * предлагается снова, пока не наберётся новых.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutor_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('tutor_sessions')->cascadeOnDelete();
            $table->foreignId('chunk_id')->constrained('tutor_chunks')->cascadeOnDelete();

            // mcq | short | open
            $table->string('qtype', 10);

            $table->text('question');

            // Варианты для mcq: ["...", "..."]. Без признака правильного.
            $table->jsonb('options')->nullable();

            $table->text('reference_answer')->nullable();
            $table->text('source_quote')->nullable();

            // Хеш нормализованного текста: защита от дублей ещё до того,
            // как вопрос показан. Считается в приложении, колонка —
            // только индекс.
            $table->string('fingerprint', 64)->nullable();

            $table->unsignedInteger('asked_count')->default(0);
            $table->unsignedInteger('backcheck_passed')->nullable();

            $table->timestamps();

            $table->index(['session_id', 'asked_count']);
            $table->index('chunk_id');
            $table->index('fingerprint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_items');
    }
};
