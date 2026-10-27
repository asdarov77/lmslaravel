<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Безопасная работа с путями контента курсов (АУК).
 *
 * Контент лежит на диске 'private' (см. config/filesystems.php) по схеме:
 *     private/<самолёт>/<АУК>/<файл>
 *
 * Зачем этот класс:
 *
 *  1. Единая точка построения пути. Раньше строка вида
 *     "private/{$aircraft}/{$auk}/{$html}" собиралась вручную в десятках
 *     методов PrivateController/PrivateManiController, и любой из них мог
 *     разъехаться с фактическим расположением контента.
 *
 *  2. Защита от выхода за пределы каталога контента. Сегменты маршрута
 *     приходят от пользователя. Параметр маршрута не содержит '/', но
 *     РАВЕН '..' вполне может (например /api/private/a/b/..), а в Windows
 *     обратный слэш тоже считается разделителем. Без проверки это даёт
 *     чтение файлов за пределами каталога курсов.
 *
 *  3. Читаемость отказа: наружу отдаётся 404, а не 500.
 */
final class PrivateContent
{
    /** Префикс, относительно которого лежит весь контент курсов. */
    public const PREFIX = 'private';

    /**
     * Проверяет сегмент пути и возвращает его либо null, если сегмент
     * недопустим.
     *
     * Запрещены: пустая строка, '.', '..', разделители '/', '\', NUL-байт,
     * а также ведущий/концевой пробел (обход через " .. " и т.п.).
     * Также блокируются URL-кодированные варианты разделителей.
     */
    public static function sanitizeSegment(?string $segment): ?string
    {
        if ($segment === null) {
            return null;
        }

        // Нормализуем пробелы по краям до проверки: " .. " и ".." — одно и то же.
        $segment = trim($segment);

        if ($segment === '' || $segment === '.' || $segment === '..') {
            return null;
        }

        // NUL-байт и любые разделители пути запрещены.
        if (str_contains($segment, "\0")
            || str_contains($segment, '/')
            || str_contains($segment, '\\')) {
            return null;
        }

        // Защита от закодированных разделителей (%2f, %5c).
        $decoded = rawurldecode($segment);
        if ($decoded !== $segment
            && (str_contains($decoded, '/') || str_contains($decoded, '\\') || str_contains($decoded, "\0"))) {
            return null;
        }

        return $segment;
    }

    /**
     * Собирает относительный путь контента из сегментов.
     *
     * @param  array<int, string|null>  $segments
     * @return string|null  null, если хотя бы один сегмент недопустим
     */
    public static function buildPath(array $segments): ?string
    {
        $clean = [];

        foreach ($segments as $segment) {
            $safe = self::sanitizeSegment($segment);

            if ($safe === null) {
                return null;
            }

            $clean[] = $safe;
        }

        if ($clean === []) {
            return null;
        }

        return self::PREFIX.'/'.implode('/', $clean);
    }

    /**
     * Путь относительно КОРНЯ КОНТЕНТА, без префикса 'private/'.
     *
     * Нужен для X-Accel-Redirect: в nginx location с `internal` стоит
     * alias на каталог контента, то есть на courses_path, и в заголовке
     * должен быть путь относительно именно его. Путь вида
     * 'private/<самолёт>/<АУК>/<файл>' там уехал бы на уровень выше и
     * указал бы на несуществующий файл (или, что хуже, на другой каталог).
     *
     * @param  array<int, string|null>  $segments
     */
    public static function relativePath(?string ...$segments): ?string
    {
        $clean = [];

        foreach ($segments as $segment) {
            $safe = self::sanitizeSegment($segment);

            if ($safe === null) {
                return null;
            }

            $clean[] = $safe;
        }

        return $clean === [] ? null : implode('/', $clean);
    }

    /**
     * Внутренний URI для заголовка X-Accel-Redirect.
     *
     * Три независимые проверки на то, что nginx не получит путь, по
     * которому можно вылезти из каталога контента:
     *
     *  1. каждый сегмент проходит sanitizeSegment (запрещены '.', '..',
     *     '/', '\', NUL);
     *  2. собранный абсолютный путь сверяется с реальным (realpath) —
     *     это ловит симлинки внутри контента, которые проходят п. 1;
     *  3. результат кодируется по сегментам, поэтому '%2e%2e' и пробелы
     *     доезжают до nginx как данные, а не как разделители пути.
     *
     * @param  array<int, string|null>  $segments
     * @return string|null null — путь недопустим, отдавать нечего
     */
    public static function accelUri(?string ...$segments): ?string
    {
        $relative = self::relativePath(...$segments);

        if ($relative === null) {
            return null;
        }

        if (! self::staysInsideContentRoot($relative)) {
            return null;
        }

        $prefix = trim((string) config('private_content.accel_internal', '/_protected-content'), '/');
        $encoded = implode('/', array_map('rawurlencode', explode('/', $relative)));

        return '/'.($prefix === '' ? '' : $prefix.'/').$encoded;
    }

    /**
     * Не выходит ли реальный путь за пределы каталога контента.
     *
     * realpath, а не string-compare: внутри контента попадаются
     * симлинки, и '../' в них честно уводит за пределы каталога, хотя
     * сама строка выглядит безобидно.
     */
    public static function staysInsideContentRoot(string $relative): bool
    {
        $root = self::contentRoot();

        if ($root === null) {
            return false;
        }

        $realRoot = realpath($root);
        $candidate = realpath($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative));

        if ($realRoot === false || $candidate === false) {
            return false;
        }

        $realRoot = rtrim($realRoot, DIRECTORY_SEPARATOR);

        return $candidate === $realRoot || str_starts_with($candidate, $realRoot.DIRECTORY_SEPARATOR);
    }

    /** Каталог контента без завершающего слеша. */
    public static function contentRoot(): ?string
    {
        $path = (string) config('app.courses_path', '');

        return $path === '' ? null : rtrim($path, '/\\');
    }

    /**
     * Собирает путь или сразу отдаёт 404.
     *
     * @param  array<int, string|null>  $segments
     */
    public static function safePath(?string ...$segments): string
    {
        $path = self::buildPath($segments);

        if ($path === null) {
            throw new NotFoundHttpException('Некорректный путь к материалу курса');
        }

        return $path;
    }

    /**
     * Отдаёт содержимое файла контента либо бросает 404.
     *
     * @param  array<int, string|null>  $segments
     */
    public static function contents(array $segments): string
    {
        $path = self::safePath(...$segments);

        $disk = Storage::disk('private');

        if (! $disk->exists($path)) {
            throw new NotFoundHttpException('Материал курса не найден');
        }

        return $disk->get($path);
    }
}
