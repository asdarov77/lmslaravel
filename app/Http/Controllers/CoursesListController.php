<?php

namespace App\Http\Controllers;

use App\Models\Course;

use Illuminate\Http\Request;

class CoursesListController extends Controller
{
//   public function getCourses(Request $request)
    public function getCourses(Request $request)
    {
        // Раньше возвращались ВСЕ курсы, включая 15 placeholder от
        // CourseSeeder (course-1...course-15) без aircraft_id. Они
        // ломали отображение: course.aircraft был null, и URL контента
        // формировался с пустым сегментом (api/private//index.html).
        // Также без eager loading aircraft делал N+1 запрос.
        $query = Course::with('aircraft', 'categories')
            ->whereNotNull('aircraft_id');

        // Фильтры. Фронтенд шлёт их и раньше (Pages/Courses.vue ->
        // Course/fetchCoursesFilter), но контроллер их игнорировал: выбор
        // категории/самолёта в UI визуально менялся, а список оставался
        // прежним. Пустые строки и '0' (сброс фильтра) считаем отсутствием.
        foreach (['aircraft_id', 'category_id'] as $field) {
            $value = $request->input($field);

            if ($value === null || $value === '' || $value === '0' || $value === 0) {
                continue;
            }

            if (! is_numeric($value)) {
                return response()->json([
                    'success' => false,
                    'data'    => null,
                    'error'   => "Некорректное значение фильтра {$field}",
                    'meta'    => null,
                ], 422);
            }

            // Категория привязана к курсу через pivot category_course
            // (его заполняет импорт манифеста). Поле courses.category_id
            // при импорте не заполняется — остаётся null, поэтому фильтр
            // по нему всегда возвращал пустой список.
            if ($field === 'category_id') {
                $query->whereHas('categories', function ($categories) use ($value) {
                    $categories->where('categories.id', (int) $value);
                });

                continue;
            }

            $query->where($field, (int) $value);
        }

        return $query->get();
    }
}
