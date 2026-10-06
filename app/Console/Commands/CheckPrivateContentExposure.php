<?php

namespace App\Console\Commands;

use App\Support\PrivateContent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Проверяет, что контент курсов недоступен напрямую через веб-сервер.
 *
 * Зачем проверка отдельной командой: каталог контента лежит внутри
 * storage/app/public. Команда `php artisan storage:link`, которую делают
 * почти на каждом деплое, создаёт симлинк public/storage ->
 * storage/app/public, и весь материал становится доступен по адресу
 * /storage/private/<самолёт>/<АУК>/<файл> БЕЗ проверки HMAC-подписи.
 *
 * То есть защита, за которую отвечает middleware
 * ValidatePrivateContentSignature, держится на том, что эти файлы не
 * отдаёт веб-сервер напрямую. Симлинк — единственный способ это сломать,
 * и заметить его по логам нельзя: запросы успешно отдаются.
 *
 * Команда ничего не чинит молча и не удаляет: сначала показывает, что
 * не так, решение за администратором.
 */
class CheckPrivateContentExposure extends Command
{
    protected $signature = 'content:check-exposure';

    protected $description = 'Проверить, что контент курсов не отдаётся веб-сервером напрямую';

    public function handle(): int
    {
        $problems = [];

        $link = public_path('storage');

        if (is_link($link) || is_dir($link)) {
            $target = is_link($link) ? readlink($link) : '(настоящий каталог)';

            // Целевая папка симлинка — private, если каталог контента
            // действительно лежит внутри public.
            $root = PrivateContent::contentRoot();
            $rootReal = $root !== null ? realpath($root) : false;

            $exposes = false;

            if (is_link($link)) {
                $resolved = realpath($link);
                $exposes = $rootReal !== false
                    && $resolved !== false
                    && ($resolved === $rootReal || str_starts_with($rootReal, rtrim($resolved, '/').'/'));
            }

            if ($exposes) {
                $problems[] = sprintf(
                    'public/storage -> %s: симлинк открывает каталог контента (%s) напрямую, '
                    .'вся HMAC-защита /api/private/... обходится адресом /storage/private/...',
                    $target,
                    $root
                );
            } else {
                $this->line("  public/storage -> {$target}: каталог контента не открывает, ок.");
            }
        } else {
            $this->line('  public/storage: симлинка нет, ок.');
        }

        // Каталог контента внутри публичной части storage — сам по себе
        // не дыра (его закрывает отсутствие симлинка), но стоит сказать.
        $root = PrivateContent::contentRoot();

        if ($root !== null && str_contains($root, '/public/') && ! $problems) {
            $this->warn("  каталог контента лежит внутри публичной части: {$root}");
            $this->warn('  без public/storage он недоступен извне, но при storage:link станет доступен.');
        }

        if (! is_dir((string) $root)) {
            $problems[] = "каталог контента не найден: {$root}. Проверьте COURSES_PATH.";
        }

        $files = 0;

        try {
            // allFiles() возвращает МАССИВ. Приведение массива к int даёт
            // всегда 1, и команда рапортовала «файлов: 1» при 7069.
            $files = count(Storage::disk('private')->allFiles());
        } catch (\Throwable $e) {
            $problems[] = 'не удалось прочитать каталог контента: '.$e->getMessage();
        }

        $this->line("  файлов в контенте: {$files}");

        if ($problems === []) {
            $this->info('Контент курсов напрямую не отдаётся.');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        $this->newLine();
        $this->warn('Как закрыть: перенесите каталог контента за пределы public/ и укажите');
        $this->warn('COURSES_PATH, либо уберите public/storage и НЕ выполняйте storage:link.');
        $this->warn('Для режима nginx: каталог контента alias-ится во внутренний location');

        return self::FAILURE;
    }
}
