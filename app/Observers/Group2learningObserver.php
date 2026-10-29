<?php

namespace App\Observers;

use App\Models\Group2learning;
use App\Support\CourseAccess;

/**
 * Инвалидация кэша доступа к модулям курса (П1 аудита).
 *
 * CourseAccess::allowedModuleIds кэширует результат на 5 минут по ключу
 * (group_id, course_id). Любое изменение записи группы на курс должно
 * сбрасывать кэш немедленно: иначе студент, только что записанный на
 * курс, будет видеть 403 до конца TTL, а снятый с курса — иметь доступ
 * к материалам ещё несколько минут (дыра безопасности, а не только
 * свежесть данных).
 *
 * Сброс идёт по course_id (все группы курса): дешёвый pattern-delete,
 * точечный по группе потребовал бы хранить индекс ключей.
 */
class Group2learningObserver
{
    public function saved(Group2learning $learning): void
    {
        if ($learning->course_id !== null) {
            CourseAccess::forgetCourseCache((int) $learning->course_id);
        }
    }

    public function deleted(Group2learning $learning): void
    {
        if ($learning->course_id !== null) {
            CourseAccess::forgetCourseCache((int) $learning->course_id);
        }
    }
}
