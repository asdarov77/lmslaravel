<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * Колонка avatar: json → string.
 *
 * Изначально она была json с комментарием «filename path», то есть
 * объявлена не тем типом, который в неё кладут. Загрузка аватара
 * падала с ошибкой Postgres «неверный синтаксис для типа json»: путь
 * вида avatars/3/abc.png в json не является.
 *
 * Меняем тип на string: хранится ровно один путь, а не структура.
 * Приведение нужно только для существующих строк, где значение могло
 * быть json-строкой — такие переводим в «[1,2,3]» → '1,2,3'.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Строки, где avatar уже заполнен: json-значение достаём текстом.
        $rows = DB::table('users')
            ->whereNotNull('avatar')
            ->get(['id', 'avatar']);

        DB::statement('ALTER TABLE users ALTER COLUMN avatar TYPE varchar(512) USING avatar::text');

        foreach ($rows as $row) {
            $value = trim((string) $row->avatar);

            // '[1,2,3]' → '1,2,3': json-массив приводим к тексту,
            // дальше это просто нечитаемый остаток, но не ошибка.
            if ($value !== '' && ($value[0] ?? '') === '[') {
                $decoded = json_decode($value, true);

                if (is_array($decoded)) {
                    $value = implode(',', array_map('strval', $decoded));
                }
            }

            DB::table('users')->where('id', $row->id)->update(['avatar' => $value]);
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users ALTER COLUMN avatar TYPE json USING avatar::json');
    }
};