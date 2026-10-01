<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Каталог прав из config/permissions.php.
 *
 * Даёт маппинг «старый slug -> новый slug» (legacy-алиасы), чтобы
 * hasPermission() понимал оба стиля, и список защищённых прав,
 * которые нельзя удалять через API.
 */
class PermissionCatalog
{
    public const CACHE_KEY = 'rbac.legacy_aliases.v1';
    public const CACHE_TTL = 3600;

    /**
     * @return array<string,string> ['manage-users' => 'users.view', ...]
     */
    public static function legacyAliases(): array
    {
        // Фолбэк вместо Cache::remember: в развёрнутых инсталляциях каталог
        // storage/framework/{views,cache,data} может быть недоступен для
        // web-процесса — запись file-кэша бросала исключение и давала 500
        // на POST /api/login. Чтение config() дешёвое, кэш здесь не критичен.
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => self::buildAliases());
        } catch (\Throwable $e) {
            return self::buildAliases();
        }
    }

    private static function buildAliases(): array
    {
        $aliases = [];
        foreach (config('permissions.permissions', []) as $slug => $meta) {
            foreach ($meta['legacy'] ?? [] as $legacy) {
                $aliases[$legacy] = $slug;
            }
        }

        return $aliases;
    }

    /**
     * Все известные slug'и каталога (новые + алиасы).
     *
     * @return array<int,string>
     */
    public static function allSlugs(): array
    {
        return array_merge(
            array_keys(config('permissions.permissions', [])),
            array_keys(self::legacyAliases())
        );
    }

    /**
     * Слаги, которые нельзя переименовать или удалить через API.
     *
     * ВАЖНО: сюда попадает ВЕСЬ каталог, а не только явный
     * config('permissions.protected_slugs'). Право из каталога зашито
     * в middleware ('permission:users.view'), в legacy-алиасы и в
     * фильтры бокового меню — его переименование или удаление молча
     * ломает доступ, поэтому такие операции должны возвращать 403.
     *
     * Кастомные права, созданные через POST /api/permissions, в каталог
     * не входят и остаются редактируемыми.
     *
     * @return array<int,string>
     */
    public static function protectedSlugs(): array
    {
        return array_values(array_unique(array_merge(
            array_keys(config('permissions.permissions', [])),
            array_keys(self::legacyAliases()),
            (array) config('permissions.protected_slugs', [])
        )));
    }

    public static function isProtected(string $slug): bool
    {
        return in_array($slug, self::protectedSlugs(), true);
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
