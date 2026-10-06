<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Фрагменты материала.
 * 
 * Модель работает «не знает тему — переформулирует вопрос из фрагмента»,
 * поэтому фрагмент это единица работы: и отбора релевантности, и
 * генерации, и back-check. Хранить надо именно фрагменты, а не ссылку на
 * файл: файл могут перезалить, а вопросы уже сгенерированы и ссылаются
 * на конкретный текст.
 * 
 * embedding — JSONB, а не векторный тип: pgvector на сервере нет
 * (проверено: extension недоступна). Вектор сохраняется, когда есть
 * модель эмбеддингов, но поиск по нему выполняется только если
 * установлено расширение. Основной путь отбора — tsvector ниже, он
 * работает везде.
 * 
 * seq — порядок фрагмента в материале. Он же даёт «нарастающий контекст»
 * в сессии: чанки не перемешиваются, а идут след за следом.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutor_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('tutor_materials')->cascadeOnDelete();
            $table->unsignedInteger('seq');

            $table->text('content');
            $table->unsignedInteger('token_count')->default(0);

            // NULL, пока эмбеддингов нет: отсутствие вектора — штатное
            // состояние, а не «данные потеряны».
            $table->jsonb('embedding')->nullable();

            // Полнотекстовый индекс для отбора релевантного фрагмента.
            // english + русский: конфигурация textsearch со словарём
            // simple, который не падает на неизвестных словах (в
            // материалах полно технических терминов, которых нет в
            // стандартных словарях).
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->unique(['material_id', 'seq']);
            $table->index('material_id');

            // GIN-индексы строим отдельным вызовом: в Blueprint их
            // нельзя описать типом (для Postgres нужен USING GIN).
        });

        DB::statement(
            'CREATE INDEX tutor_chunks_search_idx ON tutor_chunks
             USING GIN (to_tsvector(\'simple\', content))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_chunks');
    }
};
