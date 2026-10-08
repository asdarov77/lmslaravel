<?php

namespace App\Support\FileManager;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Пути файлового менеджера: арифметика путей и работа с диском.
 *
 * Раскладка:
 *
 *     <корень диска>/userfiles/<user_id>/<папка>/<файл>
 *     <корень диска>/userfiles/<user_id>/.uploads/<upload_id>/<index>.part
 *
 * Класс намеренно НЕ знает о базе: что такое папка, кому она принадлежит
 * и какие имена заняты, решает FolderTree и FileManagerService. Если
 * путь строится здесь по данным базы, то расхождение «в базе папка есть,
 * а на диске каталога нет» становится возможным тихо. Здесь только
 * «где лежит» и «можно ли туда писать».
 *
 * Идентификатор каталога — user_id, а не ФИО: ФИО меняется, каталог
 * остаётся, и файлы пользователя оказываются в папке, имя которой уже
 * никто не найдёт.
 */
final class Location
{
    /** Служебный каталог незавершённых загрузок внутри каталога пользователя. */
    public const UPLOADS_DIR = '.uploads';

    public function disk(): Filesystem
    {
        return Storage::disk((string) config('files.disk', 'local'));
    }

    /**
     * Префикс каталога пользователя внутри диска: `userfiles/42`.
     */
    public function userPrefix(int $userId): string
    {
        $root = trim((string) config('files.root', 'userfiles'), '/');

        if ($root === '') {
            throw new RuntimeException('Не задан корневой каталог файлов (config/files.php → files.root).');
        }

        return $root.'/'.$userId;
    }

    /** Абсолютный путь каталога пользователя, без создания. */
    public function userRoot(int $userId): string
    {
        return $this->disk()->path($this->userPrefix($userId));
    }

    /**
     * Каталог пользователя, созданный при необходимости.
     *
     * Создание здесь, а не в вызывающем коде: каталог нужен перед
     * первой записью в любом месте дерева, а «забыли создать» — это
     * 500 на первой же загрузке нового пользователя.
     */
    public function ensureUserRoot(int $userId): string
    {
        $path = $this->userRoot($userId);

        if (! is_dir($path)) {
            $this->makeDirectory($path);
        }

        return $path;
    }

    /** Относительный путь каталога (без имени объекта) по сегментам. */
    public function relativeDir(array $segments): string
    {
        $parts = [];

        foreach ($segments as $segment) {
            $clean = trim((string) $segment, '/');

            if ($clean !== '') {
                $parts[] = $clean;
            }
        }

        return implode('/', $parts);
    }

    /**
     * Относительный путь файла внутри каталога пользователя.
     *
     * @param  array<int, string>  $segments  сегменты папок, очищенные EntryName
     */
    public function relativeFile(array $segments, string $name): string
    {
        $dir = $this->relativeDir($segments);

        return $dir === '' ? trim($name, '/') : $dir.'/'.trim($name, '/');
    }

    /** Абсолютный путь по относительному пути от корня диска. */
    public function absolute(int $userId, string $relative): string
    {
        $relative = trim($relative, '/');

        return $this->disk()->path(
            $relative === '' ? $this->userPrefix($userId) : $this->userPrefix($userId).'/'.$relative
        );
    }

    /**
     * Остался ли путь внутри каталога пользователя.
     *
     * Две независимые проверки, потому что у каждой свой класс обхода:
     *
     *   1. разбор на сегменты и сборка заново — ловит `..` и `\` в имени;
     *   2. сверка realpath результата с realpath корня — ловит симлинк,
     *      который прошёл первую проверку.
     *
     * realpath требует существующего пути, поэтому проверять можно
     * только существующий. Для несуществующего пути возвращается false:
     * «каталога нет» и «путь верен» — это разные вещи, и путать их
     * нельзя.
     */
    public function isInsideUserRoot(int $userId, string $relative): bool
    {
        if ($relative === '' || str_contains($relative, "\0")) {
            return false;
        }

        $parts = [];

        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            // `..` и обратный слеш не доезжают до сборки пути: без этой
            // проверки '../..' успешно «собрал» бы путь внутри корня.
            if ($segment === '..' || str_contains($segment, '\\')) {
                return false;
            }

            $parts[] = $segment;
        }

        if ($parts === []) {
            return false;
        }

        $root = realpath($this->ensureUserRoot($userId));
        $candidate = realpath($this->absolute($userId, implode('/', $parts)));

        if ($root === false || $candidate === false) {
            return false;
        }

