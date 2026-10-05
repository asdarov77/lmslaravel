<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Политика экзаменов: «управлять» и «сдавать» — разные capability.
 *
 * Правила существовали как приватные методы ExamController
 * (manages/assignedTo) и были корректны, но оставались недоступны для
 * Gate, @can и вне контроллера. Здесь то же правило описано один раз:
 *
 *  - управлять экзаменами может exams.manage (или суперадминистратор);
 *  - сдавать может тот, кому экзамен назначен лично (user_id) или
 *    через группу (group_id), при наличии права exams.take;
 *  - экзамен без адресата (ни user_id, ни group_id) недоступен никому,
 *    кроме управляющего: иначе это просто общий набор вопросов.
 */
class ExamPolicy
{
    use HandlesAuthorization;

    /** Управление экзаменами: создание, редактирование, удаление. */
    public function manage(User $actor, ?Exam $exam = null): bool
    {
        return $actor->isSuperAdmin() || $actor->hasPermission('exams.manage');
    }

    /** Сдача экзамена: право есть И экзамен назначен актору. */
    public function take(User $actor, Exam $exam): bool
    {
        if ($this->manage($actor, $exam)) {
            return true;
        }

        if (! $actor->hasPermission('exams.take')) {
            return false;
        }

        return $this->assignedTo($exam, $actor);
    }

    /** Просмотр карточки экзамена в списке. */
    public function view(User $actor, Exam $exam): bool
    {
        return $this->manage($actor, $exam)
            || ($actor->hasPermission('exams.take') && $this->assignedTo($exam, $actor));
    }

    public function create(User $actor): bool
    {
        return $this->manage($actor);
    }

    public function delete(User $actor, Exam $exam): bool
    {
        return $this->manage($actor, $exam);
    }

    /**
     * Назначен ли экзамен актору: лично, через группу, либо адресатов
     * нет и это экзамен «в общий доступ» — но тогда его видит только
     * управляющий (см. take).
     */
    private function assignedTo(Exam $exam, ?User $actor): bool
    {
        if ($actor === null) {
            return false;
        }

        if ($exam->user_id !== null) {
            return (int) $exam->user_id === (int) $actor->id;
        }

        if ($exam->group_id !== null) {
            return $actor->group_id !== null
                && (int) $exam->group_id === (int) $actor->group_id;
        }

        return false;
    }
}
