<?php

namespace App\Console\Commands;

use FilesystemIterator;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Перенос каталога материала курсов из-под публичного корня.
 *
 * ЗАЧЕМ. Материал лежит в storage/app/public/private, а storage/app/public
 * попадает под корень веб-сервера через `php artisan storage:link` —
 * обычный шаг деплоя. После этого весь материал доступен по адресу
 * /storage/private/<самолёт>/<курс>/<файл> БЕЗ проверки HMAC-подписи,
 * то есть одна команда деплоя отключала всю защиту приватного
 * контента. Команда `content:check-exposure` это видит и требует
 * «перенесите каталог за пределы public/ и укажите COURSES_PATH».
 *
 * Почему команда, а не инструкция в документе: перенос 7000+ файлов
 * должен быть проверяемым и повторяемым. Команда отказывается
 * переносить в public/, считает файлы и байты до и после, умеет
 * ничего не делать (--dry-run) и печатает, что делать дальше.
 *
 * ПОРЯДОК ПРИМЕНЕНИЯ (важно, иначе сайт временно отдаёт 404):
 *
 *   1. php artisan content:relocate --dry-run     # посмотреть план
 *   2. php artisan content:relocate               # перенести (каталогов
 *                                                 # ещё указывает на
 *                                                 # старое место, сайт
 *                                                 # работает)
 *   3. поменять COURSES_PATH (или ничего: новый путь
 *      уже станет умолчанием в config/app.php)
 *   4. поправить alias в nginx, перезагрузить
 *   5. php artisan content:check-exposure         # должно замолчать
 *   6. tools/verify-nginx-delivery.sh             # отдача живым браузером
 *
 * Перенос в пределах одной файловой системы — это rename(), то есть
 * мгновенно и без второй копии. Между разными системами приходится
 * копировать, и тогда нужно свободное место размером с каталог.
 */
class RelocateContentCommand extends Command
{
    protected $signature = 'content:relocate
        {--from= : Откуда переносить (по умолчанию текущий config courses_path)}
        {--to= : Куда переносить (по умолчанию storage/app/courses/private)}
        {--dry-run : Только показать план, ничего не менять}
        {--force : Разрешить, если каталог назначения не пуст}';

    protected $description = 'Перенести каталог материала курсов за пределы public/ и проверить результат';

    public function handle(): int
    {
        $from = $this->path($this->option('from') ?: config('app.courses_path'));
        $to = $this->path($this->option('to') ?: storage_path('app/courses/private'));
        $dryRun = (bool) $this->option('dry-run');

        $this->line('');
        $this->line('<info>Перенос материала курсов</info>');
        $this->table(
            ['Параметр', 'Значение'],
            [
                ['Откуда', $from],
                ['Куда', $to],
                ['Режим', $dryRun ? 'пробный (ничего не меняется)' : 'перенос'],
            ]
        );
        $this->newLine();

        if ($from === $to) {
            $this->info('Материал уже лежит по новому пути — переносить нечего.');

            return $this->finish($from, $to);
        }

        if (! $this->guardTargetInsidePublic($to)) {
            return self::FAILURE;
        }

        if (! is_dir($from)) {
            $this->error("Каталога с материалом нет: {$from}");

            return self::FAILURE;
        }

        $before = $this->measure($from);

        $this->line(sprintf('  в исходном каталоге: %s, %s', $this->count($before), $this->bytes($before['bytes'])));

        if (is_dir($to) && $this->count($this->measure($to)) > 0 && ! $this->option('force')) {
            $this->error("Каталог назначения не пуст: {$to}");
            $this->line('  Либо удалите его, либо повторите с --force.');
            $this->line('  --force НЕ удаляет старое содержимое: перенос перестанет быть переносом,');
            $this->line('  а слиянием двух каталогов. Сначала разберитесь, что там лежит.');

            return self::FAILURE;
        }

        $this->guardSpace($from, $to);

        if ($dryRun) {
            $this->line('  <comment>Пробный запуск: файлы не тронуты.</comment>');
            $this->newLine();
            $this->line('Чтобы выполнить:');
            $this->line('  php artisan content:relocate');
            $this->newLine();
            $this->nextSteps($to);

            return self::SUCCESS;
        }

        $this->move($from, $to);

        $after = $this->measure($to);

        $this->line(sprintf('  в новом каталоге: %s, %s', $this->count($after), $this->bytes($after['bytes'])));

        if ($after['files'] !== $before['files'] || $after['bytes'] !== $before['bytes']) {
            // Это уже плохо: файлы перемещены, но счёт разошёлся.
            // Молчать об этом нельзя — расхождение означает потерю данных,
            // и заметить его нужно сейчас, а не через месяц.
            $this->error('РАСХОЖДЕНИЕ: до и после переноса разное число файлов или байт.');
            $this->line('  Исходный каталог НЕ удалён — разбирайтесь вручную.');
            $this->line("  откуда: {$from}");
            $this->line("  куда:   {$to}");

            return self::FAILURE;
        }

        $this->info('Перенос завершён, число файлов и байт совпадает.');
        $this->newLine();

        return $this->finish($from, $to);
    }

