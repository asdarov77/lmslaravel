<?php

namespace App\Policies;

use App\Models\User;
use App\Support\PermissionScope;
use Illuminate\Auth\Access\HandlesAuthorization;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Область полномочий по пользователям: своя запись против любой.
 *
 * Зачем политика. До неё решения принимались в четырёх разных местах и
 * ни одно не было полным:
 *
 *  1) getUserList() определял область видимости сравнением строк
 *     `Auth::user()->role == "Администратор"`. Роль, назначенная через
 *     role_user, в этой колонке не отражается, поэтому такой
 *     администратор видел только свою группу. Раньше это читалось как
 *     «списка пользователей нет», а не как ошибка прав.
 *
 *  2) getUser($id) отдавал ЛЮБОГО пользователя по идентификатору тому,
 *     у кого есть users.view. Инструктор группы А открывал карточку
 *     сотрудника группы Б по угаданному id — это кросс-групповая
 *     утечка профилей.
 *
 *  3) destroy() защищал ровно одного пользователя магическим числом
 *     `$id != 1`. Любой другой администратор удалялся обычным
 *     users.delete, удалить самого себя тоже можно было, а отказ
 *     возвращал 500 вместо 403.
 *
 *  4) chpass() содержал собственную проверку «свой пароль — можно,
 *     чужой — users.update» мимо каталога прав и области видимости:
 *     инструктор с правом на свою группу менял бы пароль в чужой.
 *
 * Правило общее: администратор — всё; остальные работают в пределах
 * своей группы и не трогают администраторов. Свою запись доступна всем.
 */
class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Список пользователей доступен любому авторизованному: сама выдача
     * ограничена группой в getUserList(), middleware users.view здесь
     * был мёртвым кодом — обучаемый не мог посмотреть состав своей
     * группы. Политика фиксирует именно этот замысел: список открыт,
     * область видимости решает контроллер.
     */
    public function viewAny(User $actor): bool
    {
        return true;
    }

    /**
     * Чтение карточки: своя запись — всегда, чужая — по правилам
     * ниже.
     */
    public function view(User $actor, User $target): bool
    {
        return $actor->is($target) || $this->withinScope($actor, $target, ['users.view', 'users.update']);
    }

    /** Изменение профиля. */
    public function update(User $actor, User $target): bool
    {
        return $this->mayTouchWithinScope($actor, $target);
    }

    /**
     * Удаление.
     *
     * Отдельно от update: удалять себя нельзя (иначе можно закрыть себе
     * единственный вход в систему), а чужого администратора — только
     * суперадминистратору.
     */
    public function delete(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        if (! $actor->hasPermission('users.delete')) {
            return false;
        }

        if ($target->isAdmin()) {
            return $actor->isSuperAdmin();
        }

        return $this->sharesGroup($actor, $target);
    }

    /**
     * Смена пароля: свой — всегда, чужой — как и изменение профиля.
     */
    public function changePassword(User $actor, User $target): bool
    {
        return $actor->is($target) || $this->mayTouchWithinScope($actor, $target);
    }

    /**
     * Назначение прав. Логика области уже вынесена в PermissionScope,
     * потому что её использует и страница управления правами: страница
     * и API обязаны решать одинаково, иначе одно покажет «недоступно»,
     * а другое выполнит.
     */
    public function managePermissions(User $actor, User $target): bool
    {
        return PermissionScope::canManageUser($actor, $target);
    }

    /**
     * Назначение роли (chroll).
     *
     * Право — users.permissions, то же, что у управления правами. Свою
     * роль изменить нельзя: иначе инструктор, которому открыли раздел
     * прав, повысил бы себя до администратора.
     */
    public function assignRole(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        if (! $actor->hasPermission('users.permissions')) {
            return false;
        }

        if ($target->isAdmin()) {
            return $actor->isSuperAdmin();
        }

        return $this->sharesGroup($actor, $target);
    }

    /**
     * Может ли актор менять запись в пределах своей области.
     */
    private function mayTouchWithinScope(User $actor, User $target): bool
    {
        return $this->withinScope($actor, $target, ['users.update']);
    }

    /**
     * Запись в области видимости при наличии одного из прав.
     *
     * Чтение и изменение различаются списком прав: инструктор имеет
     * users.view, но не users.update — он видит состав своей группы и не
     * может его править. Оба действия ограничены областью одинаково.
     *
     * @param  array<int,string>  $anyOf
     */
    private function withinScope(User $actor, User $target, array $anyOf): bool
    {
        // Супер-администратор проходит всё. Проверка идёт ДО правила
        // группы: у суперадмина group_id, как правило, null, и без этой
        // строки он был бы заперт в «своей части» размером в ноль —
        // то есть не смог бы править ни одной записи.
        if ($actor->isSuperAdmin()) {
            return true;
        }

        $hasRight = false;

        foreach ($anyOf as $slug) {
            if ($actor->hasPermission($slug)) {
                $hasRight = true;
                break;
            }
        }

        if (! $hasRight) {
            return false;
        }

        if ($target->isAdmin()) {
            return false;
        }

        return $this->sharesGroup($actor, $target);
    }

    /**
     * Записи в своей группе.
     *
     * Актор без группы (например, созданный до появления групп) не
     * должен получать доступ ко всем подряд: пустая группа — это «нет
     * своей части», а не «любая часть».
     */
    private function sharesGroup(User $actor, User $target): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        return $actor->group_id !== null
            && (int) $actor->group_id === (int) $target->group_id;
    }

    /**
     * Запрос списка пользователей в области видимости актора.
     *
     * Правило ответа устаревшего контроллера было сравнением строк, а
     * должно быть здесь: администратор видит всех, остальные — свою
     * группу. Актор без группы не видит никого: пустая группа — это
     * «нет своей части», а не «вся система».
     */
    public static function scopeQuery(?User $actor)
    {
        $query = User::orderBy('id');

        if ($actor === null || ! $actor->isSuperAdmin()) {
            $query->where(function ($q) use ($actor) {
                $q->whereNull('group_id');

                if ($actor !== null && $actor->group_id !== null) {
                    $q->orWhere('group_id', $actor->group_id);
                }

                // Своя запись доступна всегда, даже если группа не
                // проставлена.
                if ($actor !== null) {
                    $q->orWhere('id', $actor->id);
                }
            });
        }

        return $query;
    }

    /**
     * Короткий ответ 403 с внятным текстом вместо abort() в контроллере.
     */
    public static function deny(string $message): HttpException
    {
        return new HttpException(403, $message);
    }
}
