<?php

namespace App\Support\FileManager;

use App\Models\File;
use App\Models\FileUploadSession;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Загрузка больших файлов по частям.
 *
 * Зачем части, если можно отправить файл целиком:
 *
 *  1. ЛИМИТ НА ЗАПРОС, А НЕ НА ФАЙЛ. `upload_max_filesize` и
 *     `post_max_size` в PHP, `client_max_body_size` в nginx ограничивают
 *     один запрос. Файл, разрезанный на части, проходит через те же
 *     ограничения, что и файл размером с одну часть. Файл на 8 ГБ
 *     целиком не прошёл бы ни при какой настройке, кроме «снять
 *     ограничения», а это плохая настройка.
 *  2. ДОКАЧКА. Оборванная загрузка продолжается с последней принятой
 *     части. Повторная отправка целого файла начинается с нуля, и на
 *     плохом канале файл в 2 ГБ не загрузится никогда.
 *  3. Мультизагрузка. Несколько файлов идут очередью, у каждого свои
 *     части, и интерфейс показывает прогресс по каждому файлу, а не по
 *     всей пачке сразу.
 *
 * Протокол — четыре вызова:
 *
 *     init    → { filename, size, folder_id, last_modified }
 *             ← { upload_id, chunk_bytes, total_chunks, received[] }
 *     chunk   ← тело запроса (application/octet-stream), GET ?index=N
 *     chunk   × total_chunks
 *     complete→ { file }
 *
 * `init` идемпотентен по отпечатку (user_id, имя, размер, mtime): повторный
 * вызов с теми же данными возвращает тот же upload_id и уже принятые
 * части. Это и есть механика докачки: клиент после перезагрузки
 * страницы просто повторяет init.
 *
 * ЧТО ПРОВЕРЯЕТСЯ. Размер каждой части сверяется с ожидаемым, а не
 * просто суммируется: это делает сумму частей равной объявленному
 * размеру автоматически, и «файл на 10 ГБ, объявленный как 10 ГБ, но
 * собранный из 10 ГБ мусора» невозможно. Плюс сверка итогового файла
 * после сборки.
 */
class ChunkUploadService
{
    public function __construct(
        private readonly Location $location,
        private readonly FolderTree $tree,
        private readonly FileManagerService $files,
    ) {
    }

    // ------------------------------------------------------------------
    // Протокол
    // ------------------------------------------------------------------

    /**
     * Начинает (или продолжает) загрузку.
     *
     * @param  array{filename?: mixed, size?: mixed, mime?: mixed, last_modified?: mixed, folder_id?: mixed}  $input
     * @return array<string, mixed>
     */
    public function init(User $user, array $input): array
    {
        $userId = (int) $user->id;

        $filename = EntryName::cleanShort(
            (string) ($input['filename'] ?? ''),
            'Имя файла'
        );

        $size = $this->readSize($input['size'] ?? null);
        $max = (int) config('files.max_bytes', 0);

        if ($max > 0 && $size > $max) {
            throw ValidationException::withMessages([
                'size' => sprintf(
                    'Файл больше допустимого: %s при лимите %s.',
                    self::humanBytes($size),
                    self::humanBytes($max)
                ),
            ]);
        }

        $folderId = $this->readFolderId($userId, $input['folder_id'] ?? null);
        $lastModified = $this->readInt($input['last_modified'] ?? null);
        $chunkBytes = $this->chunkBytes();

        $fingerprint = FileUploadSession::fingerprint($userId, $filename, $size, $lastModified);

        // Незавершённая загрузка с тем же отпечатком — это она же,
        // просто оборванная. Новая сессия не создаётся: иначе старая
        // осталась бы занимать место до истечения TTL, а клиент
        // каждый раз начинал бы с нуля.
        $session = FileUploadSession::query()
            ->where('user_id', $userId)
            ->where('fingerprint', $fingerprint)
            ->whereNull('completed_at')
            ->orderByDesc('id')
            ->first();

        if ($session !== null) {
            // Каталог назначения мог поменяться (пользователь открыл
            // другую папку между попытками) — это осмысленное действие,
            // поэтому папка обновляется, а размер и имя остаются
            // прежними: файл тот же, принятые части годятся.
            $session->folder_id = $folderId;
            $session->save();

            return $this->describe($session, $userId);
        }

        $session = FileUploadSession::create([
            'id' => $this->newUploadId(),
            'user_id' => $userId,
            'folder_id' => $folderId,
            'filename' => $filename,
            'size' => $size,
            'mime' => $this->readMime($input['mime'] ?? null),
            'fingerprint' => $fingerprint,
            'chunk_bytes' => $chunkBytes,
            'total_chunks' => $this->totalChunks($size, $chunkBytes),
            'received_bytes' => 0,
        ]);

        return $this->describe($session, $userId);
    }

