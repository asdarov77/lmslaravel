<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Уроки (aukstructures) и связи курс ↔ категория.
 *
 * Зачем отдельный сидер: CourseSeeder создавал курсы без самолётов и
 * без уроков, а CategorySeeder — категории без связей. Схема это
 * допускает (все поля nullable), но интерфейс на таких данных пуст:
 *  - каталог группирует курсы по самолёту;
 *  - дерево курсов в «Записи групп на курсы» строится из уроков;
 *  - фильтр по категории ищет уроки, у которых categories содержит
 *    код категории.
 *
 * То есть «демо-база» была формально заполнена, а пользоваться ею
 * было нельзя. Здесь собирается связный набор: каждому курсу — два
 * урока, каждой категории — свой курс, а уроки помечаются её кодом.
 */
class CourseLessonsSeeder extends Seeder
{
    public function run(): void
    {
        $categories = DB::table('categories')->orderBy('id')->get();
        $courses = DB::table('courses')->orderBy('id')->get();

        if ($categories->isEmpty() || $courses->isEmpty()) {
            return;
        }

        /*
         * Категории привязываются к самолётам.
         *
         * Без aircraft_id список категорий в форме записи групп
         * оказывался пустым: он фильтруется по «категория этого
         * самолёта», а у всех категорий aircraft_id был NULL.
         */
        $aircraftIds = DB::table('aircrafts')->orderBy('id')->pluck('id')->all();

        if ($aircraftIds !== []) {
            foreach ($categories as $index => $category) {
                if ($category->aircraft_id !== null) {
                    continue;
                }

                DB::table('categories')
                    ->where('id', $category->id)
                    ->update(['aircraft_id' => $aircraftIds[$index % count($aircraftIds)]]);
            }

            $categories = DB::table('categories')->orderBy('id')->get()->toBase();
        }

        $rows = [];
        $pivot = [];
        $categoryIndex = 0;
        $courseIndex = 0;

        foreach ($courses as $course) {
            $category = $categories[$categoryIndex % $categories->count()];
            $categoryIndex++;

            // Курс живёт на самолёте своей категории: иначе в каталоге
            // он оказывался у другого самолёта, и фильтр «самолёт +
            // категория» не находил его.
            if ($category->aircraft_id !== null) {
                DB::table('courses')
                    ->where('id', $course->id)
                    ->update(['aircraft_id' => $category->aircraft_id]);
            }

            // Связь через pivot: каталог и фильтры ходят именно по ней,
            // а колонка courses.category_id остаётся для быстрых выборок.
            $pivot[] = [
                'category_id' => $category->id,
                'course_id' => $course->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            /*
             * Первый курс первого самолёта получает урок с материалом
             * (type 3) и ссылкой на реальный файл: каталог
             * материалов на диске один, и без привязки к нему страница
             * материала не открывается вовсе. Путь курса и имя файла
             * совпадают с реальными каталогами.
             */
            if ($courseIndex === 0 && $this->hasRealMaterial()) {
                /*
                 * Пять материальных разделов: проверки обращаются к
                 * первому, второму, третьему и четвёртому, поэтому
                 * запас в один раздел нужен — иначе тест упирается в
                 * ожидание несуществующего элемента.
                 */
                $rows[] = [
                    'course_id' => $course->id,
                    'parent_id' => null,
                    'title' => 'Материал курса',
                    // 3 — раздел с файлом; такие узлы открываются в манифесте.
                    'type' => 3,
                    'description' => '',
                    'categories' => $category->code,
                    'identifier' => 'MATERIAL-' . $course->id . '-1',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                foreach (range(2, 5) as $section) {
                    $rows[] = [
                        'course_id' => $course->id,
                        'parent_id' => null,
                        'title' => 'Материал курса, раздел ' . $section,
                        'type' => 3,
                        'description' => '',
                        'categories' => $category->code,
                        'identifier' => 'MATERIAL-' . $course->id . '-' . $section,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                /*
                 * Путь курса — это СЕГМЕНТ каталога, а не путь на диске:
                 * getlink строит private/<самолёт>/<курс>/<файл>. С
                 * абсолютным путём файл не находился вовсе.
                 */
                DB::table('courses')
                    ->where('id', $course->id)
                    ->update(['path' => $this->materialCourseSegment()]);
            }

            foreach ([1, 2] as $number) {
                $rows[] = [
                    'course_id' => $course->id,
                    'parent_id' => null,
                    'title' => 'Урок ' . $number . '. ' . $course->title,
                    // 1 — раздел с материалом, 2 — вложенный подраздел.
                    'type' => $number === 1 ? 1 : 2,
                    'description' => '',
                    // Код категории: по нему CourseFilter находит уроки
                    // нужной специальности.
                    'categories' => $category->code,
                    'identifier' => 'L-' . $course->id . '-' . $number,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('courses')->where('id', $course->id)->update([
                'category_id' => $category->id,
            ]);

            $courseIndex++;
        }

        // insertOrIgnore: сидер должен быть повторяемым, иначе второй
        // запуск падает на уникальном индексе identifier.
        /*
         * Идентификатор урока не уникален в схеме, поэтому
         * insertOrIgnore не спасает: каждый прогон добавлял ещё одну
         * пару материальных уроков, и в манифесте появлялись дубли.
         * Повторяемость обеспечиваем сами — удаляем свои же уроки перед
         * вставкой. Чужие уроки (identifier не MATERIAL-*) не трогаем.
         */
        DB::table('links')
            ->whereIn('aukstructure_id', function ($query) {
                $query->select('id')->from('aukstructures')->where('identifier', 'like', 'MATERIAL-%');
            })
            ->delete();

        DB::table('aukstructures')->where('identifier', 'like', 'MATERIAL-%')->delete();

        DB::table('aukstructures')->insert($rows);
        DB::table('category_course')->insertOrIgnore($pivot);

        /*
         * Ссылки на материалы ставим после вставки уроков: нужен их id.
         * Каждому разделу — свой файл, иначе переключение между
         * материалами не меняло бы ничего и проверялось бы вхолостую.
         */
        $materials = DB::table('aukstructures')
            ->where('identifier', 'like', 'MATERIAL-%')
            ->orderBy('identifier')
            ->get();

        foreach ($materials as $index => $material) {
            $file = $this->materialFile($index);

            if ($file === null) {
                continue;
            }

            DB::table('links')->updateOrInsert(
                ['aukstructure_id' => $material->id],
                ['link' => $file, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    /** Есть ли на диске каталог с материалами. */
    private function hasRealMaterial(): bool
    {
        return is_dir($this->materialCourseDir());
    }

    /** Каталог курса с материалами: private/<самолёт>/<курс>. */
    private function materialCourseDir(): string
    {
        return base_path('storage/app/courses/private/БПЛА/04');
    }

    /** Сегмент пути курса — последний элемент каталога. */
    private function materialCourseSegment(): string
    {
        return basename($this->materialCourseDir());
    }

    /**
     * HTML-файл материала по индексу.
     *
     * null, если файла нет: ссылка без файла открывала бы пустую
     * страницу с ошибкой, и это хуже, чем раздел вовсе без ссылки.
     */
    private function materialFile(int $index = 0): ?string
    {
        $files = glob($this->materialCourseDir() . '/*.html') ?: [];

        /*
         * Сначала самые крупные файлы.
         *
         * Проверки прокрутки открывают материал и ждут кнопку «вверх»:
         * у документа в несколько строк прокручивать нечего, и тест
         * падал бы на материале размером в 30 КБ, хотя сам интерфейс
         * был в порядке. Крупные документы (1+ МБ) дают реальную
         * прокрутку.
         */
        usort($files, fn ($a, $b) => filesize($b) <=> filesize($a));

        $file = $files[$index] ?? null;

        // ИМЯ файла, а не путь: getlink сам собирает
        // private/<самолёт>/<курс>/<файл>, и абсолютный путь здесь
        // приводил к поиску несуществующего файла.
        return $file === null ? null : basename($file);
    }
}