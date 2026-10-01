<?php

namespace Tests\Feature;

use App\Http\Controllers\AircraftController;
use App\Models\Aircraft;
use App\Models\Aukstructure;
use App\Models\Category;
use App\Models\Course;
use App\Models\Link;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Импорт самолёта и АУК из каталога контента в БД.
 *
 * Регрессии, которые закрывает этот тест:
 *
 *  1. `Aircraft` не имел $fillable, поэтому `Aircraft::create([...])` молча
 *     отбрасывал title/path — самолёт сохранялся пустым.
 *
 *  2. `showauks()` возвращал ЛЮБОЙ элемент scandir(), включая файлы и
 *     папку GIFT. Для КЛЕН это означало попытку создать курс «GIFT».
 *
 *  3. `parsemanifest()` брал shortname категории без проверки наличия
 *     атрибута. В манифесте КЛЕН атрибута shortname нет вообще, поэтому все
 *     категории получали code = '' и переставали различаться.
 *
 *  4. `RecurseXML()` склеивал несколько найденных id категории через
 *     implode: [21, 26] => 2126. Импорт КЛЕН падал с нарушением внешнего
 *     ключа aukstructure_category_category_id_foreign. При отсутствии
 *     совпадений получался category_id = 0 — тоже нарушение FK.
 *
 *  5. Импорт шёл без транзакции: падение на середине оставляло в базе
 *     самолёт с недозагруженными АУК.
 *
 *  6. Повторный импорт возвращал пустой 200 — клиент не мог понять результат.
 */
class CourseImportTest extends TestCase
{
    use RefreshDatabase;

    private string $contentRoot;

    protected function setUp(): void
    {
        parent::setUp();

        // Изолированный каталог контента: тест не должен трогать боевые 5+ ГБ.
        $this->contentRoot = storage_path('framework/testing/content-'.uniqid());
        File::ensureDirectoryExists($this->contentRoot.'/private');

        config([
            'app.courses_path' => rtrim($this->contentRoot.'/private', '/').'/',
            'filesystems.disks.private.root' => $this->contentRoot,
        ]);
        Storage::forgetDisk('private');
    }

    protected function tearDown(): void
    {
        Storage::forgetDisk('private');
        File::deleteDirectory($this->contentRoot);

        parent::tearDown();
    }

    /**
     * Вызывает импортёр напрямую (без HTTP-обвязки) и оборачивает ответ
     * в TestResponse, чтобы пользоваться assertStatus()/assertJson().
     */
    private function import(string $title, mixed $path): TestResponse
    {
        $response = $this->app->call(
            [app(AircraftController::class), 'storeclasses'],
            ['request' => Request::create('/api/classes', 'POST', compact('title', 'path'))]
        );

        return TestResponse::fromBaseResponse($response);
    }

    /** @test */
    public function импортирует_самолёт_с_аук_категориями_и_ссылками(): void
    {
        $this->makeAuk('ТЕСТ', '01', shortNames: ['KE', 'PKE']);

        $response = $this->import('Тестовый класс', 'ТЕСТ');

        $response->assertStatus(201);

        $aircraft = Aircraft::first();
        $this->assertNotNull($aircraft);
        $this->assertSame('Тестовый класс', $aircraft->title);
        $this->assertSame('ТЕСТ', $aircraft->path);

        $course = Course::first();
        $this->assertNotNull($course);
        $this->assertSame('01', $course->path);
        $this->assertSame($aircraft->id, $course->aircraft_id);

        $this->assertSame(2, Category::where('aircraft_id', $aircraft->id)->count());
        $this->assertSame(['KE', 'PKE'], Category::orderBy('id')->pluck('code')->all());

        $this->assertGreaterThan(0, Aukstructure::where('course_id', $course->id)->count());
        $this->assertGreaterThan(0, Link::count());
    }

    /** @test */
    public function папка_gift_не_превращается_в_курс(): void
    {
        $this->makeAuk('ТЕСТ', '01');
        $this->makeAuk('ТЕСТ', '02');
        // Служебная папка GIFT на уровне самолёта — не АУК.
        File::ensureDirectoryExists($this->contentRoot.'/private/ТЕСТ/GIFT');
        File::put($this->contentRoot.'/private/ТЕСТ/GIFT/questions.gift', ':: title::');

        $response = $this->import('Тестовый класс', 'ТЕСТ');

        $response->assertStatus(201);
        $this->assertSame(2, Course::count());
        $this->assertNull(Course::where('path', 'GIFT')->first());
    }