    /**
     * Принимает одну часть.
     *
     * Тело приходит как есть (application/octet-stream), а не как
     * multipart: у multipart есть своя накладная разметка, а часть
     * маленькая и их много — накладная съела бы заметную долю трафика.
     *
     * @return array<string, mixed>
     */
    public function chunk(User $user, string $uploadId, int $index, string $body): array
    {
        $userId = (int) $user->id;
        $session = $this->requireSession($userId, $uploadId);

        if ($session->isCompleted()) {
            throw ValidationException::withMessages(['upload_id' => 'Загрузка уже завершена.']);
        }

        $expected = $this->expectedChunkSize($session, $index);

        if ($expected === null) {
            throw ValidationException::withMessages([
                'index' => 'Номер части вне диапазона.',
            ]);
        }

        // Точное совпадение размера, а не «меньше или равно». Именно
        // поэтому докачка работает: переотправленная часть имеет тот же
        // размер, а «случайно больше» означает, что клиент прислал не
        // тот файл или подменил границы частей.
        if (strlen($body) !== $expected) {
            Log::warning('Размер части не совпал с ожидаемым', [
                'user_id' => $userId,
                'upload_id' => $uploadId,
                'index' => $index,
                'expected' => $expected,
                'got' => strlen($body),
            ]);

            throw ValidationException::withMessages([
                'index' => 'Часть пришла не полностью. Повторите загрузку файла.',
            ]);
        }

        if (! $this->location->writeChunk($userId, $uploadId, $index, $body)) {
            throw new RuntimeException('Не удалось записать часть файла на диск.');
        }

        $session->received_bytes = $this->location->receivedBytes($userId, $uploadId);
        $session->save();

        return [
            'upload_id' => $uploadId,
            'index' => $index,
            'received_bytes' => (int) $session->received_bytes,
            'total_chunks' => (int) $session->total_chunks,
        ];
    }

    /**
     * Собирает файл из частей и заводит запись.
     *
     * Порядок: собрать во временный файл рядом с частями → сверить
     * размер → перенести на место → запись в базе → удалить части.
     * Перенос до записи означает, что неудачная запись в базе оставит
     * файл без записи (его найдёт чистка), а не запись без файла.
     */
    public function complete(User $user, string $uploadId): File
    {
        $userId = (int) $user->id;
        $session = $this->requireSession($userId, $uploadId);

        if ($session->isCompleted()) {
            throw ValidationException::withMessages([
                'upload_id' => 'Загрузка уже завершена.',
            ]);
        }

        $received = $this->location->receivedChunks($userId, $uploadId);
        $expectedIndexes = $this->indexes($session);

        if ($received !== $expectedIndexes) {
            throw ValidationException::withMessages([
                'upload_id' => 'Файл собран не полностью: принято '.count($received).' из '.count($expectedIndexes).' частей.',
            ]);
        }

        $folderId = $session->folder_id === null ? null : (int) $session->folder_id;
        $segments = $this->files->folderSegments($userId, $folderId);
        $name = $this->files->uniqueFileName($userId, $folderId, (string) $session->filename);

        $this->location->ensureUploadDir($userId, $uploadId);
        $staged = $this->location->uploadDir($userId, $uploadId).DIRECTORY_SEPARATOR.'assembled';

        if (! $this->location->assemble($userId, $uploadId, (int) $session->total_chunks, $staged)) {
            throw new RuntimeException('Не удалось собрать файл из частей.');
        }

        // Размер сверяется ДО переноса: переносить битый файл на место и
        // потом спорить с пользователем, откуда он, — худший вариант.
        $assembledSize = $this->location->size($staged);

        if ($assembledSize !== (int) $session->size) {
            @unlink($staged);

            Log::error('Собранный файл не совпал с объявленным размером', [
                'user_id' => $userId,
                'upload_id' => $uploadId,
                'declared' => $session->size,
                'assembled' => $assembledSize,
            ]);

            throw ValidationException::withMessages([
                'size' => 'Собранный файл не совпал с объявленным размером. Загрузка отменена.',
            ]);
        }

        $this->location->makeUserDirectory($userId, $segments);
        $relative = $this->location->relativeFile($segments, $name);
        $target = $this->location->absolute($userId, $relative);

        if (! $this->location->move($staged, $target)) {
            throw new RuntimeException('Не удалось переместить собранный файл на место.');
        }

        @chmod($target, 0644);

        $file = File::create([
            'name' => $name,
            'type' => $this->kindOf($name),
            'extension' => EntryName::extension($name),
            'user_id' => $userId,
            'folder_id' => $folderId,
            'path' => $relative,
            'size' => $assembledSize,
            'mime' => $session->mime,
        ]);

        $session->completed_at = now();
        $session->save();

        $this->location->deleteUploadDir($userId, $uploadId);

        return $file;
    }

