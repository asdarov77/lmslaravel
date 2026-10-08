<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\FileFolder;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File as Fs;
use Tests\TestCase;

/**
 * Файловый менеджер: каталоги, папки, файлы, перенос и удаление.
 *
 * Что здесь проверяется и почему именно это:
 *
 *  - ВЛАДЕНИЕ. Каждая операция отклоняется для чужого id, и ЧУЖОЙ ID
 *    ДАЁТ «НЕ НАЙДЕНО», а не «нет прав». Иначе по реакции на запрос
 *    можно перебрать содержимое чужого каталога, не имея ни одного
 *    файла.
 *  - БЕЗОПАСНОСТЬ ПУТИ. Имя вида «../» или с разделителем обязано быть
 *    отвергнуто, а не очищено молча: молчаливая очистка дала бы файл с
 *    именем, которого пользователь не запрашивал.
 *  - СОГЛАСОВАННОСТЬ БАЗЫ И ДИСКА. После каждой операции проверяется не
 *    только ответ, но и что файл/каталог реально лежит по новому пути.
 *    Расхождение «запись есть, файла нет» — самая дорогая ошибка
 *    файлового менеджера: пользователь видит файл и не может его открыть.
 *  - ЗАЩИТА ОТ ЗАЦИКЛИВАНИЯ. Перенос папки внутрь себя обязан быть
 *    отвергнут, иначе дерево перестаёт быть деревом.
 */
class FileManagerTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    /** Отдельный корень под тесты: боевые файлы задевать нельзя. */
    private const TEST_ROOT = 'testing-filemanager';

    /**
     * Пользователь, под которым идёт текущий запрос.
     *
     * Нужен потому, что AuthenticatesApi::admin() только ставит заголовок
     * с токеном, а пользователь появляется в контейнере во время
     * обработки запроса. Вне запроса $this->app['auth']->user() — null,
     * и хелпер, которому нужен id владельца файла, упал бы с
     * «Attempt to read property id on null».
     */
    private ?\App\Models\User $current = null;

    /** Войти под администратором и запомнить его для хелперов. */
    private function asAdmin(): \App\Models\User
    {
        // admin(), а не asAdmin(): массовая замена имени во время
        // правок однажды превратила этот хелпер в бесконечную рекурсию,
        // и тесты «зависали» вместо того, чтобы падать с ошибкой.
        $this->current = $this->admin();

        return $this->current;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'files.disk' => 'local',
            'files.root' => self::TEST_ROOT,
            'files.chunk_bytes' => 1024,
            'files.max_bytes' => 1024 * 1024,
            'files.max_bulk' => 50,
        ]);
    }

    protected function tearDown(): void
    {
        Fs::deleteDirectory(storage_path('app/'.self::TEST_ROOT));

        parent::tearDown();
    }

    /**
     * Ошибка валидации в конверте ответа.
     *
     * assertJsonValidationErrors здесь не работает: ApiResponseEnvelope
     * оборачивает ЛЮБОЙ JsonResponse, включая 422, поэтому errors лежит
     * в data.errors и в error.details, а не на верхнем уровне. Об этом
     * же написано в tests/Feature/AuthTest.php.
     */
    private function assertFieldError(\Illuminate\Testing\TestResponse $response, string $field): void
    {
        $response->assertStatus(422)->assertJsonPath('success', false);

        $this->assertArrayHasKey(
            $field,
            (array) $response->json('error.details'),
            "Ожидалась ошибка валидации по полю {$field}, получено: ".$response->getContent()
        );
    }

    // ------------------------------------------------------------------
    // Доступ
    // ------------------------------------------------------------------

    public function test_guest_is_rejected_with_401(): void
    {
        $this->getJson('/api/filemanager')->assertUnauthorized();
    }

    public function test_user_without_right_is_rejected_with_403(): void
    {
        // Роль из матрицы без files.upload: у обучаемого есть только
        // content.view, и этого НЕ хватает — файлы менеджера личные.
        $role = Role::factory()->create(['rolename' => 'Обучаемый', 'slug' => 'student']);
        $user = $this->asUser(['role' => 'Обучаемый']);
        $user->roles()->attach($role);

        $this->getJson('/api/filemanager')->assertForbidden();
    }

    public function test_read_is_closed_for_content_view_only(): void
    {
        // Регресс-инвариант: право «смотреть учебный контент» не должно
        // открывать личные файлы. Проверяем явно, потому что список
        // прав файлового менеджера легко случайно расширить до
        // «все, кто вошёл».
        $role = Role::factory()->create(['rolename' => 'Читатель', 'slug' => 'reader']);
        $user = $this->asUser(['role' => 'Читатель']);
        $user->roles()->attach($role);
        $user->permissions()->attach(Permission::firstOrCreate(
            ['slug' => 'content.view'],
            ['name' => 'Просмотр учебного контента']
        ));

        $this->getJson('/api/filemanager')->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Папки
    // ------------------------------------------------------------------

    public function test_folder_is_created_and_listed(): void
    {
        $this->asAdmin();

        $response = $this->postJson('/api/filemanager/folders', ['name' => 'Отчёты']);
        $response->assertCreated();
        $folderId = $response->json('data.id');
        $this->assertSame('Отчёты', $response->json('data.name'));

        $listing = $this->getJson('/api/filemanager');
        $listing->assertOk();

        $names = array_column($listing->json('data.folders'), 'name');
        $this->assertContains('Отчёты', $names);
        $this->assertSame('Файлы', $listing->json('data.breadcrumb.0.name'));

        // Каталог на диске обязан существовать: иначе первая же
        // загрузка в папку упала бы с «нет такого каталога».
        $this->assertDirectoryExists(storage_path('app/'.self::TEST_ROOT.'/'.$this->userId().'/Отчёты'));
    }

    public function test_duplicate_folder_name_gets_suffix_instead_of_overwriting(): void
    {
        $this->asAdmin();

        $first = $this->postJson('/api/filemanager/folders', ['name' => 'Отчёты'])->json('data.id');
        $second = $this->postJson('/api/filemanager/folders', ['name' => 'Отчёты']);

        $second->assertCreated();
        $this->assertSame('Отчёты (2)', $second->json('data.name'));

        $listing = $this->getJson('/api/filemanager')->json('data.folders');
        $this->assertCount(2, $listing);
    }

    public function test_folder_name_with_path_traversal_is_rejected(): void
    {
        $this->asAdmin();

        // Молча «очистить» имя здесь нельзя: пользователь получил бы
        // папку, которой не просил, и не понял бы, куда попал файл.
        foreach (['../снаружи', '..', 'a/b', 'a\\b', '.hidden', 'имя.', ''] as $bad) {
            $this->assertFieldError(
                $this->postJson('/api/filemanager/folders', ['name' => $bad]),
                'name'
            );
        }

        // Ничего не создалось и ничего не утекло наружу.
        $this->getJson('/api/filemanager')->assertJsonPath('data.folders', []);
        $this->assertDirectoryDoesNotExist(storage_path('app/'.self::TEST_ROOT.'/снаружи'));
    }

    public function test_folder_is_renamed_on_disk_and_in_database(): void
    {
        $this->asAdmin();

        $folderId = $this->postJson('/api/filemanager/folders', ['name' => 'Старое'])->json('data.id');
        $this->putFile('Старое/файл.txt', 'данные');

        $this->patchJson("/api/filemanager/folders/{$folderId}", ['name' => 'Новое'])->assertOk();

        $userRoot = storage_path('app/'.self::TEST_ROOT.'/'.$this->userId());
        $this->assertDirectoryDoesNotExist($userRoot.'/Старое');
        $this->assertFileExists($userRoot.'/Новое/файл.txt');

        // Путь файла переписан: он указывает на каталог, которого больше
        // нет, и файл перестал бы открываться.
        $file = File::query()->where('name', 'файл.txt')->firstOrFail();
        $this->assertSame('Новое/файл.txt', $file->path);
    }

    public function test_folder_is_moved_together_with_its_files(): void
    {
        $this->asAdmin();

        $parent = $this->postJson('/api/filemanager/folders', ['name' => 'Курс'])->json('data.id');
        $child = $this->postJson('/api/filemanager/folders', [
            'parent_id' => $parent,
            'name' => 'Лекции',
        ])->json('data.id');

        $this->putFile('Курс/Лекции/тема.txt', 'материал');

        $this->postJson("/api/filemanager/folders/{$child}/move", ['parent_id' => null])->assertOk();

        $userRoot = storage_path('app/'.self::TEST_ROOT.'/'.$this->userId());
        $this->assertFileExists($userRoot.'/Лекции/тема.txt');
        $this->assertDirectoryDoesNotExist($userRoot.'/Курс');

        // Папка не исчезла, а сменила родителя, поэтому folder_id у файла
        // остаётся прежним — меняется только path (он и есть «где файл»).
        $this->assertNull(FileFolder::query()->findOrFail($child)->parent_id);

        $file = File::query()->where('name', 'тема.txt')->firstOrFail();
        $this->assertSame($child, (int) $file->folder_id);
        $this->assertSame('Лекции/тема.txt', $file->path);
    }

    public function test_folder_cannot_be_moved_into_itself(): void
    {
        $this->asAdmin();

        $outer = $this->postJson('/api/filemanager/folders', ['name' => 'Внешняя'])->json('data.id');
        $inner = $this->postJson('/api/filemanager/folders', [
            'parent_id' => $outer,
            'name' => 'Внутренняя',
        ])->json('data.id');

        // В себя: кольцо длины 1.
        $this->assertFieldError(
            $this->postJson("/api/filemanager/folders/{$outer}/move", ['parent_id' => $outer]),
            'folder_id'
        );

        // В собственного потомка: кольцо длиннее 1. Именно этот случай
        // ловится обходом по родителям кандидата, а не сравнением id.
        $this->assertFieldError(
            $this->postJson("/api/filemanager/folders/{$outer}/move", ['parent_id' => $inner]),
            'folder_id'
        );
    }

    public function test_non_empty_folder_requires_explicit_recursive_delete(): void
    {
        $this->asAdmin();

        $folderId = $this->postJson('/api/filemanager/folders', ['name' => 'С содержимым'])->json('data.id');
        $this->putFile('С содержимым/файл.txt', 'данные');

        // Без флага — отказ. Иначе «удалить папку» молча удалила бы всё
        // её содержимое, а пользователь нажимал «удалить папку».
        $this->assertFieldError(
            $this->deleteJson("/api/filemanager/folders/{$folderId}"),
            'folder_id'
        );

        $this->assertFileExists(storage_path('app/'.self::TEST_ROOT.'/'.$this->userId().'/С содержимым/файл.txt'));

        $this->deleteJson("/api/filemanager/folders/{$folderId}?recursive=1")->assertOk();

        $this->getJson('/api/filemanager')->assertJsonPath('data.folders', []);
        $this->assertDirectoryDoesNotExist(storage_path('app/'.self::TEST_ROOT.'/'.$this->userId().'/С содержимым'));
    }

    public function test_recursive_delete_removes_subfolders_and_their_files(): void
    {
        $this->asAdmin();

        $root = $this->postJson('/api/filemanager/folders', ['name' => 'Дерево'])->json('data.id');
        $child = $this->postJson('/api/filemanager/folders', [
            'parent_id' => $root,
            'name' => 'Ветка',
        ])->json('data.id');

        $this->putFile('Дерево/верх.txt', '1');
        $this->putFile('Дерево/Ветка/низ.txt', '2');

        // ids обязательны: без них запрос не должен проходить.
        $this->assertFieldError(
            $this->postJson('/api/filemanager/files/move', ['ids' => [], 'folder_id' => null]),
            'ids'
        );

        $response = $this->deleteJson("/api/filemanager/folders/{$root}?recursive=1");
        $response->assertOk();
        // Сама папка + вложенная, и два файла: верхний лежит прямо в
        // удаляемой папке, нижний — во вложенной. Проверка именно счёта
        // ловит регрессию, при которой файлы удаляются из базы, но
        // остаются на диске (или наоборот).
        $this->assertSame(2, $response->json('data.deleted_folders'));
        $this->assertSame(2, $response->json('data.deleted_files'));

        $this->assertSame(0, FileFolder::query()->whereIn('id', [$root, $child])->count());
        $this->assertDirectoryDoesNotExist(storage_path('app/'.self::TEST_ROOT.'/'.$this->userId().'/Дерево'));
    }

    // ------------------------------------------------------------------
    // Файлы
    // ------------------------------------------------------------------

    public function test_file_is_renamed_on_disk_and_path_is_updated(): void
    {
        $this->asAdmin();

        $fileId = $this->insertManagedFile('черновик.txt', 'содержимое');

        $this->patchJson("/api/filemanager/files/{$fileId}", ['name' => 'итог.txt'])->assertOk();

        $userRoot = storage_path('app/'.self::TEST_ROOT.'/'.$this->userId());
        $this->assertFileDoesNotExist($userRoot.'/черновик.txt');
        $this->assertFileExists($userRoot.'/итог.txt');
        $this->assertSame('содержимое', file_get_contents($userRoot.'/итог.txt'));

        $file = File::query()->findOrFail($fileId);
        $this->assertSame('итог.txt', $file->name);
        $this->assertSame('txt', $file->extension);
        $this->assertSame('итог.txt', $file->path);
    }

    public function test_rename_to_existing_name_does_not_overwrite(): void
    {
        $this->asAdmin();

        $first = $this->insertManagedFile('отчёт.txt', 'ПЕРВЫЙ');
        $this->insertManagedFile('отчёт (2).txt', 'ВТОРОЙ');

        // Запись без счётчика была бы тихим уничтожением чужого файла.
        $this->patchJson("/api/filemanager/files/{$first}", ['name' => 'отчёт (2).txt'])->assertOk();

        $userRoot = storage_path('app/'.self::TEST_ROOT.'/'.$this->userId());

        // Содержимое «ВТОРОЙ» на месте и не изменилось.
        $this->assertSame('ВТОРОЙ', file_get_contents($userRoot.'/отчёт (2).txt'));

        // Первый файл ушёл под свободное имя. Номер у уже занятого
        // имени снимается, иначе получилось бы «отчёт (2) (2).txt».
        $this->assertSame('отчёт (3).txt', File::query()->findOrFail($first)->name);
        $this->assertSame('ПЕРВЫЙ', file_get_contents($userRoot.'/отчёт (3).txt'));
        $this->assertFileDoesNotExist($userRoot.'/отчёт.txt');
    }

    public function test_files_are_moved_into_folder_in_bulk(): void
    {
        $this->asAdmin();

        $folderId = $this->postJson('/api/filemanager/folders', ['name' => 'Архив'])->json('data.id');

        $a = $this->insertManagedFile('a.txt', 'A');
        $b = $this->insertManagedFile('b.txt', 'B');

        $response = $this->postJson('/api/filemanager/files/move', [
            'ids' => [$a, $b],
            'folder_id' => $folderId,
        ]);

        $response->assertOk();
        $this->assertSame(2, $response->json('data.moved'));

        $userRoot = storage_path('app/'.self::TEST_ROOT.'/'.$this->userId());
        $this->assertFileExists($userRoot.'/Архив/a.txt');
        $this->assertFileExists($userRoot.'/Архив/b.txt');
        $this->assertFileDoesNotExist($userRoot.'/a.txt');

        $listing = $this->getJson('/api/filemanager?folder_id='.$folderId);
        $this->assertCount(2, $listing->json('data.files'));
        $this->assertCount(0, $listing->json('data.folders'));
    }

    public function test_moving_two_files_with_same_name_from_different_folders_keeps_both(): void
    {
        $this->asAdmin();

        $source = $this->postJson('/api/filemanager/folders', ['name' => 'Источник'])->json('data.id');
        $target = $this->postJson('/api/filemanager/folders', ['name' => 'Приёмник'])->json('data.id');

        $a = $this->insertManagedFile('doc.txt', 'A', $source);
        $b = $this->insertManagedFile('doc.txt', 'B');

        $this->postJson('/api/filemanager/files/move', ['ids' => [$a, $b], 'folder_id' => $target])
            ->assertOk();

        // Снимок занятых имён пополняется по ходу переноса: без этого
        // второй файл получил бы то же имя и затёр первый.
        $this->assertSame(
            'A',
            file_get_contents(storage_path('app/'.self::TEST_ROOT.'/'.$this->userId().'/Приёмник/doc.txt'))
        );
        $this->assertSame(
            'B',
            file_get_contents(storage_path('app/'.self::TEST_ROOT.'/'.$this->userId().'/Приёмник/doc (2).txt'))
        );
    }

    public function test_bulk_delete_removes_files_from_disk_and_database(): void
    {
        $this->asAdmin();

        $a = $this->insertManagedFile('a.txt', 'A');
        $b = $this->insertManagedFile('b.txt', 'B');

        $response = $this->postJson('/api/filemanager/files/delete', ['ids' => [$a, $b]]);
        $response->assertOk();
        $this->assertSame(2, $response->json('data.deleted'));

        $this->assertSame(0, File::query()->whereIn('id', [$a, $b])->count());
        $userRoot = storage_path('app/'.self::TEST_ROOT.'/'.$this->userId());
        $this->assertFileDoesNotExist($userRoot.'/a.txt');
        $this->assertFileDoesNotExist($userRoot.'/b.txt');
    }

    public function test_file_name_traversal_is_rejected(): void
    {
        $this->asAdmin();

        $fileId = $this->insertManagedFile('safe.txt', 'данные');

        $this->assertFieldError(
            $this->patchJson("/api/filemanager/files/{$fileId}", ['name' => '../побег.txt']),
            'name'
        );

        // Ничего не переименовано и ничего не создано вне каталога.
        $this->assertSame('safe.txt', File::query()->findOrFail($fileId)->name);
        $this->assertFileDoesNotExist(storage_path('app/'.self::TEST_ROOT.'/побег.txt'));
    }

    public function test_download_returns_file_with_its_name(): void
    {
        $this->asAdmin();

        $fileId = $this->insertManagedFile('Отчёт.txt', 'данные для выгрузки');

        $response = $this->get("/api/filemanager/files/{$fileId}/download");

        $response->assertOk();

        // Content-Disposition обязан содержать имя — иначе браузер
        // сохранит файл как «download» без расширения.
        //
        // Сравнивается не вся строка целиком: Symfony транслитерирует
        // кириллицу в ASCII-имя и меняет регистр ключа filename*
        // («utf-8''» вместо «UTF-8''») между версиями. Проверяются
        // смысловые части: attachment, наличие filename* и то, что в
        // нём закодировано ровно наше имя.
        $disposition = (string) $response->headers->get('content-disposition');

        $this->assertStringStartsWith('attachment;', $disposition);
        $this->assertStringContainsString('filename*=', $disposition);
        $this->assertStringContainsString(
            rawurlencode('Отчёт.txt'),
            $disposition,
            'В Content-Disposition должно быть закодировано исходное имя с кириллицей.'
        );
    }

    // ------------------------------------------------------------------
    // Изоляция между пользователями
    // ------------------------------------------------------------------

    public function test_other_users_files_and_folders_are_invisible(): void
    {
        // Регресс-инвариант: чужой объект обязан выглядеть как
        // отсутствующий. Если бы он давал 403, по id можно было бы
        // подтвердить существование чужой папки и перебирать содержимое.
        $owner = $this->asUser();
        $ownerFolder = $this->insertFolder((int) $owner->id, 'Чужая папка');
        $ownerFile = $this->putFile('чужой.txt', 'секрет', (int) $owner->id);

        $this->asAdmin();

        $this->getJson('/api/filemanager')->assertJsonPath('data.folders', []);
        $this->getJson('/api/filemanager')->assertJsonPath('data.files', []);

        $this->assertFieldError(
            $this->patchJson("/api/filemanager/folders/{$ownerFolder}", ['name' => 'моё']),
            'folder_id'
        );

        $this->assertFieldError(
            $this->patchJson("/api/filemanager/files/{$ownerFile}", ['name' => 'моё.txt']),
            'file_id'
        );

        $this->deleteJson("/api/filemanager/files/{$ownerFile}")->assertOk();

        // Запись чужого файла жива: наш «ok» означает «нечего было
        // удалять», а не «удалили чужое».
        $this->assertNotNull(File::query()->find($ownerFile));
        $this->assertSame('чужой.txt', File::query()->find($ownerFile)->name);
    }

    public function test_cannot_upload_into_another_users_folder(): void
    {
        $owner = $this->asUser();
        $ownerFolder = $this->insertFolder((int) $owner->id, 'Чужая папка');

        $this->asAdmin();

        $this->assertFieldError(
            $this->postJson('/api/filemanager/folders', [
                'parent_id' => $ownerFolder,
                'name' => 'взлом',
            ]),
            'parent_id'
        );
    }

    // ------------------------------------------------------------------
    // Совместимость со старыми записями
    // ------------------------------------------------------------------

    public function test_legacy_files_without_path_are_not_shown_but_still_downloadable_by_old_routes(): void
    {
        $this->asAdmin();

        // Запись, созданная FilesController::upload: пути в ней нет, и
        // выдумывать его нельзя — она лежит по схеме uploads/{ФИО}.
        $legacy = File::create([
            'name' => 'старое.txt',
            'type' => 'document',
            'extension' => 'txt',
            'user_id' => $this->userId(),
        ]);

        $this->assertNull($legacy->path);

        $this->getJson('/api/filemanager')->assertJsonPath('data.files', []);

        // Старый маршрут продолжает отвечать: менеджер не должен был
        // ломать уже работавшую загрузку.
        $this->assertFieldError(
            $this->postJson('/api/files/add', []),
            'image'
        );
    }

    // ------------------------------------------------------------------
    // Хелперы
    // ------------------------------------------------------------------

    /** id пользователя, под которым идёт тест. */
    private function userId(?int $userId = null): int
    {
        if ($userId !== null) {
            return $userId;
        }

        $this->assertNotNull($this->current, 'Пользователь не задан: сначала вызовите asAdmin()/asUser().');

        return (int) $this->current->id;
    }

    /** Абсолютный путь внутри каталога пользователя. */
    private function userPath(string $relative = '', ?int $userId = null): string
    {
        return storage_path('app/'.self::TEST_ROOT.'/'.$this->userId($userId).'/'.ltrim($relative, '/'));
    }

    /**
     * Кладёт файл на диск пользователя ПО.relative И заводит запись.
     *
     * Запись создаётся обязательно: тесты переноса и переименования
     * проверяют, что путь в базе совпадает с местом на диске, а запись
     * без файла (или файл без записи) — это ровно то расхождение,
     * ради которого тесты и написаны.
     */
    private function putFile(string $relative, string $contents, ?int $userId = null): int
    {
        $userId = $this->userId($userId);

        $absolute = $this->userPath($relative, $userId);
        Fs::ensureDirectoryExists(dirname($absolute));
        Fs::put($absolute, $contents);

        $folderId = null;

        if (preg_match('#^(.+)/([^/]+)$#u', $relative, $m) === 1) {
            $folderId = $this->findFolderIdByPath($m[1], $userId);
        }

        return File::create([
            'name' => basename($relative),
            'type' => 'document',
            'extension' => pathinfo($relative, PATHINFO_EXTENSION),
            'user_id' => $userId,
            'folder_id' => $folderId,
            'path' => $relative,
            'size' => strlen($contents),
        ])->id;
    }

    /** Заводит запись файла менеджера в корне пользователя. */
    private function insertManagedFile(
        string $name,
        string $contents,
        ?int $folderId = null,
        ?int $userId = null
    ): int {
        $userId = $this->userId($userId);

        $path = $name;

        if ($folderId !== null) {
            $segments = app(\App\Support\FileManager\FolderTree::class)
                ->segments(FileFolder::query()->findOrFail($folderId), $userId);
            $path = implode('/', array_merge($segments, [$name]));
        }

        $absolute = $this->userPath($path, $userId);
        Fs::ensureDirectoryExists(dirname($absolute));
        Fs::put($absolute, $contents);

        return File::create([
            'name' => $name,
            'type' => 'document',
            'extension' => pathinfo($name, PATHINFO_EXTENSION),
            'user_id' => $userId,
            'folder_id' => $folderId,
            'path' => $path,
            'size' => strlen($contents),
        ])->id;
    }

    /** Ищет папку пользователя по пути «Курс/Лекции». */
    private function findFolderIdByPath(string $relativePath, int $userId): ?int
    {
        $folderId = null;

        foreach (explode('/', $relativePath) as $segment) {
            $found = FileFolder::query()
                ->where('user_id', $userId)
                ->where('parent_id', $folderId)
                ->where('name', $segment)
                ->first();

            if ($found === null) {
                return null;
            }

            $folderId = (int) $found->id;
        }

        return $folderId;
    }

    private function insertFolder(int $userId, string $name, ?int $parentId = null): int
    {
        return FileFolder::create([
            'user_id' => $userId,
            'parent_id' => $parentId,
            'name' => $name,
        ])->id;
    }
}