    /** @test */
    public function файлы_на_уровне_самолёта_не_считаются_аук(): void
    {
        $this->makeAuk('ТЕСТ', '01');
        File::put($this->contentRoot.'/private/ТЕСТ/readme.txt', 'не АУК');

        $this->import('Тестовый класс', 'ТЕСТ')->assertStatus(201);

        $this->assertSame(1, Course::count());
        $this->assertSame(['01'], Course::pluck('path')->all());
    }

    /** @test */
    public function повторный_импорт_возвращает_409_и_не_дублирует_данные(): void
    {
        $this->makeAuk('ТЕСТ', '01');

        $this->import('Тестовый класс', 'ТЕСТ')->assertStatus(201);

        $courses = Course::count();
        $structures = Aukstructure::count();

        $this->import('Тестовый класс', 'ТЕСТ')->assertStatus(409);

        $this->assertSame($courses, Course::count());
        $this->assertSame($structures, Aukstructure::count());
        $this->assertSame(1, Aircraft::count());
    }

    /** @test */
    public function неизвестный_каталог_даёт_422(): void
    {
        $this->import('Нет такого', 'НЕТ-ТАКОГО')->assertStatus(422);

        $this->assertSame(0, Aircraft::count());
    }

    /** @test */
    public function путь_объектом_от_комбобокса_нормализуется_в_строку(): void
    {
        // Vuetify-комбобокс может прислать выбранный элемент объектом, а не
        // строкой. Раньше валидация 'string' падала с 422 «The path must be
        // a string», хотя класс был выбран в интерфейсе.
        $this->makeAuk('ТЕСТ', '01');

        $response = $this->import('Тестовый класс', ['text' => 'ТЕСТ', 'value' => 'ТЕСТ']);

        $response->assertStatus(201);
        $this->assertSame('ТЕСТ', Aircraft::first()->path);
    }

    /** @test */
    public function путь_массивом_от_комбобокса_нормализуется(): void
    {
        $this->makeAuk('ТЕСТ', '01');

        $response = $this->import('Тестовый класс', ['ТЕСТ']);

        $response->assertStatus(201);
        $this->assertSame('ТЕСТ', Aircraft::first()->path);
    }

    /** @test */
    public function обход_каталогов_отклоняется(): void
    {
        // Раньше path подставлялся в строку без проверки, поэтому можно было
        // указать каталог вне контента.
        $this->import('Взлом', '../../app')->assertStatus(422);

        $this->assertSame(0, Aircraft::count());
    }

    /** @test */
    public function валидация_требует_путь(): void
    {
        // Прямой вызов контроллера бросает ValidationException (HTTP-слой
        // превратил бы его в 422). Проверяем, что валидация срабатывает
        // и ничего не сохраняется.
        $this->expectException(ValidationException::class);

        $this->app->call(
            [app(AircraftController::class), 'storeclasses'],
            ['request' => Request::create('/api/classes', 'POST', ['title' => 'Без пути'])]
        );
    }

    /** @test */
    public function аук_без_манифеста_пропускается_без_ошибки(): void
    {
        $this->makeAuk('ТЕСТ', '01');
        File::ensureDirectoryExists($this->contentRoot.'/private/ТЕСТ/ПУСТОЙ');
        File::put($this->contentRoot.'/private/ТЕСТ/ПУСТОЙ/readme.txt', 'нет манифеста');

        $response = $this->import('Тестовый класс', 'ТЕСТ');

        $response->assertStatus(201);

        $meta = $response->json('meta');
        $this->assertSame(['01'], $meta['auk']);
        $this->assertNotEmpty($meta['skipped']);
    }

    /** @test */
    public function категории_без_shortname_получают_уникальный_код(): void
    {
        // Манифест КЛЕН не содержит атрибута shortname вообще.
        $this->makeAuk('ТЕСТ', '01', shortNames: null);

        $this->import('Тестовый класс', 'ТЕСТ')->assertStatus(201);

        $codes = Category::pluck('code')->all();

        $this->assertNotContains('', $codes, 'Пустые коды категорий недопустимы');
        $this->assertCount(count(array_unique($codes)), $codes, 'Коды категорий должны быть уникальны');
    }

    /** @test */
    public function несколько_категорий_связываются_без_склейки_id(): void
    {
        // Одна строка item ссылается сразу на две категории: implode()
        // склеивал [21, 26] в 2126 и ронял внешний ключ.
        $this->makeAuk('ТЕСТ', '01', shortNames: ['KE', 'PKE'], itemCategories: 'KE,PKE');

        $this->import('Тестовый класс', 'ТЕСТ')->assertStatus(201);

        $course = Course::first();
        $linked = DB::table('category_course')
            ->where('course_id', $course->id)
            ->pluck('category_id')
            ->all();

        $this->assertCount(2, $linked, 'Обе категории должны быть связаны с курсом');
        $this->assertSame($linked, array_unique($linked));

        foreach ($linked as $categoryId) {
            $this->assertDatabaseHas('categories', ['id' => $categoryId]);
        }
    }

