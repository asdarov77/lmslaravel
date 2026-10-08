<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Перенос материала курсов за пределы public/ (`content:relocate`).
 *
 * Команда перемещает 7000+ файлов, поэтому проверяется не «файлы на
 * месте», а то, что она НЕ делает опасного:
 *
 *  - не переносит каталог назначения ВНУТРЬ storage/app/public и
 *    public/. Раньше проверка смотрела только на public_path(), а каталог
 *    контента опасен прежде всего из-за symlink storage:link, и перенос
 *    в storage/app/public проходил молча — то есть ровно туда, куда
 *    переносить нельзя;
 *  - в --dry-run не трогает ничего;
 *  - не перезаписывает непустой каталог назначения;
 *  - переносит файлы и сохраняет их число и размер.
 *
 * Тест НЕ гоняет 5 ГБ: используется каталог с парой файлов. Количество
 * проверяет расхождение, а не объём.
 */
class ContentRelocateTest extends TestCase
{
    private const CONTENT = 'relocate-test-content';

    private string $source;

    private string $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = storage_path('app/'.self::CONTENT.'/source/private');
        $this->target = storage_path('app/'.self::CONTENT.'/target/private');

        File::ensureDirectoryExists($this->source);
        File::put($this->source.'/index.html', str_repeat('A', 1024));
        File::ensureDirectoryExists($this->source.'/КЛЕН/02');
        File::put($this->source.'/КЛЕН/02/index.html', str_repeat('B', 2048));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/'.self::CONTENT));

        parent::tearDown();
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->artisan('content:relocate', [
            '--from' => $this->source,
            '--to' => $this->target,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertDirectoryExists($this->source, 'пробный запуск не должен убирать исходный каталог');
        $this->assertDirectoryDoesNotExist($this->target, 'пробный запуск не должен создавать каталог назначения');
    }

    public function test_content_is_moved_and_counts_match(): void
    {
        $this->artisan('content:relocate', [
            '--from' => $this->source,
            '--to' => $this->target,
        ])->assertSuccessful();

        $this->assertDirectoryDoesNotExist($this->source, 'исходный каталог должен уйти: иначе остались бы две копии');
        $this->assertFileExists($this->target.'/index.html');
        $this->assertFileExists($this->target.'/КЛЕН/02/index.html');

        $this->assertSame(str_repeat('A', 1024), File::get($this->target.'/index.html'));
        $this->assertSame(str_repeat('B', 2048), File::get($this->target.'/КЛЕН/02/index.html'));
    }

    /**
     * Главная защита: оба «доступных из интернета» каталога отвергаются.
     *
     * storage/app/public опаснее публичного корня — он попадает под
     * веб-сервер через symlink storage:link, и проверка только на
     * public_path() его пропускала.
     */
    public function test_target_inside_storage_app_public_is_refused(): void
    {
        $bad = storage_path('app/public/private-relocated');

        try {
            $this->artisan('content:relocate', [
                '--from' => $this->source,
                '--to' => $bad,
            ])->run();

            $this->fail('перенос внутрь storage/app/public должен быть отвергнут');
        } catch (\Throwable $e) {
            // artisan()->run() бросает наружу код возврата команды,
            // потому что команда завершает работу через exit().
            $this->assertDirectoryDoesNotExist($bad, 'каталог внутри public не должен создаваться');
            $this->assertDirectoryExists($this->source, 'исходный каталог не тронут');
        }
    }

    public function test_target_inside_public_root_is_refused(): void
    {
        $bad = public_path('private-relocated');

        try {
            $this->artisan('content:relocate', [
                '--from' => $this->source,
                '--to' => $bad,
            ])->run();

            $this->fail('перенос внутрь публичного корня должен быть отвергнут');
        } catch (\Throwable $e) {
            $this->assertDirectoryDoesNotExist($bad);
            $this->assertDirectoryExists($this->source);
        }
    }

    public function test_non_empty_target_is_not_overwritten(): void
    {
        File::ensureDirectoryExists($this->target);
        File::put($this->target.'/чужой.txt', 'не трогать');

        $this->artisan('content:relocate', [
            '--from' => $this->source,
            '--to' => $this->target,
        ])->assertFailed();

        $this->assertFileExists($this->target.'/чужой.txt', 'чужой файл не должен пропасть');
        $this->assertDirectoryExists($this->source, 'исходный каталог не тронут');
    }

    public function test_missing_source_is_reported(): void
    {
        $this->artisan('content:relocate', [
            '--from' => storage_path('app/'.self::CONTENT.'/нет-такого'),
            '--to' => $this->target,
        ])->assertFailed();
    }

    public function test_relocating_onto_itself_is_a_no_op(): void
    {
        $this->artisan('content:relocate', [
            '--from' => $this->source,
            '--to' => $this->source,
        ])->assertSuccessful();

        $this->assertFileExists($this->source.'/index.html');
    }
}
