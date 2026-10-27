<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\ContentDelivery;
use App\Support\PrivateContent;
use App\Support\PrivateContentSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Раздача приватного контента: php или nginx через X-Accel-Redirect.
 *
 * Проверяется не «отдаётся ли файл», а что меняется при переключении
 * режима и что НЕ меняется никогда: проверка подписи, безопасность пути
 * и запрет отдать материал тому, кому подпись не выдавали.
 *
 * Почему НЕ Storage::fake: fake-адаптер в этой связке Laravel/Flysystem
 * читает содержимое со смещением и возвращает мусор вместо файла
 * (для 'ascii.txt' приходит пустая строка, для пути с кириллицей —
 * обрывок пути). На такой вывод нельзя опираться. Поэтому контент
 * кладётся в настоящий временный каталог: заодно работает проверка
 * реального пути (realpath) в PrivateContent::accelUri, которую на
 * виртуальном диске проверить было бы нечем.
 */
class ContentDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private const AIRCRAFT = 'Test Aircraft';

    private const COURSE = '01';

    private string $tmpRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpRoot = storage_path('framework/testing/content-'.bin2hex(random_bytes(6)));

        File::ensureDirectoryExists($this->tmpRoot.'/private/'.self::AIRCRAFT.'/'.self::COURSE);

        // courses_path указывает на каталог контента, а корень диска
        // private — на его родителя: именно так устроены эти пути в
        // config/filesystems.php, где ключи начинаются с 'private/...'.
        config([
            'app.courses_path' => $this->tmpRoot.'/private/',
            'filesystems.disks.private.root' => $this->tmpRoot,
        ]);

        Setting::create([
            'name' => ContentDelivery::settingName(),
            'value' => ContentDelivery::PHP,
            'type' => ContentDelivery::settingName(),
        ]);

        ContentDelivery::flush();
    }

    protected function tearDown(): void
    {
        ContentDelivery::flush();
        File::deleteDirectory($this->tmpRoot);

        parent::tearDown();
    }

    /** Кладёт файл контента и возвращает подписанный путь {expires}/{signature}/{file}. */
    private function putContent(string $relative, string $body): string
    {
        $full = PrivateContent::PREFIX.'/'.self::AIRCRAFT.'/'.self::COURSE.'/'.$relative;

        File::ensureDirectoryExists(dirname($this->absolute($full)));
        File::put($this->absolute($full), $body);

        return $this->signedUrl($relative);
    }

    private function absolute(string $relativeToDiskRoot): string
    {
        return $this->tmpRoot.'/'.str_replace('/', DIRECTORY_SEPARATOR, $relativeToDiskRoot);
    }

    private function signedUrl(string $relative): string
    {
        $expires = now()->addMinutes(10)->getTimestamp();

        $signature = PrivateContentSigner::signature(self::AIRCRAFT, self::COURSE, $expires);

        return $expires.'/'.$signature.'/'.$relative;
    }

    /** Подписанный URL целиком, с закодированными сегментами. */
    private function url(string $relative): string
    {
        $encoded = implode('/', array_map('rawurlencode', explode('/', $this->signedUrl($relative))));

        return '/api/private/'.rawurlencode(self::AIRCRAFT).'/'.rawurlencode(self::COURSE).'/'.$encoded;
    }

    private function enableNginx(): void
    {
        $this->setMode(ContentDelivery::NGINX);
    }

    /**
     * Запрос так, как его прислал бы nginx: с заголовком-меткой.
     *
     * Метка обязательна. Без неё приложение не может отличить «файл отдаёт
     * nginx» от «файл должен отдать PHP, но nginx забыл настроить
     * X-Accel-Redirect», и выбрал бы второе — иначе при `php artisan serve`
     * ответ был бы пустым со статусом 200.
     */
    private function viaNginx(): self
    {
        return $this->withHeaders([
            ContentDelivery::ACCEL_HEADER => ContentDelivery::accelMarker(),
        ]);
    }

    private function setMode(string $mode): void
    {
        Setting::where('name', ContentDelivery::settingName())->update(['value' => $mode]);
        ContentDelivery::flush();
    }

    // --- Режим php ---------------------------------------------------

    public function test_php_mode_returns_file_body(): void
    {
        $this->putContent('index.html', '<html>материал</html>');

        $response = $this->viaNginx()->get($this->url('index.html'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=utf-8');
        $this->assertSame('<html>материал</html>', $response->getContent());
        $this->assertNull(
            $response->headers->get('X-Accel-Redirect'),
            'в режиме php заголовка быть не должно — файл отдаёт само приложение'
        );
    }

    public function test_php_mode_streams_nested_resource(): void
    {
        $this->putContent('style.css', 'body{color:red}');

        $response = $this->viaNginx()->get($this->url('style.css'));

        $response->assertOk();
        $this->assertSame('body{color:red}', $response->streamedContent());
        $this->assertNull($response->headers->get('X-Accel-Redirect'));
    }

    // --- Режим nginx -------------------------------------------------

    /**
     * Сгенерированный фрагмент nginx указывает на каталог private.
     *
     * Регресс: команда подставляла alias от корня диска
     * (storage/app/public), тогда как X-Accel-Redirect собирается из
     * относительного пути БЕЗ префикса 'private/'. nginx искал файл на
     * уровень выше и отдавал 404 на КАЖДЫЙ материал — при том, что все
     * проверки подписи и internal проходили. Расхождение было видно
     * только на живых запросах.
     */
    public function test_generated_nginx_config_points_alias_at_private_dir(): void
    {
        $root = rtrim((string) config('filesystems.disks.private.root'), '/');
        $expected = $root.'/'.trim(PrivateContent::PREFIX, '/');

        $path = tempnam(sys_get_temp_dir(), 'nginx-conf').'.conf';

        try {
            $this->artisan('content:nginx-config', ['--path' => $path])->assertExitCode(0);

            $snippet = (string) file_get_contents($path);

            $this->assertStringContainsString('alias '.$expected.'/;', $snippet);

            // Корень диска без каталога private в alias быть не должен.
            $this->assertStringNotContainsString('alias '.$root.'/;', $snippet);
        } finally {
            @unlink($path);
        }
    }

    public function test_generated_nginx_config_marks_the_proxy_header(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'nginx-conf').'.conf';

        try {
            $this->artisan('content:nginx-config', ['--path' => $path])->assertExitCode(0);

            $snippet = (string) file_get_contents($path);

            // Без метки приложение не отдаёт X-Accel-Redirect и падает
            // обратно на PHP — то есть режим nginx не работает вовсе.
            $this->assertStringContainsString(
                'proxy_set_header '.ContentDelivery::ACCEL_HEADER,
                $snippet
            );
            $this->assertStringContainsString(ContentDelivery::accelMarker(), $snippet);
            $this->assertStringContainsString('internal;', $snippet);
        } finally {
            @unlink($path);
        }
    }

    public function test_nginx_mode_returns_empty_body_and_accel_header(): void
    {
        $this->enableNginx();

        $this->putContent('index.html', '<html>материал</html>');

        $response = $this->viaNginx()->get($this->url('index.html'));

        $response->assertOk();

        $accel = $response->headers->get('X-Accel-Redirect');

        $this->assertNotNull($accel, 'nginx-режим обязан отдать X-Accel-Redirect');

        // Тело пустое: файл отдаёт nginx. Непустое тело означало бы, что
        // PHP всё ещё читает файл — то есть главного эффекта нет.
        $this->assertSame('', $response->getContent(), 'тело должно быть пустым');

        // Внутренний URI относительно каталога контента: без префикса
        // 'private/' и без сегментов подписи — alias в nginx указывает
        // именно на каталог контента.
        $prefix = trim((string) config('private_content.accel_internal'), '/');

        $this->assertSame(
            '/'.$prefix.'/'.rawurlencode(self::AIRCRAFT).'/'.self::COURSE.'/index.html',
            $accel
        );
        $this->assertStringNotContainsString(PrivateContent::PREFIX.'/', $accel);
    }

    /**
     * Регресс: режим nginx без nginx на переднем плане.
     *
     * Приложение часто запускают без nginx (php artisan serve, тесты,
     * artisan-команды). Там заголовок X-Accel-Redirect никто не
     * обрабатывает, Symfony отдаёт наружу пустое тело со статусом 200 —
     * материал выглядит как пустая страница, и в логе нет ни ошибки, ни
     * предупреждения. Поэтому без метки приложение обязано отдать файл
     * само.
     */
    public function test_nginx_mode_without_nginx_marker_falls_back_to_php(): void
    {
        $this->enableNginx();
        $this->putContent('index.html', '<html>материал</html>');

        // Запрос БЕЗ метки — как от artisan serve.
        $response = $this->get($this->url('index.html'));

        $response->assertOk();

        $this->assertNull(
            $response->headers->get('X-Accel-Redirect'),
            'без метки X-Accel отдавать нельзя: файл никто не отдаст'
        );
        $this->assertStringContainsString(
            'материал',
            $response->getContent(),
            'файл обязан отдаваться через PHP, а не пустым телом'
        );
    }

    public function test_nginx_mode_does_not_leak_signature_segments(): void
    {
        $this->enableNginx();

        $this->putContent('index.html', '<html>материал</html>');

        $response = $this->viaNginx()->get($this->url('index.html'));
        $accel = (string) $response->headers->get('X-Accel-Redirect');

        [$expires, $signature] = explode('/', $this->signedUrl('index.html'));

        // Подпись в заголовке не нужна и опасна: она попала бы в логи
        // nginx и в ответ, который кэшируют.
        $this->assertStringNotContainsString($signature, $accel);
        $this->assertStringNotContainsString($expires.'/', $accel);
    }

    public function test_nginx_mode_sets_cache_control_private(): void
    {
        $this->enableNginx();

        $this->putContent('style.css', 'body{}');

        $response = $this->viaNginx()->get($this->url('style.css'));

        $response->assertOk();

        // public на ответе с материалом означает, что CDN/Varnish отдаст
        // файл тому, кто подпись не получал.
        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);
    }

    public function test_nginx_mode_encodes_accel_uri(): void
    {
        $this->enableNginx();

        // Пробелы и кириллица в имени файла: незакодированный URI
        // nginx не примет, а пробелы ещё и позволяют подменить ответ.
        $this->putContent('файл с пробелом.css', 'body{}');

        $response = $this->viaNginx()->get($this->url('файл с пробелом.css'));

        $response->assertOk();
        $accel = (string) $response->headers->get('X-Accel-Redirect');

        $this->assertStringNotContainsString(' ', $accel, 'в X-Accel-Redirect не должно быть пробелов');
        $this->assertStringContainsString(rawurlencode('файл с пробелом.css'), $accel);
    }

    // --- Что не меняется переключателем ------------------------------

    public function test_invalid_signature_never_yields_accel_header(): void
    {
        foreach ([ContentDelivery::PHP, ContentDelivery::NGINX] as $mode) {
            $this->setMode($mode);

            $this->putContent('index.html', '<html>BODY-MARKER-42</html>');

            $response = $this->get('/api/private/'.rawurlencode(self::AIRCRAFT).'/'.rawurlencode(self::COURSE).'/1/deadbeef/index.html');

            $response->assertForbidden("недействительная подпись, режим {$mode}");
            $this->assertNull(
                $response->headers->get('X-Accel-Redirect'),
                "подпись недействительна, но заголовок отдан (режим {$mode})"
            );
            // Проверяем по метке в теле файла, а не по слову «материал»:
            // это слово есть в тексте самой ошибки middleware.
            $this->assertStringNotContainsString('BODY-MARKER-42', (string) $response->getContent());
        }
    }

    public function test_traversal_is_rejected_in_both_modes(): void
    {
        // Файл-приманка вне каталога контента: если обход пройдёт, его
        // содержимое окажется в ответе.
        $outside = storage_path('framework/testing/secret-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists(dirname($outside));
        File::put($outside, 'SECRET-OUTSIDE');

        try {
            foreach ([ContentDelivery::PHP, ContentDelivery::NGINX] as $mode) {
                $this->setMode($mode);

                $this->putContent('index.html', '<html>материал</html>');

                $relative = '../../../../../../'.basename($outside);

                $signed = $this->signedUrl($relative);
                $encoded = implode('/', array_map('rawurlencode', explode('/', $signed)));

                $response = $this->get(
                    '/api/private/'.rawurlencode(self::AIRCRAFT).'/'.rawurlencode(self::COURSE).'/'.$encoded
                );

                $this->assertNotEquals(
                    200,
                    $response->getStatusCode(),
                    "обход пути не отбит в режиме {$mode}"
                );
                $this->assertStringNotContainsString(
                    'SECRET-OUTSIDE',
                    (string) $response->getContent(),
                    "обход пути раскрыл файл вне каталога контента (режим {$mode})"
                );
                $this->assertNull($response->headers->get('X-Accel-Redirect'));
            }
        } finally {
            File::delete($outside);
        }
    }

    public function test_missing_file_is_404_in_both_modes(): void
    {
        foreach ([ContentDelivery::PHP, ContentDelivery::NGINX] as $mode) {
            $this->setMode($mode);

            $response = $this->viaNginx()->get($this->url('нет-такого.html'));

            $response->assertNotFound("нет файла в режиме {$mode}");
            $this->assertNull($response->headers->get('X-Accel-Redirect'));
        }
    }

    // --- Настройка ---------------------------------------------------

    public function test_mode_comes_from_settings_over_config(): void
    {
        config(['private_content.delivery' => ContentDelivery::PHP]);

        $this->assertSame(ContentDelivery::PHP, ContentDelivery::mode());

        $this->enableNginx();

        $this->assertSame(
            ContentDelivery::NGINX,
            ContentDelivery::mode(),
            'галка в настройках должна побеждать config, иначе переключатель ничего не делает'
        );
    }

    public function test_missing_setting_falls_back_to_config(): void
    {
        Setting::where('name', ContentDelivery::settingName())->delete();
        ContentDelivery::flush();

        // mode() запоминает значение на время запроса (чтобы 200 вложенных
        // ресурсов не стали 200 запросами в settings), поэтому после смены
        // config его нужно сбросить — иначе проверяется первое значение.
        config(['private_content.delivery' => ContentDelivery::NGINX]);
        ContentDelivery::flush();
        $this->assertSame(ContentDelivery::NGINX, ContentDelivery::mode());

        config(['private_content.delivery' => ContentDelivery::PHP]);
        ContentDelivery::flush();
        $this->assertSame(ContentDelivery::PHP, ContentDelivery::mode());
    }

    public function test_invalid_setting_value_falls_back_safely(): void
    {
        $this->setMode('kafka');

        config(['private_content.delivery' => ContentDelivery::PHP]);
        ContentDelivery::flush();

        // Неизвестное значение не должно ломать выдачу: откатываемся.
        $this->assertSame(ContentDelivery::PHP, ContentDelivery::mode());
    }

    public function test_normalize(): void
    {
        $this->assertNull(ContentDelivery::normalize('kafka'));
        $this->assertNull(ContentDelivery::normalize(null));
        $this->assertNull(ContentDelivery::normalize(123));

        // Регистр и пробелы — не причина отвергать значение: галка и
        // .env пишутся руками.
        $this->assertSame(ContentDelivery::PHP, ContentDelivery::normalize(' PHP '));
        $this->assertSame(ContentDelivery::NGINX, ContentDelivery::normalize('Nginx'));
    }

    public function test_persist_creates_missing_row(): void
    {
        // update() здесь не сработал бы: строки нет, и режим молча
        // остался бы прежним, а галка выглядела бы включённой.
        Setting::where('name', ContentDelivery::settingName())->delete();
        ContentDelivery::flush();

        $this->assertTrue(ContentDelivery::persist(ContentDelivery::NGINX));

        $this->assertSame(
            1,
            Setting::where('name', ContentDelivery::settingName())->where('value', ContentDelivery::NGINX)->count()
        );
        $this->assertSame(ContentDelivery::NGINX, ContentDelivery::mode(), 'persist обязан сбросить запомненное значение');
    }

    public function test_persist_rejects_unknown_mode(): void
    {
        $this->assertFalse(ContentDelivery::persist('kafka'));
        $this->assertSame(ContentDelivery::PHP, ContentDelivery::mode());
    }

    // --- Построение URI -----------------------------------------------

    public function test_accel_uri_rejects_unsafe_segments(): void
    {
        $this->putContent('index.html', 'x');

        $this->assertNull(PrivateContent::accelUri('..', 'passwd'));
        $this->assertNull(PrivateContent::accelUri('a', 'b/../../c'));
        $this->assertNull(PrivateContent::accelUri(''));
        $this->assertNull(PrivateContent::accelUri(null));
    }

    public function test_accel_uri_has_no_double_slash_or_traversal(): void
    {
        $this->putContent('index.html', 'x');

        $uri = PrivateContent::accelUri(self::AIRCRAFT, self::COURSE, 'index.html');

        $this->assertNotNull($uri);
        $this->assertStringNotContainsString('..', $uri);
        $this->assertStringNotContainsString('//', substr($uri, 1), 'двойной слеш во внутреннем URI ломает разбор location');
    }
}
