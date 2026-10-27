<?php

namespace App\Console\Commands;

use App\Support\FileManager\ChunkUploadService;
use Illuminate\Console\Command;

/**
 * Диагностика ограничений на размер запроса.
 *
 * Загрузка больших файлов идёт частями, и размер части упирается в
 * ограничения, которые заданы НЕ в приложении:
 *
 *   - post_max_size и upload_max_filesize — в php.ini (для FPM свой
 *     ini-файл пула, и не тот, что у CLI);
 *   - client_max_body_size — в nginx, по умолчанию 1m, то есть
 *     запрос большего размера отбивается с 413 ещё ДО PHP.
 *
 * Из-за второго ограничения симптом выглядит загадочно: интерфейс
 * показывает «ошибка загрузки», в логах Laravel нет ничего, потому что
 * запрос туда не дошёл. Поэтому команда не только показывает числа, но и
 * печатает готовую строку nginx, которую надо вставить в конфиг.
 *
 * Ограничение nginx проверить нельзя — оно не видно из PHP. Поэтому
 * команда честно говорит об этом, а не делает вид, что знает.
 */
class FileLimitsCommand extends Command
{
    protected $signature = 'files:limits';

    protected $description = 'Показать лимиты загрузки файлов и что надо настроить в PHP/nginx';

    public function handle(): int
    {
        $chunk = (int) config('files.chunk_bytes');
        $max = (int) config('files.max_bytes');

        $post = $this->bytes((string) ini_get('post_max_size'));
        $upload = $this->bytes((string) ini_get('upload_max_filesize'));
        $memory = $this->bytes((string) ini_get('memory_limit'));

        $this->line('<info>Приложение</info>');
        $this->table(
            ['Параметр', 'Значение'],
            [
                ['Размер части (files.chunk_bytes)', ChunkUploadService::humanBytes($chunk)],
                ['Предел одного файла (files.max_bytes)', $max > 0 ? ChunkUploadService::humanBytes($max) : 'без ограничения'],
                ['Диск', (string) config('files.disk')],
                ['Каталог файлов', $this->absoluteRoot()],
            ]
        );

        $this->newLine();
        $this->line('<info>PHP</info>');
        $this->table(
            ['Параметр', 'Значение', 'Влияет на части'],
            [
                [
                    'post_max_size',
                    $this->human((string) ini_get('post_max_size')),
                    $post === null ? '?' : ($post > $chunk ? 'да — всё в порядке' : 'НЕТ, части будут отбиты'),
                ],
                // upload_max_filesize к частям отношения не имеет: он
                // ограничивает multipart-загрузку, а части уходят сырым
                // телом application/octet-stream. Показывать его как
                // «НЕТ» значило бы отправить администратора менять
                // настройку, которая на загрузку по частям не влияет.
                [
                    'upload_max_filesize',
                    $this->human((string) ini_get('upload_max_filesize')),
                    'не применяется (multipart не используется)',
                ],
                [
                    'memory_limit',
                    $this->human((string) ini_get('memory_limit')),
                    $memory !== null && $memory !== -1 && $memory > 0 && $memory < $chunk
                        ? 'НЕТ, воркер упадёт на приёме части'
                        : 'да — части помещаются',
                ],
            ]
        );

        $this->newLine();
        $this->warn('Значения выше — от процесса CLI. У PHP-FPM свой ini-файл пула:');
        $this->line('  php-fpm8.2 -i | grep -E "post_max_size|upload_max_filesize"');
        $this->newLine();

        /*
         * Запас сверх размера части нужен по двум причинам:
         *  - тело запроса приходит в PHP уже с накладными расходами;
         *  - иначе подогнанная ровно в лимит часть отбивается при
         *    округлении вверх на стороне nginx (1m = 1048576, а не
         *    «мегабайт с округлением»).
         */
        $recommended = $this->ceilBytes($chunk * 2);

        $this->line('<info>nginx</info>');
        $this->line('Чтобы части доходили до PHP, ограничение тела запроса должно быть');
        $this->line('больше размера части. Добавьте в location с обработкой PHP:');
        $this->newLine();
        // Именно формат nginx (8m), а не «8,0 МБ»: в конфиг попадёт
        // ровно то, что напечатано, и директива с запятой-разделителем
        // десятичной дроби и с кириллицей не распарсилась бы.
        $this->line('    client_max_body_size '.$this->nginxBytes($recommended).';');
        $this->line('    client_body_timeout  300s;');
        $this->line('    # Тело запроса не буферизуется на диске nginx:');
        $this->line('    # при обрыве клиент теряет уже принятую часть, а не весь файл.');
        $this->line('    fastcgi_request_buffering off;');
        $this->newLine();
        $this->comment('Проверить фактическое значение: nginx -T | grep client_max_body_size');
        $this->comment('Значение из приложения не проверить: оно живёт в конфиге nginx.');

        if ($memory !== null && $memory !== -1 && $memory > 0 && $memory < $chunk) {
            $this->newLine();
            $this->error(sprintf(
                'memory_limit (%s) меньше размера части (%s): воркер упадёт на приёме части.',
                $this->human((string) ini_get('memory_limit')),
                ChunkUploadService::humanBytes($chunk)
            ));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** Абсолютный путь каталога файлов (по диску и корню из конфига). */
    private function absoluteRoot(): string
    {
        $disk = (string) config('files.disk', 'local');
        $root = trim((string) config('files.root', 'userfiles'), '/');

        $base = config('filesystems.disks.'.$disk.'.root');

        return rtrim((string) $base, '/').'/'.$root.'/<user_id>';
    }

    /** Байты из «8M», «512K», «1G» или числа. null, если не разобрали. */
    private function bytes(string $value): ?int
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^(\d+(?:\.\d+)?)\s*([KMG]?)B?$/i', $value, $m)) {
            return null;
        }

        $multiplier = match (strtoupper($m[2])) {
            'K' => 1024,
            'M' => 1024 * 1024,
            'G' => 1024 * 1024 * 1024,
            default => 1,
        };

        return (int) round(((float) $m[1]) * $multiplier);
    }

    /** Округление вверх до «красивого» числа для nginx. */
    private function ceilBytes(int $bytes): int
    {
        $units = [
            1024 * 1024 * 1024,
            1024 * 1024,
            1024,
            1,
        ];

        foreach ($units as $unit) {
            if ($bytes >= $unit) {
                return (int) (ceil($bytes / $unit) * $unit);
            }
        }

        return $bytes;
    }

    /**
     * Размер в синтаксисе nginx: 8m, 512k, 1g.
     *
     * Отдельный формат, потому что nginx не понимает «8,0 МБ», а
     * печатать надо то, что можно скопировать в конфиг без правки.
     */
    private function nginxBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return (int) ceil($bytes / (1024 * 1024 * 1024)).'g';
        }

        if ($bytes >= 1024 * 1024) {
            return (int) ceil($bytes / (1024 * 1024)).'m';
        }

        if ($bytes >= 1024) {
            return (int) ceil($bytes / 1024).'k';
        }

        return (string) $bytes;
    }

    private function human(string $raw): string
    {
        $bytes = $this->bytes($raw);

        return $bytes === null ? $raw : ChunkUploadService::humanBytes($bytes);
    }
}
