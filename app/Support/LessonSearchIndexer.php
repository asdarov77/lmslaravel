<?php

namespace App\Support;

use App\Models\Aukstructure;
use App\Models\Course;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Наполнение lesson_search_index (P1.1).
 *
 * Обход «воздух/курс/файл» — тот же маршрут, что и у старого поиска,
 * только разово и в БД, а не на каждый запрос. Дубликаты (один файл
 * привязан к нескольким материалам Link'à) схлопываются по первому
 * материалу: результат поиска ребёнка и родителя был бы одинаковым.
 */
class LessonSearchIndexer
{
    /** Проиндексировать один курс: возвращает число строк. */
    public static function indexCourse(Course $course): int
    {
        if (! $course->aircraft) {
            return 0;
        }

        $disk = Storage::disk('public');
        $prefix = 'private/' . trim($course->aircraft->path) . '/' . trim($course->path) . '/';
        $indexed = 0;

        // Свежий текст знает именно диск: база не хранит содержимое файлов.
        $latest = [];
        foreach ($course->aukstructures()->with('links')->get() as $auk) {
            foreach ($auk->links as $link) {
                $path = $prefix . trim($link->link);
                if (! str_ends_with($path, '.html') || str_ends_with($path, 'index.html')
                    || ! $disk->exists($path)) {
                    continue;
                }
                $latest[$path] = [
                    'aukstructure_id' => $auk->id,
                    'course_id' => $course->id,
                    'file' => trim($link->link),
                    'plain_text' => self::plainText((string) $disk->get($path)),
                ];
                break; // один файл — одна строка индекса
            }
        }

        $existing = DB::table('lesson_search_index')
            ->where('course_id', $course->id)
            ->pluck('file')->all();

        foreach ($latest as $path => $row) {
            $updated = [
                'aukstructure_id' => $row['aukstructure_id'],
                'plain_text' => $row['plain_text'],
                'updated_at' => now(),
            ];

            // file НЕ содержит уникальности в схеме; upsert по паре (course, file).
            $exists = in_array($row['file'], $existing, true);
            if ($exists) {
                DB::table('lesson_search_index')
                    ->where('course_id', $course->id)
                    ->where('file', $row['file'])
                    ->update($updated);
            } else {
                DB::table('lesson_search_index')->insert($row + ['created_at' => now(), 'updated_at' => now()]);
            }
        }

        // Файлы, исчезнувшие с диска, из индекса выпадают: при пустом
        // списке whereNotIn([]) совпадает со всеми — курс чистится целиком.
        $keep = array_column($latest, 'file');
        DB::table('lesson_search_index')
            ->where('course_id', $course->id)
            ->whereNotIn('file', $keep)
            ->delete();

        return count($latest);
    }

    /** Текст документа без тегов и скриптов: tsvector считает слова, а не разметку. */
    public static function plainText(string $html): string
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html);
        $text = trim(strip_tags((string) $html));
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]+/u', ' ', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return mb_substr($text, 0, 1000000);
    }
}
