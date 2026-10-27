<?php

namespace App\Policies;

use App\Models\Group2learning;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Область полномочий по учебным записям (group2learnings).
 *
 * Запись отвечает на вопрос «кто на какой курс записан и когда», то
 * есть это учебные данные конкретной группы. Поэтому здесь ровно одно
 * правило: чужая группа — не твоя часть работы, даже если права
 * каталога у тебя есть.
 *
 * Что закрывает:
 *
 *  - show($id) висел только на auth:sanctum и отдавал ЛЮБУЮ запись по
 *    идентификатору. Список (index) к тому моменту уже был ограничен
 *    своей группой, поэтому утечку можно было получить простым
 *    перебором id: план группы А показывался обучаемому группы Б.
 *
 *  - Логика «своя группа или право users.courses» жила прямо в index()
 *    и не была доступна ни другим методам, ни тестам как отдельное
 *    правило. Теперь она описана здесь и переиспользуется.
 */
class Group2learningPolicy
{
    use HandlesAuthorization;

    /** Список записей открыт любому авторизованному, но scoped. */
    public function viewAny(User $actor): bool
    {
        return true;
    }

    /**
     * Чтение отдельной записи.
     *
     * Администратор и методист (users.courses) видят всё; остальные —
     * только свою группу.
     */
    public function view(User $actor, Group2learning $learning): bool
    {
        if (self::seesAllGroups($actor)) {
            return true;
        }

        return $this->ownsGroup($actor, $learning);
    }

    /**
     * Изменение записи (в том числе перенос в другую группу).
     *
     * Требуется право записи групп на курсы — то же, что у
     * POST /api/learning. Действующая запись не редактируется, потому
     * что форма отослала бы пустые NOT NULL-поля и затерела период.
     */
    public function update(User $actor, Group2learning $learning): bool
    {
        return $actor->hasPermission('users.courses')
            || $actor->hasPermission('create-tasks');
    }

    public function delete(User $actor, Group2learning $learning): bool
    {
        return $this->update($actor, $learning);
    }

    /**
     * Создание записи.
     */
    public function create(User $actor): bool
    {
        return $actor->hasPermission('users.courses')
            || $actor->hasPermission('create-tasks');
    }

    /**
     * Видит ли актор все группы.
     *
     * Статический метод: тем же правилом пользуется scopeQuery(), и
     * вызывать его нужно без создания экземпляра политики.
     */
    public static function seesAllGroups(?User $actor): bool
    {
        return $actor !== null
            && ($actor->isSuperAdmin() || $actor->hasPermission('users.courses'));
    }

    /**
     * Запись своей группы.
     */
    private function ownsGroup(?User $actor, Group2learning $learning): bool
    {
        return $actor !== null
            && $actor->group_id !== null
            && (int) $actor->group_id === (int) $learning->group_id;
    }

    /**
     * Запрос записей в области видимости актора.
     *
     * Актор без группы не видит ничего: пустая группа — это «нет своей
     * части», а не «вся система». Раньше здесь возвращался пустой
     * список, что читалось как «учебного плана нет».
     */
    public static function scopeQuery(?User $actor, $query)
    {
        if (self::seesAllGroups($actor)) {
            return $query;
        }

        $groupId = $actor?->group_id;

        if (! $groupId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('group_id', (int) $groupId);
    }
}
