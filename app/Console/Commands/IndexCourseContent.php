<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Support\LessonSearchIndexer;
use Illuminate\Console\Command;

/**
 * courses:index-content — одноразовое (или повторное) наполнение
 * lesson_search_index для всех курсов. Индексатор самоневалидирующийся:
 * повторный прогон обновляет тексты и выкидывает исчезнувшие файлы.
 */
class IndexCourseContent extends Command
{
    protected $signature = 'courses:index-content {--course= : id курса — проиндексировать только его}';
    protected $description = 'Наполнить поисковый индекс содержимым материалов курсов';

    public function handle(): int
    {
        $query = Course::query();

        if ($this->option('course')) {
            $query->where('id', (int) $this->option('course'));
        }

        $count = 0;
        foreach ($query->orderBy('id')->cursor() as $course) {
            $rows = LessonSearchIndexer::indexCourse($course);
            $count += $rows;
            $this->line("course {$course->id}: {$rows}");
        }

        $this->info("Индекс обновлён, строк: {$count}");

        return self::SUCCESS;
    }
}
