<?php

namespace App\Support;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Область видимости курсов.
 *
 * Зачем: каталог курсов отдавался всем авторизованным одинаково, поэтому
 * обучаемый видел ВСЕ специальности и все курсы, даже те, на которые его
 * группу не записывали. Для учебного процесса это неверно: обучаемый должен
 * видеть ровно то, что ему назначено, плюс каталог остаётся полным у тех,
 * кто курсами управляет (методист/администратор).
 *
 * Область видимости — это разновидность проверки прав: в Moodle/Canvas
 * «может видеть» и «может изменять» тоже разные capabilities.
 */
class CourseVisibility
{
    /**
     * Ограничивает выборку курсов подписками группы актора.
     *
     * Возвращает true, если ограничение применено, — вызывающий код может
     * использовать это для логов/метрик.
     */
    public static function restrictToEnrolled(Builder $query, ?User $actor): bool
    {
        // Без актора (например, artisan-команда) ограничивать нечем:
        // это не пользовательский запрос, и «показать всё» безопасно.
        if ($actor === null) {
            return false;
        }

        // Кто управляет курсами, тот видит весь каталог: без этого
        // администратор не смог бы увидеть курс, который ещё никому не
        // назначен, и не смог бы его отредактировать или удалить.
        if ($actor->isSuperAdmin() || $actor->hasPermission('courses.manage')) {
            return false;
        }

        $groupId = $actor->group_id;

        // Пользователь без группы не записан ни на один курс.
        // Пустой результат честнее, чем весь каталог: показывать
        // обучаемому без группы весь учебный материал нельзя.
        $query->whereIn('courses.id', function ($sub) use ($groupId) {
            $sub->select('course_id')
                ->from('group2learnings')
                ->where('group_id', $groupId);
        });

        return true;
    }

    /**
     * Ограничивает список категорий (специальностей) теми, в которые
     * попадают курсы актора.
     *
     * Обучаемому не нужны все 13 специальностей профиля — только те, где
     * у него есть назначенный курс. Для методиста справочник остаётся
     * полным.
     */
    public static function restrictCategoriesToEnrolled(Builder $query, ?User $actor): bool
    {
        if ($actor === null) {
            return false;
        }

        if ($actor->isSuperAdmin() || $actor->hasPermission('categories.manage') || $actor->hasPermission('courses.manage')) {
            return false;
        }

        $query->whereExists(function ($sub) use ($actor) {
            $sub->selectRaw('1')
                ->from('category_course')
                ->join('group2learnings', 'group2learnings.course_id', '=', 'category_course.course_id')
                ->whereColumn('category_course.category_id', 'categories.id')
                ->where('group2learnings.group_id', $actor->group_id);
        });

        return true;
    }

    /**
     * Курсы, назначенные группе актора, как Eloquent-запрос.
     * Используется страницей учебного плана и дашбордом.
     */
    public static function enrolledQuery(?User $actor): Builder
    {
        $query = Course::with(['aircraft', 'categories']);

        if ($actor !== null && ! $actor->isSuperAdmin() && ! $actor->hasPermission('courses.manage')) {
            self::restrictToEnrolled($query, $actor);
        }

        return $query;
    }

    /**
     * Идентификаторы курсов, видимых актору.
     *
     * Нужен там, где скоуп требуется подставить в ЧУЖОЙ запрос — например
     * поиск по темам (aukstructures) ограничивается курсами, а сам
     * restrictToEnrolled() умеет править только запрос по courses.
     * Пустой результат означает «не виден ни один курс»: вызывающий
     * обязан трактовать это как пустую выдачу, а не как «показать всё».
     *
     * @return array<int,int>
     */
    public static function visibleCourseIds(?User $actor): array
    {
        $query = Course::query()->select('courses.id');

        if ($actor !== null) {
            self::restrictToEnrolled($query, $actor);
        }

        return $query->pluck('courses.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
