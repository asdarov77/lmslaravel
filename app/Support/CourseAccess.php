<?php

namespace App\Support;

use App\Models\Course;
use App\Models\Group2learning;
use App\Models\User;

/**
 * Доступ к СОДЕРЖИМОМУ курса.
 *
 * Отдельно от CourseVisibility, который отвечает на вопрос «какие курсы
 * видны в списке». Здесь — «открывается ли материал курса».
 *
 * Почему пришлось вводить при появлении витрины и самостоятельной
 * записи. До этого материал курса читался по факту входа: страница
 * /courses/desc/:id и манифест грузились любому авторизованному, кто
 * знал идентификатор. Как только каталог начал показывать все курсы и
 * позволил записаться самому, тот же обход стал читать материал
 * НЕЗАПИСАННОГО курса: идентификаторы идут подряд, перебор занимает
 * секунды.
 *
 * Правило: материал открыт тому, кто на курс записан, либо тому, кто
 * курсами управляет.
 */
class CourseAccess
{
    /** Записан ли актор (через свою группу) на этот курс. */
    public static function isEnrolled(?User $actor, Course $course): bool
    {
        if ($actor === null || $actor->group_id === null) {
            return false;
        }

        return Group2learning::query()
            ->where('group_id', $actor->group_id)
            ->where('course_id', $course->id)
            ->exists();
    }

    /** Управляет ли актор курсами: ему материал доступен всегда. */
    public static function manages(?User $actor): bool
    {
        return $actor !== null
            && ($actor->isSuperAdmin()
                || $actor->hasPermission('courses.manage')
                || $actor->hasPermission('content.manage'));
    }

    /** Открывается ли материал курса этому пользователю. */
    public static function canOpen(?User $actor, Course $course): bool
    {
        return self::manages($actor) || self::isEnrolled($actor, $course);
    }

    /**
     * Завершение запроса с 403, если материал закрыт.
     *
     * Отдельный метод, чтобы контроллеры не собирали текст ошибки
     * каждый по-своему.
     */
    public static function authorizeOpen(?User $actor, Course $course): void
    {
        if (self::canOpen($actor, $course)) {
            return;
        }

        abort(403, 'Курс не открыт: запишитесь на него, чтобы увидеть материал');
    }
}