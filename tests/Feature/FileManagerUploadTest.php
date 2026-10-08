<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\FileFolder;
use App\Models\FileUploadSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File as Fs;
use Tests\TestCase;

/**
 * Загрузка больших файлов по частям.
 *
 * Проверяется не «файл загрузился», а свойства, без которых докачка и
 * мультизагрузка не работают:
 *
 *  - СОБРАННЫЙ ФАЙЛ РАВЕН ОТПРАВЛЕННОМУ. Это главная проверка: протокол
 *    из пятисот частей, где одна пришла дважды, а другая не пришла
 *    вовсе, даёт «успех» и битый файл, если не сверять итог.
 *  - ЧАСТЬ ПРИНИМАЕТСЯ ТОЛЬКО ПРАВИЛЬНОГО РАЗМЕРА. Иначе объявленный
 *    размер файла ничем не ограничен: клиент объявляет 1 КБ и присылает
 *    1 ГБ частями.
 *  - ДОКАЧКА. Повторный init с теми же метаданными обязан вернуть тот
 *    же upload_id и уже принятые части. Без этого «продолжить после
 *    обрыва» означало бы «начать заново».
 *  - ЧУЖАЯ ЗАГРУЗКА НЕДОСТУПНА. upload_id стоит в URL, поэтому он
 *    обязан быть бесполезным для другого пользователя.
 */
class FileManagerUploadTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    private const TEST_ROOT = 'testing-filemanager-upload';

    /** Мелкий чанк: тест не должен гонять мегабайты ради проверки логики. */
    private const CHUNK = 1024;

    private ?\App\Models\User $current = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'files.disk' => 'local',
            'files.root' => self::TEST_ROOT,
            'files.chunk_bytes' => self::CHUNK,
            'files.max_bytes' => 64 * 1024,
            'files.upload_ttl' => 3600,
        ]);
    }

    protected function tearDown(): void
    {
        Fs::deleteDirectory(storage_path('app/'.self::TEST_ROOT));

        parent::tearDown();
    }

    private function asAdmin(): \App\Models\User
    {
        $this->current = $this->admin();

        return $this->current;
    }

    private function userPath(string $relative = ''): string
    {
        return storage_path('app/'.self::TEST_ROOT.'/'.$this->current->id.'/'.ltrim($relative, '/'));
    }

    // ------------------------------------------------------------------

    public function test_guest_cannot_start_upload(): void
    {
        $this->postJson('/api/filemanager/uploads/init', [
            'filename' => 'файл.bin',
            'size' => 10,
        ])->assertUnauthorized();
    }

    public function test_multi_chunk_file_is_assembled_byte_for_byte(): void
    {
        $this->asAdmin();

        // 2.5 чанка: последняя часть короче остальных — именно этот
        // случай ломает наивную проверку «все части полного размера».
        $payload = random_bytes(self::CHUNK * 2 + 511);

        $file = $this->uploadBytes('отчёт.bin', $payload);

        $this->assertSame('отчёт.bin', $file->name);
        $this->assertSame(strlen($payload), $file->size);
        $this->assertSame('отчёт.bin', $file->path);
        $this->assertSame(
            $payload,
            file_get_contents($this->userPath('отчёт.bin')),
            'Собранный файл обязан совпасть с отправленным побайтово.'
        );

        // Каталог этой загрузки убран: иначе каталог пользователя рос бы
        // с каждой загрузкой. Сам .uploads остаётся — он создаётся по
        // требованию и переиспользуется следующей загрузкой.
        $this->assertDirectoryDoesNotExist($this->userPath('.uploads/'.$file->id));
        $this->assertCount(
            0,
            array_diff((array) scandir($this->userPath('.uploads')), ['.', '..']),
            'После сборки в .uploads не должно остаться каталогов загрузок.'
        );
    }

    public function test_init_reports_chunk_size_and_total_parts(): void
    {
        $this->asAdmin();

        $response = $this->postJson('/api/filemanager/uploads/init', [
            'filename' => 'movie.mp4',
            'size' => self::CHUNK * 3,
        ]);

        $response->assertOk();
        $this->assertSame(self::CHUNK, $response->json('data.chunk_bytes'));
        $this->assertSame(3, $response->json('data.total_chunks'));
        $this->assertSame([], $response->json('data.received'));
        $this->assertSame(40, strlen((string) $response->json('data.upload_id')));
    }

    public function test_empty_file_is_uploaded_without_parts(): void
    {
        $this->asAdmin();

        $file = $this->uploadBytes('пусто.txt', '');

        $this->assertSame(0, $file->size);
        $this->assertSame('', file_get_contents($this->userPath('пусто.txt')));
    }

    public function test_upload_lands_in_requested_folder(): void
    {
        $this->asAdmin();

        $folderId = $this->postJson('/api/filemanager/folders', ['name' => 'Отчёты'])->json('data.id');

        $file = $this->uploadBytes('январь.xlsx', 'данные', $folderId);

        $this->assertSame($folderId, (int) $file->folder_id);
        $this->assertSame('Отчёты/январь.xlsx', $file->path);
        $this->assertFileExists($this->userPath('Отчёты/январь.xlsx'));
    }

    public function test_duplicate_name_gets_suffix_and_both_files_survive(): void
    {
        $this->asAdmin();

        $first = $this->uploadBytes('doc.txt', 'ПЕРВЫЙ');
        $second = $this->uploadBytes('doc.txt', 'ВТОРОЙ');

        $this->assertSame('doc.txt', $first->name);
        $this->assertSame('doc (2).txt', $second->name);
        $this->assertSame('ПЕРВЫЙ', file_get_contents($this->userPath('doc.txt')));
        $this->assertSame('ВТОРОЙ', file_get_contents($this->userPath('doc (2).txt')));
    }

    public function test_extension_is_kept_when_suffix_is_added(): void
    {
        $this->asAdmin();

        $this->uploadBytes('отчёт.pdf', 'один');
        $second = $this->uploadBytes('отчёт.pdf', 'два');

        // Регрессия класса «(2)» не должен оказаться перед точкой.
        $this->assertSame('отчёт (2).pdf', $second->name);
        $this->assertSame('pdf', $second->extension);
        $this->assertSame('document', $second->type);
    }

    // ------------------------------------------------------------------
    // Докачка
    // ------------------------------------------------------------------

    public function test_repeated_init_resumes_the_same_upload(): void
    {
        $this->asAdmin();

        $payload = random_bytes(self::CHUNK * 2);

        $first = $this->postJson('/api/filemanager/uploads/init', [
            'filename' => 'большой.bin',
            'size' => strlen($payload),
            'last_modified' => 1700000000000,
        ])->json('data');

        $this->sendChunk($first['upload_id'], 0, substr($payload, 0, self::CHUNK));
        $this->sendChunk($first['upload_id'], 1, substr($payload, self::CHUNK, self::CHUNK));

        // Повторный init — как после перезагрузки страницы.
        $second = $this->postJson('/api/filemanager/uploads/init', [
            'filename' => 'большой.bin',
            'size' => strlen($payload),
            'last_modified' => 1700000000000,
        ])->json('data');

        $this->assertSame(
            $first['upload_id'],
            $second['upload_id'],
            'Повторный init обязан вернуть ту же загрузку, а не начать новую.'
        );
        $this->assertSame([0, 1], $second['received'], 'Список принятых частей потерян — докачка начнётся с нуля.');
        $this->assertSame(strlen($payload), $second['received_bytes']);

        // И сборка после докачки даёт полный файл.
        $completed = $this->postJson("/api/filemanager/uploads/{$second['upload_id']}/complete");
        $completed->assertCreated();
        $this->assertSame($payload, file_get_contents($this->userPath('большой.bin')));
    }

    public function test_resumed_upload_may_be_redirected_to_another_folder(): void
    {
        $this->asAdmin();

        $payload = random_bytes(self::CHUNK);
        $folderId = $this->postJson('/api/filemanager/folders', ['name' => 'Другая'])->json('data.id');

        $first = $this->postJson('/api/filemanager/uploads/init', [
            'filename' => 'файл.bin',
            'size' => strlen($payload),
        ])->json('data');

        $this->sendChunk($first['upload_id'], 0, $payload);

        // Пользователь открыл другую папку и повторил init.
        $second = $this->postJson('/api/filemanager/uploads/init', [
            'filename' => 'файл.bin',
            'size' => strlen($payload),
            'folder_id' => $folderId,
        ])->json('data');

        $this->assertSame($first['upload_id'], $second['upload_id']);

        $this->postJson("/api/filemanager/uploads/{$second['upload_id']}/complete")->assertCreated();

        $this->assertFileExists($this->userPath('Другая/файл.bin'));
        $this->assertFileDoesNotExist($this->userPath('файл.bin'));
    }

    public function test_completed_upload_cannot_be_completed_twice(): void
    {
        $this->asAdmin();

        $payload = random_bytes(self::CHUNK);
        $init = $this->postJson('/api/filemanager/uploads/init', [
            'filename' => 'файл.bin',
            'size' => strlen($payload),
        ])->json('data');

        $this->sendChunk($init['upload_id'], 0, $payload);
        $this->postJson("/api/filemanager/uploads/{$init['upload_id']}/complete")->assertCreated();

        // Вторая вкладка повторяет complete. Без отметки completed_at
        // файл был бы собран заново и в базе появилась бы вторая запись.
        $this->postJson("/api/filemanager/uploads/{$init['upload_id']}/complete")->assertStatus(422);
        $this->assertSame(1, File::query()->count());
    }

    // ------------------------------------------------------------------
    // Отказы
    // ------------------------------------------------------------------

    public function test_file_larger_than_limit_is_rejected_at_init(): void
    {
        $this->asAdmin();

        // Проверяется объявленный размер, иначе «файл на 4 ГБ» прошёл бы
        // init и обнаружился бы только после заполнения диска.
        $this->assertFieldError(
            $this->postJson('/api/filemanager/uploads/init', [
                'filename' => 'огромный.bin',
                'size' => 1024 * 1024 * 1024,
            ]),
            'size'
        );
    }

    public function test_negative_size_is_rejected(): void
    {
        $this->asAdmin();

        $this->assertFieldError(
            $this->postJson('/api/filemanager/uploads/init', [
                'filename' => 'файл.bin',
                'size' => -1,
            ]),
            'size'
        );
    }

    public function test_chunk_with_traversal_name_is_rejected(): void
    {
        $this->asAdmin();

        // Ключ ошибки — `name`, а не `filename`: проверка имени одна на
        // файлы и папки (EntryName) и не знает, из какого поля пришло
        // имя. Для интерфейса это безразлично — имя файла берётся из
        // выбора файлов, а не из текстового поля.
        $this->assertFieldError(
            $this->postJson('/api/filemanager/uploads/init', [
                'filename' => '../../etc/passwd',
                'size' => 4,
            ]),
            'name'
        );

        $this->assertSame(0, FileUploadSession::query()->count());
    }

    public function test_short_chunk_is_rejected(): void
    {
        $this->asAdmin();

        $init = $this->init('файл.bin', self::CHUNK);

        // Обрыв на середине запроса. Принимать такой кусок нельзя:
        // файл собрался бы короче объявленного и «скачался» бы битым.
        $this->assertFieldError(
            $this->sendChunk($init['upload_id'], 0, 'меньше, чем надо'),
            'index'
        );

        // И собирать такой файл нельзя.
        $this->assertFieldError(
            $this->postJson("/api/filemanager/uploads/{$init['upload_id']}/complete"),
            'upload_id'
        );
    }

    public function test_oversized_chunk_is_rejected(): void
    {
        $this->asAdmin();

        $init = $this->init('файл.bin', self::CHUNK);

        $this->assertFieldError(
            $this->sendChunk($init['upload_id'], 0, str_repeat('x', self::CHUNK + 1)),
            'index'
        );
    }

    public function test_chunk_index_out_of_range_is_rejected(): void
    {
        $this->asAdmin();

        $init = $this->init('файл.bin', self::CHUNK);

        $this->assertFieldError(
            $this->sendChunk($init['upload_id'], 5, str_repeat('x', self::CHUNK)),
            'index'
        );
    }

    public function test_complete_before_all_parts_is_rejected(): void
    {
        $this->asAdmin();

        $payload = random_bytes(self::CHUNK * 2);
        $init = $this->init('файл.bin', strlen($payload));

        $this->sendChunk($init['upload_id'], 0, substr($payload, 0, self::CHUNK));

        $this->assertFieldError(
            $this->postJson("/api/filemanager/uploads/{$init['upload_id']}/complete"),
            'upload_id'
        );

        // Ни файла, ни записи.
        $this->assertSame(0, File::query()->count());
        $this->assertFileDoesNotExist($this->userPath('файл.bin'));
    }

    public function test_other_users_upload_is_not_reachable(): void
    {
        $owner = $this->asAdmin();
        $payload = random_bytes(self::CHUNK);
        $init = $this->init('секрет.bin', strlen($payload));
        $this->sendChunk($init['upload_id'], 0, $payload);

        // Новый пользователь с тем же правом.
        $other = $this->admin();
        $this->current = $other;

        // Ни одна операция чужой загрузки не должна пройти по её id.
        $this->assertFieldError(
            $this->postJson("/api/filemanager/uploads/{$init['upload_id']}/complete"),
            'upload_id'
        );

        $this->call(
            'POST',
            "/api/filemanager/uploads/{$init['upload_id']}/chunk?index=0",
            [],
            [],
            [],
            $this->rawServer(),
            $payload
        )->assertStatus(422);

        // Части остались у владельца нетронутыми.
        $this->assertSame(
            $payload,
            file_get_contents(storage_path('app/'.self::TEST_ROOT.'/'.$owner->id.'/.uploads/'.$init['upload_id'].'/0.part'))
        );
    }

    public function test_abort_removes_temp_parts(): void
    {
        $this->asAdmin();

        $payload = random_bytes(self::CHUNK);
        $init = $this->init('файл.bin', strlen($payload));
        $this->sendChunk($init['upload_id'], 0, $payload);

        $this->deleteJson("/api/filemanager/uploads/{$init['upload_id']}")->assertNoContent();

        $this->assertDirectoryDoesNotExist($this->userPath('.uploads/'.$init['upload_id']));
        $this->assertSame(0, FileUploadSession::query()->count());
    }

    public function test_abort_of_unknown_upload_is_not_an_error(): void
    {
        $this->asAdmin();

        // Сессия могла истечь к моменту, когда интерфейс отменил
        // загрузку. Отказ здесь превращался бы в «ошибка отмены» при
        // вполне успешной отмене.
        $this->deleteJson('/api/filemanager/uploads/'.str_repeat('a', 40))->assertNoContent();
    }

    public function test_upload_into_another_users_folder_is_rejected(): void
    {
        $owner = $this->asAdmin();
        $folderId = FileFolder::create([
            'user_id' => $owner->id,
            'parent_id' => null,
            'name' => 'Чужая папка',
        ])->id;

        $this->current = $this->admin();

        $this->assertFieldError(
            $this->postJson('/api/filemanager/uploads/init', [
                'filename' => 'файл.bin',
                'size' => 10,
                'folder_id' => $folderId,
            ]),
            'folder_id'
        );
    }

    // ------------------------------------------------------------------
    // Обслуживание
    // ------------------------------------------------------------------

    public function test_prune_removes_expired_sessions_and_their_parts(): void
    {
        $this->asAdmin();

        $payload = random_bytes(self::CHUNK);
        $init = $this->init('давно.bin', strlen($payload));
        $this->sendChunk($init['upload_id'], 0, $payload);

        // Свежая сессия жива даже при нулевом TTL.
        $service = app(\App\Support\FileManager\ChunkUploadService::class);
        $service->prune(3600);
        $this->assertSame(1, FileUploadSession::query()->count());

        FileUploadSession::query()->whereKey($init['upload_id'])->update([
            'created_at' => now()->subDays(3),
        ]);

        $result = $service->prune(3600);

        $this->assertSame(1, $result['deleted']);
        $this->assertGreaterThan(0, $result['bytes'], 'Освобождённые байты не посчитаны — чистка молча ничего не делает.');
        $this->assertSame(0, FileUploadSession::query()->count());
        $this->assertDirectoryDoesNotExist($this->userPath('.uploads/'.$init['upload_id']));
    }

    public function test_limits_are_reported_to_the_client(): void
    {
        $this->asAdmin();

        $limits = $this->getJson('/api/filemanager')->json('data.limits');

        // Клиент обязан знать предел ДО выбора файла, а не получать
        // отказ после того, как файл уже выбран.
        $this->assertSame(self::CHUNK, $limits['chunk_bytes']);
        $this->assertSame(64 * 1024, $limits['max_bytes']);
        $this->assertSame(0, $limits['used_bytes']);

        $this->uploadBytes('файл.bin', str_repeat('x', 100));
        $this->assertSame(100, $this->getJson('/api/filemanager')->json('data.limits.used_bytes'));
    }

    // ------------------------------------------------------------------
    // Хелперы
    // ------------------------------------------------------------------

    /**
     * Полный цикл загрузки: init → все части → complete.
     *
     * @return File
     */
    private function uploadBytes(string $name, string $payload, ?int $folderId = null): File
    {
        $init = $this->init($name, strlen($payload), $folderId);

        $chunks = $init['total_chunks'];

        for ($i = 0; $i < $chunks; $i++) {
            $this->sendChunk($init['upload_id'], $i, substr($payload, $i * self::CHUNK, self::CHUNK));
        }

        $response = $this->postJson("/api/filemanager/uploads/{$init['upload_id']}/complete");
        $response->assertCreated();

        $id = $response->json('data.id');

        return File::query()->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function init(string $name, int $size, ?int $folderId = null): array
    {
        $response = $this->postJson('/api/filemanager/uploads/init', array_filter([
            'filename' => $name,
            'size' => $size,
            'mime' => 'application/octet-stream',
            'folder_id' => $folderId,
        ], static fn ($v) => $v !== null));

        $response->assertOk();

        return (array) $response->json('data');
    }

    /** Отправляет часть как «сырое» тело, а не как multipart. */
    private function sendChunk(string $uploadId, int $index, string $body): \Illuminate\Testing\TestResponse
    {
        return $this->call(
            'POST',
            "/api/filemanager/uploads/{$uploadId}/chunk?index={$index}",
            [],
            [],
            [],
            $this->rawServer(),
            $body
        );
    }

    /**
     * HTTP-заголовки для запроса с сырым телом.
     *
     * CONTENT_TYPE обязателен: без него Symfony считает запрос формой и
     * тело попадает в $_POST, а не в php://input — и проверялась бы не
     * та ветка, что работает в бою.
     *
     * @return array<string, string>
     */
    private function rawServer(): array
    {
        return [
            'CONTENT_TYPE' => 'application/octet-stream',
            'HTTP_ACCEPT' => 'application/json',
        ];
    }

    private function assertFieldError(\Illuminate\Testing\TestResponse $response, string $field): void
    {
        $response->assertStatus(422)->assertJsonPath('success', false);

        $this->assertArrayHasKey(
            $field,
            (array) $response->json('error.details'),
            "Ожидалась ошибка валидации по полю {$field}, получено: ".$response->getContent()
        );
    }
}
