<?php

namespace App\Http\Controllers;

use App\Support\FileManager\FileManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Файловый менеджер: каталоги, папки, файлы.
 *
 * Контроллер тонкий намеренно. В нём нет ни проверки имён, ни сборки
 * путей, ни порядка «сначала диск, потом база» — всё это в
 * App\Support\FileManager. Здесь только разбор запроса и вызов сервиса.
 *
 * Права — на маршрутах (routes/api.php), а не здесь: одна проверка в
 * одном месте, и её видно целиком, а не выборочно в методах.
 *
 * ВАЖНО ПРО ВЛАДЕНИЕ. Каждый вызов сервиса получает ТЕКУЩЕГО
 * пользователя, и все выборки идут по user_id. Чужой идентификатор
 * даёт «не найдено», а не «нет прав»: иначе по id можно было бы
 * узнать, что файл существует, и перебирать содержимое чужого
 * каталога по реакции на запрос.
 */
class FileManagerController extends Controller
{
    public function __construct(private readonly FileManagerService $files)
    {
    }

    /**
     * Содержимое каталога.
     *
     * GET /api/filemanager?folder_id=…
     */
    public function index(Request $request): JsonResponse
    {
        $this->validate($request, [
            // nullable + integer: папка в корне — это отсутствие
            // folder_id, а не 0. Приводим к null явно, потому что
            // numericQuery/приведение строки в контроллерах уже давали
            // «NaN» в похожих местах.
            'folder_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($this->files->list($request->user(), $this->folderId($request)));
    }

    // ------------------------------------------------------------------
    // Папки
    // ------------------------------------------------------------------

    /**
     * Всё дерево папок — для выбора папки назначения.
     *
     * GET /api/filemanager/folders
     *
     * Отдельный маршрут, а не флаг у index: содержимое каталога и список
     * папок отвечают на разные вопросы, и объединять их значило бы
     * тянуть дерево при каждом открытии каталога ради одного диалога.
     */
    public function folders(Request $request): JsonResponse
    {
        return response()->json($this->files->folderTree((int) $request->user()->id));
    }

    /**
     * Создать папку.
     *
     * POST /api/filemanager/folders  { parent_id?, name }
     */
    public function storeFolder(Request $request): JsonResponse
    {
        $validated = $this->validate($request, [
            'parent_id' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $folder = $this->files->createFolder(
            $request->user(),
            $this->intOrNull($validated['parent_id'] ?? null),
            (string) $validated['name']
        );

        return response()->json($folder->toManagerArray(), 201);
    }

    /**
     * Переименовать папку.
     *
     * PATCH /api/filemanager/folders/{folder}  { name }
     */
    public function updateFolder(Request $request, string $folder): JsonResponse
    {
        $validated = $this->validate($request, [
            'name' => ['required', 'string', 'max:255'],
        ]);

        $updated = $this->files->renameFolder($request->user(), (int) $folder, (string) $validated['name']);

        return response()->json($updated->toManagerArray());
    }

    /**
     * Перенести папку в другой каталог.
     *
     * POST /api/filemanager/folders/{folder}/move  { parent_id|null }
     *
     * Отдельный метод, а не переиспользование update: перенос меняет
     * parent_id и переписывает пути файлов внутри, тогда как
     * переименование меняет только имя. Смешивать их в одном методе
     * значило бы различать их по переданному полю.
     */
    public function moveFolder(Request $request, string $folder): JsonResponse
    {
        $validated = $this->validate($request, [
            'parent_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $moved = $this->files->moveFolder(
            $request->user(),
            (int) $folder,
            $this->intOrNull($validated['parent_id'] ?? null)
        );

        return response()->json($moved->toManagerArray());
    }

    /**
     * Удалить папку.
     *
     * DELETE /api/filemanager/folders/{folder}?recursive=1
     *
     * Непустая папка без recursive даёт 422 и текст «подтвердите
     * удаление содержимого» — интерфейс после этого повторяет запрос с
     * флагом. Так «удалить папку» нельзя случайно превратить в «удалить
     * 300 файлов».
     */
    public function destroyFolder(Request $request, string $folder): JsonResponse
    {
        $this->validate($request, [
            'recursive' => ['nullable', 'boolean'],
        ]);

        $result = $this->files->deleteFolder(
            $request->user(),
            (int) $folder,
            $request->boolean('recursive')
        );

        return response()->json($result);
    }

    // ------------------------------------------------------------------
    // Файлы
    // ------------------------------------------------------------------

    /**
     * Переименовать файл.
     *
     * PATCH /api/filemanager/files/{file}  { name }
     */
    public function updateFile(Request $request, string $file): JsonResponse
    {
        $validated = $this->validate($request, [
            'name' => ['required', 'string', 'max:255'],
        ]);

        $updated = $this->files->renameFile($request->user(), (int) $file, (string) $validated['name']);

        return response()->json($updated->toManagerArray());
    }

    /**
     * Перенести несколько файлов в каталог.
     *
     * POST /api/filemanager/files/move  { ids: [...], folder_id|null }
     */
    public function moveFiles(Request $request): JsonResponse
    {
        $ids = $this->validateIds($request);

        $validated = $this->validate($request, [
            'folder_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $result = $this->files->moveFiles(
            $request->user(),
            $ids,
            $this->intOrNull($validated['folder_id'] ?? null)
        );

        return response()->json($result);
    }

    /**
     * Удалить несколько файлов.
     *
     * POST /api/filemanager/files/delete  { ids: [...] }
     *
     * POST, а не DELETE с телом: тело у DELETE часть клиентов и
     * прокси выбрасывает, и запрос молча приходил бы без ids.
     */
    public function destroyFiles(Request $request): JsonResponse
    {
        $ids = $this->validateIds($request);

        return response()->json($this->files->deleteFiles($request->user(), $ids));
    }

    /**
     * Удалить один файл.
     *
     * DELETE /api/filemanager/files/{file}
     */
    public function destroyFile(Request $request, string $file): JsonResponse
    {
        return response()->json($this->files->deleteFiles($request->user(), [(int) $file]));
    }

    /**
     * Скачать файл.
     *
     * GET /api/filemanager/files/{file}/download
     *
     * Поток отдаёт сам Laravel, а не X-Accel-Redirect: файлы менеджера
     * лежат вне каталога контента курсов, и их отдача не должна
     * зависеть от настройки content_delivery. Кроме того, поток от
     * Laravel корректно отдаёт Range-запросы и докачку, а
     * клиентское имя файла (Content-Disposition) выставляется Symfony с
     * учётом кириллицы.
     */
    public function download(Request $request, string $file): BinaryFileResponse|StreamedResponse
    {
        $record = $this->files->requireFile((int) $request->user()->id, (int) $file);
        $absolute = $this->files->absolutePathFor($request->user(), $record);

        if (! is_file($absolute)) {
            // Запись есть, файла нет — состояние после сорванного
            // удаления. Пользователю это не его ошибка, поэтому 404 с
            // понятным текстом, а не пустой файл нулевого размера.
            throw ValidationException::withMessages([
                'file' => 'Файл не найден на диске. Обновите список.',
            ]);
        }

        $mime = is_string($record->mime) && $record->mime !== ''
            ? $record->mime
            : 'application/octet-stream';

        return response()->download($absolute, (string) $record->name, [
            'Content-Type' => $mime,
            // Файл пользователя приватен: общий кэш отдал бы его тому,
            // кто ссылку не получал.
            'Cache-Control' => 'private, no-store',
        ]);
    }

    // ------------------------------------------------------------------
    // Внутреннее
    // ------------------------------------------------------------------

    /**
     * Идентификаторы файлов для массовой операции.
     *
     * @return array<int, int>
     */
    private function validateIds(Request $request): array
    {
        $max = (int) config('files.max_bulk', 500);

        $validated = $this->validate($request, [
            'ids' => ['required', 'array', 'min:1', 'max:'.$max],
            // distinct убирает повторы: без него файл из выделения дважды
            // попадал бы в один запрос и второй проход упал бы на
            // «уже перенесён».
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);

        return array_map('intval', $validated['ids']);
    }

    private function folderId(Request $request): ?int
    {
        return $this->intOrNull($request->query('folder_id'));
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