    /** @test */
    public function неизвестный_код_категории_не_создаёт_битых_связей(): void
    {
        // В item указан код, которого нет в списке категорий манифеста.
        $this->makeAuk('ТЕСТ', '01', shortNames: ['KE'], itemCategories: 'KE,НЕТ-ТАКОГО');

        $this->import('Тестовый класс', 'ТЕСТ')->assertStatus(201);

        // category_id = 0 нарушал бы внешний ключ.
        $this->assertSame(0, DB::table('category_course')->where('category_id', 0)->count());
        $this->assertSame(0, DB::table('aukstructure_category')->where('category_id', 0)->count());
        $this->assertSame(1, DB::table('category_course')->count());
    }

    /** @test */
    public function showclassesfs_возвращает_только_каталоги_самолётов(): void
    {
        $this->makeAuk('БПЛА', '04');
        File::put($this->contentRoot.'/private/test.txt', 'файл');
        File::ensureDirectoryExists($this->contentRoot.'/private/КЛЕН');

        $classes = app(AircraftController::class)->showclassesfs();

        $this->assertEqualsCanonicalizing(['БПЛА', 'КЛЕН'], $classes);
    }

    /** @test */
    public function showauks_возвращает_только_подкаталоги(): void
    {
        $this->makeAuk('КЛЕН', '01');
        $this->makeAuk('КЛЕН', '02');
        File::put($this->contentRoot.'/private/КЛЕН/imsmanifest.xml', 'файл');
        File::ensureDirectoryExists($this->contentRoot.'/private/КЛЕН/GIFT');

        $auks = app(AircraftController::class)->showauks('КЛЕН');

        $this->assertSame(['01', '02'], $auks);
    }

    /** @test */
    public function showauks_не_выходит_за_каталог_контента(): void
    {
        $this->assertSame([], app(AircraftController::class)->showauks('../../app'));
        $this->assertSame([], app(AircraftController::class)->showauks('НЕТ-ТАКОГО'));
    }

    /**
     * Создаёт правдоподобный АУК: манифест + index.html + материал.
     */
    private function makeAuk(
        string $aircraft,
        string $auk,
        ?array $shortNames = ['KE'],
        ?string $itemCategories = 'KE',
    ): void {
        $dir = $this->contentRoot.'/private/'.$aircraft.'/'.$auk;
        File::ensureDirectoryExists($dir);

        $categoryXml = '';
        foreach ($shortNames ?? [] as $index => $short) {
            $attribute = $short === null ? '' : " shortname=\"$short\"";
            $categoryXml .= "<category name=\"Категория ".($index + 1)."\"$attribute/>";
        }

        if ($categoryXml === '') {
            $categoryXml = '<category name="Без кода"/>';
        }

        $itemAttribute = $itemCategories === null
            ? ''
            : " categories=\"$itemCategories\"";

        $material = "1.1.1 Материал $auk.html";

        // Структура повторяет реальные манифесты: вложенные <organization>
        // и трёхуровневый <item>. Ссылки создаются только на листовом
        // элементе с identifierref (см. условие $auktype == 3 в RecurseXML).
        File::put($dir.'/imsmanifest.xml', <<<XML
            <?xml version='1.0' encoding='utf-8'?>
            <manifest xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"
                      xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                      xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_v1p3"
                      version="1.3" identifier="$auk">
              <metadata>
                <schema>ADL SCORM</schema>
                <schemaversion>2004 3rd Edition</schemaversion>
              </metadata>
              <organizations default="default">
                <organization structure="hierarchical" identifier="default">
                  <title>Курс $aircraft/$auk</title>
                  <item identifier="id_l1">
                    <title>Раздел 1</title>
                    <item identifier="id_l2">
                      <title>Подраздел 1</title>
                      <item identifier="id_l3" identifierref="res_$auk"$itemAttribute>
                        <title>Материал 1</title>
                      </item>
                    </item>
                  </item>
                </organization>
              </organizations>
              <resources>
                <resource identifier="res_$auk" type="webcontent" adlcp:scormtype="sco" href="index.html">
                  <file href="index.html"/>
                  <file href="$material"/>
                </resource>
              </resources>
              <course>
                $categoryXml
              </course>
            </manifest>
            XML);

        File::put($dir.'/index.html', '<html><body>'.$material.'</body></html>');
        File::put($dir.'/'.$material, '<html><body>Материал</body></html>');
    }
}
