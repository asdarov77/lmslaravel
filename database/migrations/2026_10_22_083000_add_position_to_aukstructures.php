<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * position для aukstructures (раздел 8: операция переупорядочивания).
 *
 * NULL = «без явной позиции»: порядок остаётся прежним (по id), поэтому
 * список восходит совместимо даже до первого reorder. Сортировка
 * везде через ORDER BY coalesce(position, id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aukstructures', function (Blueprint $table) {
            $table->unsignedInteger('position')->nullable()->after('type');
            $table->index(['course_id', 'position']);
        });

        // Существующие узлы упорядачиваются по id одну за другой — иначе
        // NULL-порядок смешается с позициями после первого reorder.
        DB::statement(
            'WITH ordered AS (
                SELECT id, row_number() OVER (PARTITION BY course_id ORDER BY id) rn
                FROM aukstructures
            )
            UPDATE aukstructures SET position = rn
            FROM ordered WHERE aukstructures.id = ordered.id'
        );
    }

    public function down(): void
    {
        Schema::table('aukstructures', function (Blueprint $table) {
            $table->dropIndex(['course_id', 'position']);
            $table->dropColumn('position');
        });
    }
};
