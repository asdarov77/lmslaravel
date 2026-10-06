<?php

namespace App\Console\Commands;

use App\Support\ContentDelivery;
use App\Support\PrivateContent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Печатает готовый фрагмент конфигурации nginx.
 *
 * Зачем команда, а не документ в README: в конфиге nginx три вещи,
 * которые обязаны совпадать с приложением, — внутренний путь, корень
 * хранилища и метка «этот запрос от nginx». Опечатка в любой из них
 * даёт молчаливую поломку: либо пустое тело вместо файла, либо 404 на
 * каждом материале. Команда берёт все три из того же места, откуда их
 * берёт приложение, поэтому совпадение обеспечено конструкцией.
 */
class ContentNginxConfigCommand extends Command
{
    protected $signature = 'content:nginx-config
        {--path= : Куда записать файл (по умолчанию печатается в stdout)}
        {--root= : Корень проекта (по умолчанию base_path())}';

    protected $description = 'Сгенерировать фрагмент nginx для раздачи материалов (X-Accel-Redirect)';

    public function handle(): int
    {
        $root = rtrim((string) ($this->option('root') ?: base_path()), '/');
        $diskRoot = rtrim(str_replace('\\', '/', config('filesystems.disks.private.root')), '/');
        $internal = (string) config('private_content.accel_internal', '/_protected-content');
        $marker = ContentDelivery::accelMarker();

        // Внешний location добавляется с завершающим слешем: при
        // alias он обязателен, иначе отбрасывается последний сегмент.
        $alias = $internal.'/';
        $header = ContentDelivery::ACCEL_HEADER;

        $snippet = <<<NGINX
        # Раздача приватных материалов курсов через X-Accel-Redirect.
        #
        # Сгенерировано: php artisan content:nginx-config
        # Проект: {$root}
        #
        # ВНИМАНИЕ: фрагмент вставляется ВНУТРЬ server-блока того же
        # виртуального хоста, который проксирует или fastcgi_pass-ит на
        # приложение. Отдельным server/location он не сработает.

        # 1. Метка «запрос пришёл через nginx».
        #
        # Приложение отдаёт X-Accel-Redirect только если видит этот
        # заголовок. Без него (например, когда приложение открыто
        # напрямую через `php artisan serve`) режим nginx дал бы пустое
        # тело со статусом 200 вместо файла.
        proxy_set_header {$header} "{$marker}";

        # 2. Раздача материалов.
        #
        # internal — не украшение, а требование. Такой location не
        # отвечает на внешние запросы (404) и срабатывает только по
        # заголовку из ответа приложения, который появляется уже после
        # проверки HMAC-подписи и прав доступа. Уберите internal — и
        # любой получит все курсы, просто угадав путь.
        location {$alias} {
            internal;

            # Завершающий слеш обязателен: без него при alias
            # отбрасывается последний сегмент пути.
            alias {$diskRoot}/;

            autoindex off;
        }

        # 3. Приватный контент недоступен и обычным location.
        #
        # Корень диска private лежит внутри публичной папки
        # (storage/app/public), поэтому после `php artisan storage:link`
        # путь /storage/app/public/private/... открыл бы все курсы
        # вообще без подписи. Закрываем явно.
        location ^~ /storage {
            return 404;
        }

        # Полезно рядом с internal-location: без sendfile файл
        # копируется через userspace, и выигрыш от nginx теряется.
        sendfile on;
        aio threads;
        tcp_nopush on;

        # gzip для текстовых ресурсов курса (css/js/svg). Расширение
        # приходит от Laravel, поэтому тип известен заранее.
        gzip on;
        gzip_vary on;
        gzip_min_length 1024;
        gzip_types text/css application/javascript image/svg+xml text/html;
        NGINX;

        $path = $this->option('path');

        if ($path) {
            File::put($path, $this->indent($snippet));
            $this->info('Записано: '.$path);
            $this->line('Подключите фрагмент внутрь server-блока и выполните: nginx -t && nginx -s reload');
        } else {
            $this->line($this->indent($snippet));
        }

        return self::SUCCESS;
    }

    protected function indent(string $text): string
    {
        return rtrim($text)."\n";
    }
}