    /**
     * Отказ, если каталог назначения снова оказался доступен из интернета.
     *
     * Проверяются ДВА каталога, и это не перестраховка:
     *
     *  1. public_path() — корень сайта. Попадание внутрь означает, что
     *     веб-сервер отдаёт содержимое напрямую.
     *  2. storage_path('app/public') — выглядит безобидно, но
     *     `php artisan storage:link` создаёт public/storage ->
     *     storage/app/public, и после этого каталог отдаётся по адресу
     *     /storage/... Проверять только public_path() нельзя: именно
     *     поэтому первый вариант этой команды молча пропускал перенос
     *     в storage/app/public, то есть ровно туда, куда переносить
     *     нельзя.
     *
     * Каталог назначения может ещё не существовать, поэтому сравнение
     * идёт с его ближайшим СУЩЕСТВУЮЩИМ предком: созданные позже
     * подкаталоги всё равно окажутся внутри.
     *
     * Возвращается bool, а не вызывается exit(): команда живёт внутри
     * процесса Artisan, и exit() из неё убивал процесс целиком — вместе
     * с выводом и, в тестах, с самим прогоном.
     */
    private function guardTargetInsidePublic(string $to): bool
    {
        $realTarget = $this->deepestExisting($to);

        if ($realTarget === null) {
            return true;
        }

        $roots = [$this->path(public_path())];

        // Симлинк storage:link может быть ещё не создан, а появиться
        // после переноса — и тогда материал снова станет доступен.
        $storageLink = $this->path(public_path('storage'));
        $realStorageLink = realpath($storageLink);

        if ($realStorageLink !== false) {
            $roots[] = $this->path($realStorageLink);
        }

        $roots[] = $this->path(storage_path('app/public'));

        foreach (array_unique($roots) as $root) {
            if ($realTarget === $root || str_starts_with($realTarget, $root . DIRECTORY_SEPARATOR)) {
                $this->error("Каталог назначения доступен из интернета: {$to}");
                $this->line("  он находится внутри: {$root}");
                $this->line('  Так перенос ничего не меняет: `php artisan storage:link` снова откроет материал.');
                $this->line('  Выберите каталог ВНЕ storage/app/public, например storage/app/courses/private.');

                return false;
            }
        }

        return true;
    }

    /**
     * Проверка места при копировании.
     *
     * Для rename() места не нужно вовсе, поэтому проверка имеет смысл
     * только когда каталоги на разных системах.
     */
    private function guardSpace(string $from, string $to): void
    {
        if ($this->sameDevice($from, $to)) {
            $this->line('  обе точки на одной файловой системе: перенос будет переименованием, места не нужно');

            return;
        }

        $need = $this->measure($from)['bytes'];
        $free = @disk_free_space(dirname($this->deepestExisting($to) ?? $to));

        if ($free === false || $free < $need * 1.05) {
            $this->error('Каталоги на разных системах, а свободного места не хватает.');
            $this->line(sprintf('  нужно примерно %s, свободно %s', $this->bytes((int) $need), $this->bytes((int) $free)));

            return;
        }

        $this->warn(sprintf('  копирование: на это время понадобится ещё около %s', $this->bytes((int) $need)));
    }

    /** Само перемещение: rename, а при разных системах — копирование. */
    private function move(string $from, string $to): void
    {
        $this->line('  перемещаю...');

        if (is_dir($to)) {
            // Каталог мог остаться пустым после --force или прошлой попытки.
            foreach ((array) scandir($to) as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    throw new RuntimeException("Каталог назначения не пуст: {$to}");
                }
            }

            @rmdir($to);
        }

