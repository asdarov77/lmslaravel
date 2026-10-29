<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P1 (аудит производительности): индексы под горячие пути плеера.
 *
 * Что проверяет каждый запрос кадра:
 *  - CourseAccess::allowedModuleIds → group2learnings по (group_id, course_id)
 *    с фильтром category_id / выборкой parent_id. Без составного индекса
 *    это seq scan всей таблицы записей на КАЖДЫЙ GET /content и /raw
 *    (а /raw — десятки запросов на один скан-документ).
 *  - CourseAccess::withDescendants → aukstructures по course_id с
 *    (id, parent_id). Частичный индекс whereNotNull(parent_id) покрывает
 *    запрос index-only scan'ом.
 *  - hasSpecialtyAccess → aukstructure_category по aukstructure_id
 *    (в исходной миграции таблицы индексов на FK-колонку нет).
 *  - CoursePlayerService::payload → course_material_views по
 *    (user_id, course_id). Отдельные индексы на user_id и course_id
 *    planner объединяет bitmap-ом хуже, чем один составной.
 *
 * Индексы создаются CONCURRENTLY (PostgreSQL), чтобы не блокировать
 * записи на время построения; в тестах (sqlite) fallback обычный.
 */
return new class extends Migration
{
    /** @var array<int,array{table:string,name:string,columns:array,prefix?:bool}> */
    private array $indexes = [
        ['table' => 'group2learnings', 'name' => 'g2l_group_course_idx', 'columns' => ['group_id', 'course_id']],
        ['table' => 'aukstructures', 'name' => 'auk_course_parent_idx', 'columns' => ['course_id', 'parent_id'], 'prefix' => true],
        ['table' => 'aukstructure_category', 'name' => 'auc_aukstructure_idx', 'columns' => ['aukstructure_id']],
        ['table' => 'category_course', 'name' => 'cc_course_idx', 'columns' => ['course_id']],
        ['table' => 'course_material_views', 'name' => 'cmv_user_course_idx', 'columns' => ['user_id', 'course_id']],
    ];

    /**
     * PostgreSQL не выполняет CREATE INDEX ... CONCURRENTLY внутри
     * транзакции, а Laravel 11 оборачивает каждую миграцию в транзакцию.
     * Отключаем её точечно (supportsTemporary / disableTransaction).
     */
    public function supportsSchemaTransactions(): bool
    {
        return false;
    }

    public function up(): void
    {
        $pgsql = Schema::getConnection()->getDriverName() === 'pgsql';

        foreach ($this->indexes as $index) {
            if (! Schema::hasTable($index['table'])) {
                continue;
            }

            $cols = implode(', ', array_map(
                fn ($c) => '"'.$c.'"',
                $index['columns']
            ));

            // CONCURRENTLY только на pgsql: он не берёт lock на запись
            // на время построения. На sqlite (тесты) — обычный индекс.
            $concurrently = $pgsql ? 'CONCURRENTLY' : '';

            $this->createIndexQuietly($index['table'], $index['name'], $cols, $concurrently);
        }
    }

    private function createIndexQuietly(string $table, string $name, string $cols, string $concurrently): void
    {
        try {
            DB::statement(
                "CREATE {$concurrently} INDEX IF NOT EXISTS \"{$name}\" ON \"{$table}\" ({$cols})"
            );
        } catch (\Throwable $e) {
            // Индекс уже есть (создан ранее вручную) или таблица залочена —
            // деградация до seq scan предпочтительнее упавшей миграции.
            \Illuminate\Support\Facades\Log::warning("Index {$name} not created: ".$e->getMessage());
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $index) {
            if (! Schema::hasTable($index['table'])) {
                continue;
            }

            try {
                DB::statement('DROP INDEX IF EXISTS "'.$index['name'].'"');
            } catch (\Throwable) {
                // ignore
            }
        }
    }
};