    /** Отменяет загрузку и убирает её части. */
    public function abort(User $user, string $uploadId): void
    {
        $userId = (int) $user->id;

        // Сессия ищется по user_id: чужой upload_id должен выглядеть как
        // несуществующий, иначе по нему можно было бы удалять чужие
        // временные каталоги.
        $session = FileUploadSession::query()
            ->where('user_id', $userId)
            ->whereKey($uploadId)
            ->first();

        if ($session === null) {
            return;
        }

        $this->location->deleteUploadDir($userId, $uploadId);
        $session->delete();
    }

    // ------------------------------------------------------------------
    // Обслуживание
    // ------------------------------------------------------------------

    /**
     * Удаляет незавершённые загрузки старше TTL вместе с их частями.
     *
     * Части занимают место на диске, а «бросил и забыл» — обычное
     * поведение: оборвалось соединение, закрыли вкладку, перезагрузили
     * страницу. Без такой чистки каталог пользователя растёт сам по
     * себе и никто не знает, сколько именно.
     *
     * @return array{deleted: int, bytes: int}
     */
    public function prune(?int $ttlSeconds = null): array
    {
        $ttl = $ttlSeconds ?? (int) config('files.upload_ttl', 86400);
        $cutoff = now()->subSeconds(max(60, $ttl));

        $sessions = FileUploadSession::query()
            ->where('created_at', '<', $cutoff)
            ->get();

        $bytes = 0;
        $deleted = 0;

        foreach ($sessions as $session) {
            $dir = $this->location->uploadDir((int) $session->user_id, (string) $session->id);

            $bytes += $this->location->directoryBytes($dir);
            $this->location->deleteUploadDir((int) $session->user_id, (string) $session->id);
            $session->delete();
            $deleted++;
        }

        return ['deleted' => $deleted, 'bytes' => $bytes];
    }

    // ------------------------------------------------------------------
    // Внутреннее
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function describe(FileUploadSession $session, int $userId): array
    {
        return [
            'upload_id' => (string) $session->id,
            'chunk_bytes' => (int) $session->chunk_bytes,
            'total_chunks' => (int) $session->total_chunks,
            'size' => (int) $session->size,
            'filename' => (string) $session->filename,
            'received' => $this->location->receivedChunks($userId, (string) $session->id),
            'received_bytes' => $this->location->receivedBytes($userId, (string) $session->id),
        ];
    }

    /**
     * Ожидаемый размер части.
     *
     * null — индекс вне диапазона. Последняя часть короче остальных,
     * поэтому «ожидаемый размер = chunk_bytes» было бы неверно на
     * последнем куске, а «принимать что пришло» означало бы, что
     * объявленный размер ничем не ограничен.
     */
    private function expectedChunkSize(FileUploadSession $session, int $index): ?int
    {
        $total = (int) $session->total_chunks;

        if ($index < 0 || $index >= $total) {
            return null;
        }

        $chunk = (int) $session->chunk_bytes;
        $start = $index * $chunk;

        if ($start >= (int) $session->size) {
            return null;
        }

        return (int) min($chunk, (int) $session->size - $start);
    }

