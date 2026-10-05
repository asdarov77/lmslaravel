<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Group2learning;
use App\Models\User;
use App\Support\CourseVisibility;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Политика курсов: «видеть» и «управлять» — разные capabilities.
 *
 * Разделение воспроизводит замысел CourseVisibility, который иначе
 * жил только в контроллере и в одном запросе:
 *
 *  - обучаемый видит ровно те курсы, на которые записана его группа
 *    (плюс право courses.view), поэтому «открыть курс по прямой
 *    ссылке /courses/itemmani?idEdit=...» должен быть ограничен тем же
 *    правилом, а не фактом входа;
 *  - тот, кто курсами управляет (courses.manage), видит весь каталог.
 *
 * Здесь не изобретается новое правило, а переносится уже принятое в
 * одно место, чтобы контроллер, маршрут и тест говорили об одном и том
 * же.
 */
class CoursePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('courses.view')
            || $actor->hasPermission('courses.manage');
    }

    /**
     * Открыть курс.
     *
     * Управляющим курсами всё доступно. Остальным — только то, что
     * видно по области (CourseVisibility): назначенное группе.
     */
    public function view(User $actor, Course $course): bool
    {
        if ($actor->isSuperAdmin() || $actor->hasPermission('courses.manage')) {
            return true;
        }

        if (! $actor->hasPermission('courses.view')) {
            return false;
        }

        return $this->isEnrolled($actor, $course);
    }

    /** Создание и редактирование курса. */
    public function update(User $actor, Course $course): bool
    {
        return $actor->hasPermission('courses.manage')
            || $actor->hasPermission('manage-course')
            || $actor->hasPermission('edit_courses');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('courses.manage')
            || $actor->hasPermission('manage-course')
            || $actor->hasPermission('edit_courses');
    }

    public function delete(User $actor, Course $course): bool
    {
        return $this->update($actor, $course);
    }

    /**
     * Публикация курса — отдельное действие, а не синоним manage:
     * черновик методиста не должен уезжать в каталог сам по себе.
     */
    public function publish(User $actor, Course $course): bool
    {
        return $actor->hasPermission('courses.publish');
    }

    /**
     * Записан ли актор на этот курс своей группой.
     *
     * Пользователь без группы не записан ни на один курс: пустой
     * результат честнее, чем весь каталог.
     */
    private function isEnrolled(User $actor, Course $course): bool
    {
        if ($actor->group_id === null) {
            return false;
        }

        return Group2learning::query()
            ->where('group_id', $actor->group_id)
            ->where('course_id', $course->id)
            ->exists();
    }
}