        $parent = dirname($to);

        if (! is_dir($parent) && ! @mkdir($parent, 0775, true) && ! is_dir($parent)) {
            throw new RuntimeException("Не удалось создать каталог: {$parent}");
        }

        $ok = @rename($from, $to);

        if ($ok) {
            return;
        }

        // rename не сработал: почти всегда другая файловая система.
        $this->warn('  переименование не удалось, копирую по файлам');
        $this->copyTree($from, $to);
    }

    /** Копирование дерева с индикатором: перенос 5 ГБ иначе выглядит зависшим. */
    private function copyTree(string $from, string $to): void
    {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $done = 0;
        $total = count(iterator_to_array($items, false));

        foreach ($items as $item) {
            /** @var SplFileInfo $item */
            $target = $to . DIRECTORY_SEPARATOR . $this->relative($from, $item->getPathname());

            if ($item->isDir()) {
                @mkdir($target, 0775, true);
            } else {
                if (! @copy($item->getPathname(), $target)) {
                    throw new RuntimeException('Не удалось скопировать: ' . $item->getPathname());
                }
            }

            $done++;

            if ($done % 250 === 0) {
                $this->line(sprintf('    %d / %d', $done, $total));
            }
        }

        $this->removeTree($from);
    }

    /**
     * Счётчик: сколько файлов и сколько байт в каталоге.
     *
     * Один обход вместо двух. Возвращается массив, потому что
     * пересчитывать отдельно файлы и отдельно каталоги — значит
     * дважды обойти 5 ГБ.
     *
     * @return array{files: int, dirs: int, bytes: int}
     */
    private function measure(string $path): array
    {
        $result = ['files' => 0, 'dirs' => 0, 'bytes' => 0];

        if (! is_dir($path)) {
            return $result;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($items as $item) {
            /** @var SplFileInfo $item */
            if ($item->isDir()) {
                $result['dirs']++;
            } else {
                $result['files']++;
                $result['bytes'] += (int) $item->getSize();
            }
        }

        return $result;
    }

    private function removeTree(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            /** @var SplFileInfo $item */
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($path);
    }

    private function sameDevice(string $a, string $b): bool
    {
        $sa = @stat($a);
        $sb = @stat($this->deepestExisting($b) ?? $b);

        return $sa !== false && $sb !== false && $sa['dev'] === $sb['dev'];
    }

    /** Ближайший СУЩЕСТВУЮЩИЙ предок пути: для ещё не созданных каталогов. */
    private function deepestExisting(string $path): ?string
    {
        $path = $this->path($path);

        while ($path !== '' && ! file_exists($path)) {
            $parent = dirname($path);

            if ($parent === $path) {
                return null;
            }

            $path = $parent;
        }

        return realpath($path) ?: null;
    }

    /** Путь без завершающего слеша — иначе при сравнении строк они не равны. */
    private function path(string $path): string
    {
        return rtrim(str_replace('\\', '/', trim($path)), '/');
    }

    private function relative(string $base, string $path): string
    {
        $base = $this->path($base) . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }

    private function count(array $measured): string
    {
        return sprintf('%d файлов, %d каталогов', $measured['files'], $measured['dirs']);
    }

    private function bytes(int $bytes): string
    {
        return \App\Support\FileManager\ChunkUploadService::humanBytes($bytes);
    }

    /**
     * Что делать после переноса: без этих шагов сайт продолжит искать
     * материал по старому пути и отдавать 404.
     */
    private function nextSteps(string $to): void
    {
        $this->line('Дальше:');
        $this->line("  1. убедиться, что материал по новому пути (каталог: {$to})");
        $this->line('  2. поправить alias в конфиге nginx на новый путь:');
        $this->line('       php artisan content:nginx-config');
        $this->line('     и вставить распечатанный фрагмент, затем nginx -t && systemctl reload nginx');
        $this->line('  3. php artisan content:check-exposure   — должен перестать ругаться');
        $this->line('  4. tools/verify-nginx-delivery.sh       — отдача живым браузером');
    }

    private function finish(string $from, string $to): int
    {
        $this->newLine();
        $this->nextSteps($to);

        if ($from !== $to && ! is_dir($from)) {
            $this->newLine();
            $this->line("Старый каталог пуст: {$from} — его можно удалить, если он мешает.");
        }

        return self::SUCCESS;
    }
}