        return $candidate === $root || str_starts_with($candidate, $root.DIRECTORY_SEPARATOR);
    }

    /**
     * Есть ли объект с таким именем в каталоге на диске.
     *
     * Нужно в дополнение к таблице files: файл, оставшийся на диске без
     * записи (сорванное удаление), занимает имя молча, и следующая
     * загрузка либо перезапишет его, либо упрётся в «занято».
     *
     * @param  array<int, string>  $folderSegments
     */
    public function existsOnDisk(int $userId, array $folderSegments, string $name): bool
    {
        $relative = $this->relativeFile($folderSegments, $name);

        return file_exists($this->absolute($userId, $relative));
    }

    /** Есть ли каталог на диске. */
    public function directoryExists(int $userId, array $folderSegments): bool
    {
        $relative = $this->relativeDir($folderSegments);

        if ($relative === '' || str_contains($relative, "\0")) {
            return false;
        }

        $parts = [];

        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..' || str_contains($segment, '\\')) {
                return false;
            }

            $parts[] = $segment;
        }

        return $parts !== [] && is_dir($this->absolute($userId, implode('/', $parts)));
    }

    /** Создаёт каталог вместе с родителями. */
    public function makeDirectory(string $absolutePath): bool
    {
        if (is_dir($absolutePath)) {
            return true;
        }

        // 0775, а не 0777: каталог должен быть доступен пользователю
        // веб-сервера, но не записью для всех на машине.
        return @mkdir($absolutePath, 0775, true) || is_dir($absolutePath);
    }

    /**
     * Создаёт каталог пользователя по сегментам папок.
     *
     * @param  array<int, string>  $folderSegments
     */
    public function makeUserDirectory(int $userId, array $folderSegments): string
    {
        $absolute = $this->absolute($userId, $this->relativeDir($folderSegments));

        if (! $this->makeDirectory($absolute)) {
            throw new RuntimeException('Не удалось создать каталог для файлов: '.$absolute);
        }

        return $absolute;
    }

    /** Удаляет каталог с содержимым. */
    public function deleteDirectory(string $absolutePath): bool
    {
        if (! is_dir($absolutePath)) {
            return true;
        }

        // Рекурсивное удаление вручную, а не через Symfony Filesystem:
        // тот бросает IOException на read-only-каталоге, а здесь
        // отсутствие каталога — норма (его уже удалили), и ошибка
        // должна быть видна в ответе, а не ронять удаление папки целиком.
        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absolutePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($entries as $entry) {
            /** @var \SplFileInfo $entry */
            if ($entry->isDir() && ! $entry->isLink()) {
                @rmdir($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
            }
        }

        return @rmdir($absolutePath);
    }

    /** Переносит файл или каталог, создавая каталог назначения. */
    public function move(string $from, string $to): bool
    {
        $dir = dirname($to);

        if (! is_dir($dir) && ! $this->makeDirectory($dir)) {
            return false;
        }

        if (file_exists($to)) {
            return false;
        }

        return @rename($from, $to);
    }

    /** Есть ли объект по абсолютному пути. */
    public function exists(string $absolutePath): bool
    {
        return file_exists($absolutePath);
    }

    /** Пуст ли каталог. */
    public function isEmptyDirectory(string $absolutePath): bool
    {
        if (! is_dir($absolutePath)) {
            return false;
        }

        $entries = @scandir($absolutePath);

        return $entries !== false && count($entries) <= 2;
    }

    /**
     * Удаляет пустые каталоги вверх по цепочке, не доходя до корня
     * пользователя.
     *
     * Зачем: перенос папки из «Курс/Лекции» в корень оставляет каталог
     * «Курс» — он больше не значит ничего, но продолжает занимать место
     * в дереве и показывается пользователю как существующая папка, если
     * он заглянет на диск. Пустые каталоги от запущенных процессов и
     * упавшей загрузки — то же самое.
     *
     * Остановка на корне пользователя обязательна: без неё первый же
     * вызов удалил бы каталог самого пользователя, а в нём может лежать
     * всё, что не попало в базу.
     *
     * @param  array<int, string>  $segments  сегменты каталога, с которого начинаем подъём
     */
    public function pruneEmptyDirs(int $userId, array $segments): int
    {
        $removed = 0;
        $root = realpath($this->ensureUserRoot($userId));

        if ($root === false) {
            return 0;
        }

        $path = [];

        foreach ($segments as $segment) {
            $clean = trim((string) $segment, '/');

            if ($clean !== '') {
                $path[] = $clean;
            }
        }

        // Подъём от самого глубокого сегмента к корню. Каждый каталог
        // проверяется ДО удаления: непустой остаётся нетронутым, и
        // подъём на этом прекращается.
        for ($i = count($path); $i > 0; $i--) {
            $absolute = $this->absolute($userId, implode('/', array_slice($path, 0, $i)));

            if (! $this->isEmptyDirectory($absolute)) {
                break;
            }

            $real = realpath($absolute);

            if ($real === false || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR)) {
                break;
            }

            if (! @rmdir($real)) {
                break;
            }

            $removed++;
        }

        return $removed;
    }

    /** Размер файла в байтах, 0 если файла нет. */
    public function size(string $absolutePath): int
    {
        $size = @filesize($absolutePath);

        return is_int($size) ? $size : 0;
    }

    // ------------------------------------------------------------------
    // Незавершённые загрузки
    // ------------------------------------------------------------------

    /** Каталог частей конкретной загрузки. */
    public function uploadDir(int $userId, string $uploadId): string
    {
        return $this->absolute($userId, $this->relativeDir([self::UPLOADS_DIR, $uploadId]));
    }

    /** Создаёт каталог частей. */
    public function ensureUploadDir(int $userId, string $uploadId): string
    {
        $dir = $this->uploadDir($userId, $uploadId);

        if (! is_dir($dir) && ! $this->makeDirectory($dir)) {
            throw new RuntimeException('Не удалось создать каталог частей загрузки: '.$dir);
        }

        return $dir;
    }

    /** Путь файла части по её индексу. */
    public function chunkPath(int $userId, string $uploadId, int $index): string
    {
        return $this->uploadDir($userId, $uploadId).DIRECTORY_SEPARATOR.$index.'.part';
    }

    /**
     * Принятые индексы частей — по фактическому содержимому каталога.
     *
     * Именно по диску, а не по строке сессии. Если запись части
     * прервалась на середине, в базе она может числиться принятой, а на
     * диске быть короче — и сборка файла молча дала бы битый файл.
     */
    public function receivedChunks(int $userId, string $uploadId): array
    {
        $dir = $this->uploadDir($userId, $uploadId);

        if (! is_dir($dir)) {
            return [];
        }

        $found = [];

        foreach ((array) scandir($dir) as $entry) {
            if (preg_match('/^(\d+)\.part$/', (string) $entry, $m) === 1) {
                $found[] = (int) $m[1];
            }
        }

        sort($found);

        return $found;
    }

    /** Сколько байт заняли принятые части. */
    public function receivedBytes(int $userId, string $uploadId): int
    {
        $dir = $this->uploadDir($userId, $uploadId);

        if (! is_dir($dir)) {
            return 0;
        }

        $bytes = 0;

        foreach ((array) scandir($dir) as $entry) {
            if (preg_match('/^(\d+)\.part$/', (string) $entry, $m) === 1) {
                $size = @filesize($dir.DIRECTORY_SEPARATOR.$entry);

                if (is_int($size)) {
                    $bytes += $size;
                }
            }
        }

        return $bytes;
    }

    /**
     * Записывает часть файла.
     *
     * Пишем во временный файл и переименовываем: прерванная запись
     * оставила бы `.part` вдвое короче нужного, и при resume такой файл
     * считался бы принятым (см. receivedChunks), а файл собрался бы с
     * дырой.
     */
    public function writeChunk(int $userId, string $uploadId, int $index, string $body): bool
    {
        $dir = $this->ensureUploadDir($userId, $uploadId);
        $target = $dir.DIRECTORY_SEPARATOR.$index.'.part';
        $tmp = $target.'.writing';

        $written = @file_put_contents($tmp, $body);

        if ($written === false || $written !== strlen($body)) {
            @unlink($tmp);

            return false;
        }

        return @rename($tmp, $target);
    }

    /**
     * Склеивает части в один файл по порядку.
     *
     * Склейка построчная по потоку, а не через file_get_contents всего
     * файла: файл может быть размером с память воркера, и чтение его
     * целиком в PHP — это 500 на большом файле при нехватке памяти.
     */
    public function assemble(int $userId, string $uploadId, int $totalChunks, string $targetPath): bool
    {
        $dir = $this->uploadDir($userId, $uploadId);
        $targetDir = dirname($targetPath);

        if (! is_dir($targetDir) && ! $this->makeDirectory($targetDir)) {
            return false;
        }

        $out = @fopen($targetPath, 'wb');

        if ($out === false) {
            return false;
        }

        try {
            for ($i = 0; $i < $totalChunks; $i++) {
                $part = $dir.DIRECTORY_SEPARATOR.$i.'.part';

                if (! is_file($part)) {
                    return false;
                }

                $in = @fopen($part, 'rb');

                if ($in === false) {
                    return false;
                }

                try {
                    // stream_copy_to_chunked (PHP 8.2) копирует блоками и
                    // не держит в памяти целый кусок.
                    if (function_exists('stream_copy_to_chunked')) {
                        $ok = stream_copy_to_chunked($in, $out, -1, 1 << 20);
                    } else {
                        $ok = stream_copy_to_stream($in, $out);
                    }

                    if ($ok === false) {
                        return false;
                    }
                } finally {
                    fclose($in);
                }
            }
        } finally {
            fclose($out);
        }

        return true;
    }

    /** Удаляет каталог частей загрузки. */
    public function deleteUploadDir(int $userId, string $uploadId): bool
    {
        return $this->deleteDirectory($this->uploadDir($userId, $uploadId));
    }

    /** Размер каталога в байтах (для сверки «что занял пользователь»). */
    public function directoryBytes(string $absolutePath): int
    {
        if (! is_dir($absolutePath)) {
            return 0;
        }

        $bytes = 0;

        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absolutePath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($entries as $entry) {
            /** @var \SplFileInfo $entry */
            if ($entry->isFile()) {
                $size = $entry->getSize();

                if (is_int($size)) {
                    $bytes += $size;
                }
            }
        }

        return $bytes;
    }
}
