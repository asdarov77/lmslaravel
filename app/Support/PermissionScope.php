<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\User;

/**
 * Область полномочий по управлению правами.
 *
 * Зачем вынесено в отдельный класс: страница управления правами и API
 * обязаны принимать одинаковые решения о том, кому какие права можно
 * назначить. Иначе интерфейс может показать «недоступно», а прямой вызов
 * API — выполниться (и наоборот).
 *
 * Правила:
 *  - Администратор — всё: любые права любому пользователю, включая
 *    других администраторов.
 *  - Инструктор — только в своей части: не может выдать право, которого
 *    не имеет сам (иначе это эскалация привилегий), не может трогать
 *    администраторов и не может выдать права вне своего набора.
 *  - Обучаемый (и любой без users.view/users.permissions) — не может
 *    управлять правами вовсе.
 */
final class PermissionScope
{
    /**
     * Права, которые нельзя назначить через интерфейс управления правами.
     * Это права о самой системе прав: выдав их инструктору, можно было бы
     * выдать их кому угодно ещё. Администратору остаются доступны — иначе
     * он не смог бы настроить систему.
     *
     * @var array<int, string>
     */
    public const ADMIN_ONLY_SLUGS = [
        'users.permissions',
        'system.maintenance',
    ];

    /** @return array<int, string> */
    public static function adminOnlySlugs(): array
    {
        return self::ADMIN_ONLY_SLUGS;
    }

    /**
     * Может ли актор открыть страницу управления правами.
     */
    public static function canOpen(?User $actor): bool
    {
        if (! $actor) {
            return false;
        }

        return $actor->isSuperAdmin()
            || $actor->hasPermission('users.permissions')
            || $actor->hasPermission('users.view');
    }

    /**
     * Может ли актор менять права конкретного пользователя.
     *
     * Администратору доступно всё. Инструктор не может менять права
     * администраторов (в том числе себя, если он администратор) и не
     * может выйти за пределы своей группы: назначение прав чужому
     * инструктору — это уже не «своя часть».
     */
    public static function canManageUser(?User $actor, User $target): bool
    {
        if (! $actor) {
            return false;
        }

        if ($actor->isSuperAdmin()) {
            return true;
        }

        if (! $actor->hasPermission('users.permissions') && ! $actor->hasPermission('users.view')) {
            return false;
        }

        // Права администратора не трогает никто, кроме администратора.
        if ($target->isAdmin()) {
            return false;
        }

        // Инструктор работает со своей группой.
        if ($actor->group_id !== null && (int) $actor->group_id === (int) $target->group_id) {
            return true;
        }

        return false;
    }

    /**
     * Какие права актор вправе назначать.
     *
     * Администратору — все. Инструктору — только те, что есть у него
     * самого, и без системных прав: иначе он смог бы выдать право,
     * которым сам не обладает, и через него — любые остальные.
     *
     * @return array<int, string>|null null — «без ограничений»
     */
    public static function assignableSlugs(?User $actor): ?array
    {
        if (! $actor || $actor->isSuperAdmin()) {
            return null;
        }

        $own = array_values(array_diff(
            (array) $actor->permissionSlugs(),
            self::ADMIN_ONLY_SLUGS
        ));

        return $own;
    }

    /**
     * Может ли актор выдать конкретное право.
     */
    public static function canAssign(?User $actor, string $slug): bool
    {
        $allowed = self::assignableSlugs($actor);

        if ($allowed === null) {
            return true;
        }

        return in_array($slug, $allowed, true);
    }

    /**
     * Отделяет запрошенные права от запрещённых для актора.
     *
     * @param  array<int, string>  $slugs
     * @return array{granted: array<int, string>, denied: array<int, string>}
     */
    public static function partition(?User $actor, array $slugs): array
    {
        $allowed = self::assignableSlugs($actor);
        $granted = [];
        $denied = [];

        foreach (array_values(array_unique($slugs)) as $slug) {
            if ($allowed === null || in_array($slug, $allowed, true)) {
                $granted[] = $slug;
            } else {
                $denied[] = $slug;
            }
        }

        return ['granted' => $granted, 'denied' => $denied];
    }

    /**
     * Права, отнесённые к своим группам каталога.
     *
     * Нужен интерфейсу, чтобы показать права сгруппированными, а не
     * плоским списком из 26 позиций.
     *
     * @return array<string, array{name: string, permissions: array<int, array<string, mixed>>}>
     */
    public static function catalog(?User $actor = null): array
    {
        $groups = [];

        foreach ((array) config('permissions.permissions', []) as $slug => $meta) {
            $group = (string) ($meta['group'] ?? 'other');
            $groups[$group] ??= [
                'name' => self::groupLabel($group),
                'permissions' => [],
            ];

            $groups[$group]['permissions'][] = [
                'id' => null,
                'slug' => (string) $slug,
                'name' => (string) ($meta['name'] ?? $slug),
                'assignable' => self::canAssign($actor, (string) $slug),
            ];
        }

        return $groups;
    }

    /**
     * Человекочитаемое название группы прав.
     */
    public static function groupLabel(string $group): string
    {
        $labels = [
            'users' => 'Пользователи',
            'courses' => 'Курсы и категории',
            'groups' => 'Группы (классы обучаемых)',
            'questions' => 'Банк вопросов и экзамены',
            'grading' => 'Оценивание',
            'progress' => 'Прогресс обучения',
            'content' => 'Контент и файлы',
            'reports' => 'Отчёты и аудит',
            'dictionaries' => 'Справочники и настройки',
            'system' => 'Система',
            'other' => 'Прочие',
        ];

        return $labels[$group] ?? 'Прочие';
    }

    /**
     * К каталогу приклеивает id из БД — интерфейсу нужен id для сохранения.
     *
     * @param  array<string, array{name: string, permissions: array<int, array<string, mixed>>}>  $groups
     * @return array<string, array{name: string, permissions: array<int, array<string, mixed>>}>
     */
    public static function withIds(array $groups): array
    {
        $bySlug = Permission::query()->pluck('id', 'slug');

        foreach ($groups as &$group) {
            foreach ($group['permissions'] as &$permission) {
                $permission['id'] = $bySlug[$permission['slug']] ?? null;
            }
            unset($permission);
        }
        unset($group);

        return $groups;
    }
}