<?php

namespace App\Console\Commands;

use App\Support\FileManager\ChunkUploadService;
use Illuminate\Console\Command;

/**
 * Чистка незавершённых загрузок.
 *
 * Зачем нужна, а не «само рассосётся»: части файла лежат на диске до
 * сборки, и загрузка, брошенная на середине, занимает место бессрочно.
 * Пользователь при этом не видит ничего — для него файл просто не
 * загрузился, а на диске лежат его половина.
 *
 * Команда идемпотентна: запускать её можно хоть по минуте.
 */
class PruneFileUploadsCommand extends Command
{
    protected $signature = 'files:prune-uploads
        {--ttl= : Пережить не дольше N секунд (по умолчанию files.upload_ttl)}';

    protected $description = 'Удалить незавершённые загрузки файлов вместе с их частями';

    public function handle(ChunkUploadService $uploads): int
    {
        $ttl = $this->option('ttl');
        $result = $uploads->prune($ttl === null ? null : max(60, (int) $ttl));

        if ($result['deleted'] === 0) {
            $this->info('Незавершённых загрузок нет.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Удалено загрузок: %d, освобождено: %s.',
            $result['deleted'],
            ChunkUploadService::humanBytes((int) $result['bytes'])
        ));

        return self::SUCCESS;
    }
}
