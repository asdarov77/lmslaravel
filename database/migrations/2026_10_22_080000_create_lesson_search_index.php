<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Индекс содержимого материалов для поиска (P1.1).
 *
 * plain_text — вычищенный текст документа; searchable — сгенерированный
 * столбец tsvector ('russian'): БД поддерживает его сама, обновляется
 * вместе с plain_text, отдельно индексатор ничего не дописывает.
 * GIN-индекс делает websearch_to_tsquery точечным вместо обхода ФС.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_search_index', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aukstructure_id')->constrained('aukstructures')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('file');
            $table->text('plain_text')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'file']);
        });

        // Столбец считается БД: any UPDATE plain_text пересчитывает tsvector.
        DB::statement(
            "ALTER TABLE lesson_search_index ADD COLUMN searchable tsvector
             GENERATED ALWAYS AS (to_tsvector('russian', coalesce(plain_text, ''))) STORED"
        );
        DB::statement(
            'CREATE INDEX lesson_search_index_searchable_gin ON lesson_search_index USING GIN (searchable)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_search_index');
    }
};
