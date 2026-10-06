<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * POST /api/clear-database — очистка таблиц контента.
 *
 * DELETE выполняется в порядке, ОБРАТНОМ цепочкам внешних ключей, в одной
 * транзакции. Пользователей, группы, роли и права не трогает: чистится
 * содержимое обучения, а не настройки доступа.
 *
 * Требует system.maintenance — одно из двух прав, которые инструктору выдать
 * нельзя (см. PermissionScope::ADMIN_ONLY_SLUGS).
 */
class ClearDBController extends Controller
{
    /**
     * Таблицы контента, которые чистит кнопка «Очистить базу данных».
     *
     * Порядок выведен из information_schema: сначала дочерние таблицы,
     * потом родительские. Цепочки внешних ключей в этой схеме:
     *
     *   test_results -> answers -> questions -> categories
     *   links -> aukstructures
     *   aukstructure_category -> aukstructures, categories
     *   category_course -> categories, courses
     *   course_students / course_instructors -> courses
     *   courses -> categories
     *
     * Раньше courses/categories удалялись в середине списка, пока
     * questions/answers/test_results ещё ссылались на них: очистка падала
     * с «violates foreign key constraint», и кнопка выглядела нерабочей.
     * Удалять в обратном порядке нельзя — те же нарушения.
     */
    private const CONTENT_TABLES = [
        'test_results',
        'answers',
        'questions',
        'links',
        'aukstructure_category',
        'group2learnings',
        'aukstructures',
        'category_course',
        'course_students',
        'course_instructors',
        'courses',
        'categories',
        'aircrafts',
    ];

    /**
     * Полная очистка содержимого (курсы, категории, структура АУК, вопросы).
     *
     * Пользователи, группы, роли и права НЕ трогаются: без них нельзя
     * зайти в систему и нельзя повторно импортировать контент.
     */
    public function clear(): JsonResponse
    {
        $cleared = [];

        // TRUNCATE в PostgreSQL, в отличие от MySQL, участвует в
        // транзакции, но сбрасывает последовательности (RESTART IDENTITY
        // недоступен для DELETE) и требует прав на все таблицы сразу.
        // Поэтому чистим через DELETE в правильном порядке (см.
        // CONTENT_TABLES) внутри одной транзакции: при ошибке на любой
        // таблице ничего не теряется, а id остаются сквозными.
        $tables = array_values(array_filter(
            self::CONTENT_TABLES,
            static fn (string $table): bool => Schema::hasTable($table)
        ));

        $missing = array_values(array_diff(self::CONTENT_TABLES, $tables));

        try {
            DB::transaction(static function () use ($tables, &$cleared): void {
                foreach ($tables as $table) {
                    $before = DB::table($table)->count();
                    DB::table($table)->delete();
                    $cleared[$table] = $before;
                }
            });
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'data' => null,
                'error' => 'Не удалось очистить базу данных: ' . $e->getMessage(),
                'meta' => ['tables' => $tables, 'missing' => $missing],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message' => 'Database cleared successfully',
                'deleted' => $cleared,
            ],
            'error' => null,
            'meta' => [
                'tables' => $tables,
                'missing' => $missing,
            ],
        ]);
    }
}