    /**
     * Список ожидаемых индексов частей.
     *
     * Для пустого файла (size = 0) частей нет, и список пустой — range(0, -1)
     * вернул бы [0], и пустой файл не собрался бы никогда.
     *
     * @return array<int, int>
     */
    private function indexes(FileUploadSession $session): array
    {
        $total = (int) $session->total_chunks;

        return $total <= 0 ? [] : range(0, $total - 1);
    }

    private function totalChunks(int $size, int $chunkBytes): int
    {
        if ($size <= 0) {
            return 0;
        }

        return (int) ceil($size / max(1, $chunkBytes));
    }

    private function chunkBytes(): int
    {
        $configured = (int) config('files.chunk_bytes', 4 * 1024 * 1024);

        // Нижняя граница нужна, чтобы опечатка в настройке (0 или
        // отрицательное значение) не превратила загрузку в миллион
        // запросов по килобайту. Порог низкий намеренно: он защищает
        // от абсурда, а не задаёт «правильный» размер, который всё
        // равно упирается в post_max_size и client_max_body_size.
        return max(1024, $configured);
    }

    private function requireSession(int $userId, string $uploadId): FileUploadSession
    {
        $session = FileUploadSession::query()
            ->where('user_id', $userId)
            ->whereKey($uploadId)
            ->first();

        if ($session === null) {
            throw ValidationException::withMessages(['upload_id' => 'Загрузка не найдена.']);
        }

        return $session;
    }

    private function readSize(mixed $value): int
    {
        $size = $this->readInt($value);

        if ($size === null || $size < 0) {
            throw ValidationException::withMessages([
                'size' => 'Размер файла должен быть неотрицательным целым числом.',
            ]);
        }

        return $size;
    }

    private function readInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        if (is_float($value) && $value >= 0 && floor($value) === $value) {
            return (int) $value;
        }

        return null;
    }

    private function readFolderId(int $userId, mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = $this->readInt($value);

        if ($id === null) {
            throw ValidationException::withMessages(['folder_id' => 'Некорректный идентификатор папки.']);
        }

        if ($this->tree->find($id, $userId) === null) {
            throw ValidationException::withMessages(['folder_id' => 'Папка не найдена.']);
        }

        return $id;
    }

    /**
     * MIME-тип от клиента.
     *
     * Берётся как есть, но только если похож на тип: строка попадает в
     * заголовок Content-Type при отдаче файла, и произвольное значение
     * из тела запроса — это способ подсунуть в ответ свой заголовок.
     * Поэтому сверяется с форматом «тип/подтип» и обрезается по длине.
     */
    private function readMime(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (preg_match('#^[a-z0-9][a-z0-9!\#$&^_.+-]{0,60}/[a-z0-9][a-z0-9!\#$&^_.+-]{0,60}$#i', $value) !== 1) {
            return null;
        }

        return strtolower($value);
    }

    /**
     * Тип файла для колонки files.type.
     *
     * Словарь взят у FilesController::getType, чтобы старые и новые
     * записи читались одинаково. 'other' — потому что колонка NOT NULL:
     * без значения перечисленного типа (например .zip или .json)
     * запись просто не создалась бы, а это тихий отказ всей загрузки.
     */
    private function kindOf(string $name): string
    {
        return match (EntryName::extension($name)) {
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg' => 'image',
            'mp3', 'ogg', 'mpga', 'wav', 'm4a', 'flac' => 'audio',
            'mp4', 'mpeg', 'webm', 'mov', 'avi' => 'video',
            'doc', 'docx', 'pdf', 'odt', 'rtf', 'txt', 'xls', 'xlsx', 'ppt', 'pptx' => 'document',
            default => 'other',
        };
    }

    /**
     * Идентификатор загрузки.
     *
     * sha1 случайных 16 байт: значение попадает в URL и в имя каталога,
     * поэтому оно должно быть непредсказуемым (иначе можно было бы
     * загадать чужой upload_id) и безопасным для имени каталога
     * (только [0-9a-f]).
     */
    private function newUploadId(): string
    {
        return sha1(random_bytes(16));
    }

    /** Человекочитаемый размер: байты для отладки, «человеческие» — в UI. */
    public static function humanBytes(int $bytes): string
    {
        $units = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
        $value = (float) $bytes;
        $index = 0;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return ($index === 0 ? (string) $bytes : number_format($value, 1, ',', ' ')).' '.$units[$index];
    }
}
