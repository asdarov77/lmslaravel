<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\Question;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Политика банка вопросов.
 *
 * Здесь важно не «кто может открыть страницу», а что именно утекает в
 * браузер. Ответы на вопрос отдаются вместе с признаком правильности
 * (Answer::is_correct), поэтому чтение банка в том же виде, что и
 * проведение экзамена, — это публикация правильных ответов.
 *
 * Правила:
 *  - читать банк (questions.view) может методист, готовящий материалы;
 *  - создавать и править вопросы (questions.manage) — тоже;
 *  - обучающемуся банк недоступен: у него есть exams.take, но не
 *    questions.view, поэтому и /questions, и /api/questions для него
 *    закрыты. Проверка стоит и на маршруте, и в политике — правило не
 *    должно зависеть от того, каким путём пришёл запрос.
 */
class QuestionBankPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('questions.view')
            || $actor->hasPermission('questions.manage');
    }

    /** Чтение вопроса вместе с вариантами ответа. */
    public function view(User $actor, Question $question): bool
    {
        return $this->viewAny($actor);
    }

    /** Сводка по банку: сколько вопросов, где нет ответов, где нет верных. */
    public function statistics(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('questions.manage');
    }

    public function update(User $actor, Question $question): bool
    {
        return $actor->hasPermission('questions.manage');
    }

    public function delete(User $actor, Question $question): bool
    {
        return $actor->hasPermission('questions.manage');
    }

    /**
     * Специальность (категория) — часть банка: тот же набор прав.
     */
    public function manageCategory(User $actor, Category $category): bool
    {
        return $actor->hasPermission('questions.manage')
            || $actor->hasPermission('categories.manage');
    }
}
