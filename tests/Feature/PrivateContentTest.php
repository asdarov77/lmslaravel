<?php

namespace Tests\Feature;

use App\Support\PrivateContent;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Unit-тесты построения путей контента.
 *
 * Регрессия: все content-эндпоинты склеивали путь строкой
 * "private/{$aircraft}/{$auk}/{$html}". Параметры приходят из URL, поэтому
 * значение вида `../../../../etc` позволяло выйти за пределы каталога
 * контента. Storage в Laravel не блокирует обход — блокировка полностью
 * на стороне приложения.
 */
class PrivateContentTest extends TestCase
{
    /** @var array<int, string> */
    private array $temporaryRoots = [];

    protected function tearDown(): void
    {
        Storage::forgetDisk('private');

        foreach ($this->temporaryRoots as $root) {
            File::deleteDirectory($root);
        }

        $this->temporaryRoots = [];

        parent::tearDown();
    }

    public function test_строит_корректный_путь(): void
    {
        $this->assertSame(
            'private/КЛЕН/01/index.html',
            PrivateContent::buildPath(['КЛЕН', '01', 'index.html'])
        );
    }

    public function test_собирает_вложенный_путь(): void
    {
        $this->assertSame(
            'private/БПЛА/04/img/pic.png',
            PrivateContent::buildPath(['БПЛА', '04', 'img', 'pic.png'])
        );
    }

    public function test_отклоняет_разделитель_внутри_сегмента(): void
    {
        // Вложенность задаётся отдельными сегментами, а не слешем в одном:
        // иначе нельзя отличить 'img/pic.png' от 'img/../../etc/passwd'.
        $this->assertNull(PrivateContent::buildPath(['КЛЕН', '01', 'img/pic.png']));
        $this->assertNull(PrivateContent::buildPath(['КЛЕН', '01', 'img\\pic.png']));
    }

    public function test_отклоняет_обход_каталогов(): void
    {
        foreach ([
            ['..'],
            ['../..'],
            ['КЛЕН/../../etc'],
            ['/etc/passwd'],
            ['..\\..\\windows'],
            ["\0"],
        ] as $segments) {
            $this->assertNull(
                PrivateContent::buildPath($segments),
                'Путь '.json_encode($segments).' должен быть отклонён'
            );
        }
    }

    public function test_отклоняет_null_bytes_внутри_сегмента(): void
    {
        $this->assertNull(PrivateContent::sanitizeSegment("index\0.html"));
    }

    public function test_отклоняет_пустой_и_точечный_сегмент(): void
    {
        $this->assertNull(PrivateContent::sanitizeSegment(''));
        $this->assertNull(PrivateContent::sanitizeSegment('.'));
        $this->assertNull(PrivateContent::sanitizeSegment('..'));
    }

    public function test_сохраняет_пробелы_и_кириллицу(): void
    {
        // Названия файлов в контенте содержат пробелы и кириллицу.
        $this->assertSame(
            'private/КЛЕН/01/1.1 Общие сведения.html',
            PrivateContent::buildPath(['КЛЕН', '01', '1.1 Общие сведения.html'])
        );
    }

    /**
     * Подменяет private-диск временным каталогом.
     *
     * КРИТИЧНО: тесты не должны писать в боевой каталог контента
     * (storage/app/public/private — это 5+ ГБ исходников АУК, и он вне git).
     * Storage::fake('private') здесь не подходит: он подменяет корень
     * целиком и ломает проверку реального config.
     */
    private function useTemporaryContentDisk(): string
    {
        $tmp = storage_path('framework/testing/content-'.uniqid());

        File::ensureDirectoryExists($tmp);
        config(['filesystems.disks.private.root' => $tmp]);
        Storage::forgetDisk('private');

        $this->temporaryRoots[] = $tmp;

        return $tmp;
    }

    public function test_содержимое_читается_с_private_диска(): void
    {
        $root = $this->useTemporaryContentDisk();
        File::ensureDirectoryExists($root.'/private/КЛЕН/01');
        File::put($root.'/private/КЛЕН/01/index.html', 'содержимое');

        $this->assertSame('содержимое', PrivateContent::contents(['КЛЕН', '01', 'index.html']));
    }

    public function test_содержимое_бросает_404_для_отсутствующего_файла(): void
    {
        $this->useTemporaryContentDisk();

        $this->expectException(NotFoundHttpException::class);

        PrivateContent::contents(['КЛЕН', '01', 'нет-такого.html']);
    }

    public function test_содержимое_бросает_404_при_обходе_каталогов(): void
    {
        $root = $this->useTemporaryContentDisk();
        // Настоящий секрет РЯДОМ с каталогом контента. Проверяем, что он
        // недоступен: '..' отбрасывается до обращения к диску.
        File::put($root.'/secret.txt', 'нельзя');

        $this->expectException(NotFoundHttpException::class);

        PrivateContent::contents(['..', 'secret.txt']);
    }

    public function test_диск_private_указывает_на_каталог_контента(): void
    {
        $coursesPath = rtrim((string) config('app.courses_path'), '/');
        $root = (string) config('filesystems.disks.private.root');

        // private.root — родитель courses_path, потому что пути в коде
        // начинаются с 'private/...'. Раньше root схлопывался в
        // storage/app/ (переменная PRIVATE_PATH в .env отсутствует).
        $this->assertSame(dirname($coursesPath), $root);
        $this->assertDirectoryExists($root);
        $this->assertDirectoryExists($coursesPath);
    }

    public function test_курс_на_диске_находится_через_private_диск(): void
    {
        // Пути контента начинаются с 'private/...' — именно поэтому корень
        // диска является родителем courses_path, а не самим courses_path.
        // Проверяем на реальном контенте, но пропускаем тест, если каталог
        // не развёрнут (например, на чистом CI).
        $coursesPath = rtrim((string) config('app.courses_path'), '/');

        if (! is_dir($coursesPath)) {
            $this->markTestSkipped('Каталог контента не развёрнут: '.$coursesPath);
        }

        $aircrafts = collect(Storage::disk('private')->directories('private'))
            ->map(fn (string $dir) => basename($dir));

        $this->assertNotEmpty($aircrafts, 'В private-каталоге должны быть самолёты');

        foreach ($aircrafts as $aircraft) {
            $this->assertDirectoryExists($coursesPath.'/'.$aircraft);
        }
    }
}
