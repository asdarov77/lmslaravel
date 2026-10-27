<?php

namespace App\Support\FileManager;

use App\Models\File;
use App\Models\FileFolder;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Операции файлового менеджера над папками и файлами.
 *
 * Слой существует, чтобы контроллер оставался тонким, а решения — в
 * одном месте. Три вещи, которые обязаны быть согласованы между собой и
 * поэтому живут здесь, а не вызывающем коде:
 *
 *  1. ЗАПИСЬ В БАЗЕ И НА ДИСК. Каждая операция меняет и то, и другое.
 *     Порядок фиксирован: сначала диск, потом база. Обратный порядок
 *     даёт запись о файле, которого нет (пользователь видит файл,
 *     открыть его нельзя); при отказе базы наоборот остаётся файл без
 *     записи, и он занимает имя молча. Поэтому при отказе базы диск
 *     откатывается, а при отказе диска база не трогается.
 *
 *  2. ВЛАДЕНИЕ. Все выборки по user_id. Чужой id обязан выглядеть как
 *     отсутствующий, а не как запрет: «нет такого файла» не отличает
 *     «нет» от «нельзя», поэтому чужой файл нельзя даже обнаружить.
 *
 *  3. ЦЕЛОСТНОСТЬ ПУТИ. Запись идёт только по пути, проверенному
 *     Location::isInsideUserRoot. Проверка имени (EntryName) и проверка
 *     итогового пути — разные вещи: безопасное имя не спасает, если
 *     родительский каталог оказался симлинком.
 */
class FileManagerService
{
    public function __construct(
        private readonly Location $location,
        private readonly FolderTree $tree,
    ) {
    }

    // ------------------------------------------------------------------
    // Чтение
    // ------------------------------------------------------------------

    /**
     * Содержимое каталога: сам каталог, хлебные крошки, папки, файлы и
     * пределы загрузки.
     *
     * Папки и файлы отдаются двумя отдельными массивами, а не одним
     * списком с полем type: интерфейсу их всё равно нужно рисовать
     * по-разному, а смешанный список заставляет каждую строку проверять
     * «а это папка?», и рано или поздно одна забывает.
     *
     * @return array<string, mixed>
     */
    public function list(User $user, ?int $folderId): array
    {
        $userId = (int) $user->id;
        $folder = $this->tree->find($folderId, $userId);

        // Запрошенную папку, которой нет, отличаем от существующей:
        // иначе интерфейс молча откроет корень, и пользователь решит,
        // что папку удалили.
        if ($folderId !== null && $folder === null) {
            throw ValidationException::withMessages([
                'folder_id' => 'Папка не найдена.',
            ]);
        }

        $segments = $this->tree->segments($folder, $userId);

        $folders = FileFolder::query()
            ->where('user_id', $userId)
            ->childrenOf($folder?->id === null ? null : (int) $folder->id)
            ->withCount('files')
            ->orderBy('name')
            ->get()
            ->map(fn (FileFolder $f): array => $f->toManagerArray() + [
                'item_count' => (int) $f->files_count,
            ])
            ->values()
            ->all();

        $files = File::query()
            ->where('user_id', $userId)
            ->inFolder($folder?->id === null ? null : (int) $folder->id)
            ->managed()
            ->orderBy('name')
            ->get()
            ->map(fn (File $f): array => $f->toManagerArray())
            ->values()
            ->all();

        return [
            'folder' => $folder?->toManagerArray(),
            'breadcrumb' => $this->tree->breadcrumb($folder, $userId),
            'folders' => $folders,
            'files' => $files,
            'limits' => $this->limits($userId),
        ];
    }

    /**
     * Всё дерево папок пользователя списком — для выбора папки назначения.
     *
     * Отдельный метод, а не обход содержимого каталогов: диалог «переместить
     * в» обязан показать ВСЕ папки сразу, иначе перенос в папку, которая
     * сейчас не открыта, был бы невозможен. Обход по каталогам дал бы ещё и
     * N запросов на один диалог.
     *
     * Возвращается плоский список с полным путём (`Отчёты/2026`), потому
     * что в выпадающем списке два одинаковых имени «Архив» из разных
     * каталогов должны быть различимы.
     *
     * @return array<int, array{id: int, name: string, path: string, parent_id: int|null, depth: int}>
     */
    public function folderTree(int $userId): array
    {
        $folders = FileFolder::query()
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get();

        $byId = [];
        $depth = [];

        foreach ($folders as $folder) {
            $byId[(int) $folder->id] = $folder;
            $depth[(int) $folder->id] = 0;
        }

        $out = [];

        foreach ($folders as $folder) {
            $id = (int) $folder->id;

            // Глубина считается по родителям. Ограничение защищает от
            // кольца в данных: без него подъём не закончился бы.
            $cursor = $folder->parent_id === null ? null : ($byId[(int) $folder->parent_id] ?? null);
            $level = 0;

            while ($cursor !== null && $level < 64) {
                $level++;
                $nextId = $cursor->parent_id === null ? null : (int) $cursor->parent_id;
                $cursor = $nextId === null ? null : ($byId[$nextId] ?? null);
            }

            $depth[$id] = $level;

            $segments = $this->tree->segments($folder, $userId);

            $out[] = [
                'id' => $id,
                'name' => (string) $folder->name,
                'path' => implode('/', $segments),
                'parent_id' => $folder->parent_id === null ? null : (int) $folder->parent_id,
                'depth' => $level,
            ];
        }

        return $out;
    }

    /**
     * Пределы загрузки для интерфейса.
     *
     * Клиент обязан знать предел ДО выбора файла, а не после отказа
     * сервера. Поэтому значения отдаёт сервер, а не зашиты в форму.
     *
     * @return array<string, int>
     */
    public function limits(int $userId): array
    {
        return [
            'max_bytes' => (int) config('files.max_bytes', 0),
            'chunk_bytes' => (int) config('files.chunk_bytes', 4 * 1024 * 1024),
            'used_bytes' => (int) File::query()
                ->where('user_id', $userId)
                ->managed()
                ->sum('size'),
        ];
    }

    // ------------------------------------------------------------------
    // Папки
    // ------------------------------------------------------------------

    /** Создать папку в каталоге $parentId. */
    public function createFolder(User $user, ?int $parentId, string $rawName): FileFolder
    {
        $userId = (int) $user->id;
        $parent = $this->tree->find($parentId, $userId);

        if ($parentId !== null && $parent === null) {
            throw ValidationException::withMessages(['parent_id' => 'Родительская папка не найдена.']);
        }

        $name = EntryName::clean($rawName, 'Имя папки');
        $finalName = $this->tree->uniqueName($userId, $parent?->id === null ? null : (int) $parent->id, $name);

        $segments = $this->tree->segments($parent, $userId);
        $this->location->makeUserDirectory($userId, array_merge($segments, [$finalName]));

        return FileFolder::create([
            'user_id' => $userId,
            'parent_id' => $parent?->id,
            'name' => $finalName,
        ]);
    }

    /** Переименовать папку (файл на диске переезжает вместе с содержимым). */
    public function renameFolder(User $user, int $folderId, string $rawName): FileFolder
    {
        $userId = (int) $user->id;
        $folder = $this->requireFolder($userId, $folderId);
        $name = EntryName::clean($rawName, 'Имя папки');

        $parentId = $folder->parent_id === null ? null : (int) $folder->parent_id;
        $finalName = $this->tree->uniqueName($userId, $parentId, $name, (int) $folder->id);

        $fromSegments = $this->tree->segments($folder, $userId);
        $toSegments = array_merge($this->tree->segments($this->tree->find($parentId, $userId), $userId), [$finalName]);

        $from = $this->location->absolute($userId, $this->location->relativeDir($fromSegments));
        $to = $this->location->absolute($userId, $this->location->relativeDir($toSegments));

        if ($from === $to) {
            $folder->name = $finalName;
            $folder->save();

            return $folder;
        }

        // Каталога на диске может не быть (создан в базе, каталог удалили
        // руками). Переименование тогда не переносит ничего и не должно
        // падать: имя в базе обновляется, содержимое пользователь
        // потерял раньше и сейчас.
        if ($this->location->exists($from)) {
            if (! $this->location->move($from, $to)) {
                throw new RuntimeException('Не удалось переименовать каталог на диске.');
            }
        } else {
            $this->location->makeUserDirectory($userId, $toSegments);
        }

        $folder->name = $finalName;
        $folder->save();

        return $this->rewriteDescendantPaths($folder, $userId);
    }

    /**
     * Перенести папку в другой каталог.
     *
     * Файлы внутри получают новые path. Это обязательная часть: path —
     * источник истины о том, где лежит файл, и оставшийся старым он
     * после переноса папки указывал бы в никуда.
     */
    public function moveFolder(User $user, int $folderId, ?int $newParentId): FileFolder
    {
        $userId = (int) $user->id;
        $folder = $this->requireFolder($userId, $folderId);
        $parent = $this->tree->find($newParentId, $userId);

        if ($newParentId !== null && $parent === null) {
            throw ValidationException::withMessages(['parent_id' => 'Папка назначения не найдена.']);
        }

        // Сначала кольцо, потом всё остальное: иначе проверка зацикливания
        // выполнялась бы уже на изменённом дереве.
        $this->tree->assertNoCycle($folder, $newParentId, $userId);

        $targetParentId = $parent?->id === null ? null : (int) $parent->id;

        if ($targetParentId === ($folder->parent_id === null ? null : (int) $folder->parent_id)) {
            return $folder;
        }

        $finalName = $this->tree->uniqueName($userId, $targetParentId, $folder->name, (int) $folder->id);

        $oldParentId = $folder->parent_id === null ? null : (int) $folder->parent_id;
        $oldParentSegments = $this->tree->segments($this->tree->find($oldParentId, $userId), $userId);

        $fromSegments = $this->tree->segments($folder, $userId);
        $toSegments = array_merge(
            $this->tree->segments($parent, $userId),
            [$finalName]
        );

        $from = $this->location->absolute($userId, $this->location->relativeDir($fromSegments));
        $to = $this->location->absolute($userId, $this->location->relativeDir($toSegments));

        if ($from !== $to) {
            $this->location->makeUserDirectory($userId, $this->tree->segments($parent, $userId));

            if ($this->location->exists($from) && ! $this->location->move($from, $to)) {
                throw new RuntimeException('Не удалось перенести каталог на диске.');
            }

            // Прежний родитель мог опустеть. Каталог без записи в базе —
            // мусор: он занимает место и виден пользователю, который
            // заглянет на диск, но в интерфейсе его нет.
            $this->location->pruneEmptyDirs($userId, $oldParentSegments);
        }

        $folder->name = $finalName;
        $folder->parent_id = $targetParentId;
        $folder->save();

        return $this->rewriteDescendantPaths($folder, $userId);
    }

    /**
     * Удалить папку.
     *
     * По умолчанию — только если она пуста. Удаление непустой папки
     * одним действием («удалил папку с 300 файлами, потому что хотел
     * убрать одну вложенную») — это способ потерять работу, поэтому
     * непустая папка требует явного $recursive.
     *
     * @return array{deleted_folders: int, deleted_files: int}
     */
    public function deleteFolder(User $user, int $folderId, bool $recursive): array
    {
        $userId = (int) $user->id;
        $folder = $this->requireFolder($userId, $folderId);

        $descendants = $this->tree->descendants((int) $folder->id, $userId);

        if (! $recursive && ($descendants->isNotEmpty() || $this->countFiles($userId, [(int) $folder->id]) > 0)) {
            throw ValidationException::withMessages([
                'folder_id' => 'Папка не пуста. Подтвердите удаление содержимого.',
            ]);
        }

        $folderIds = $descendants->map(fn (FileFolder $f): int => (int) $f->id)
            ->push((int) $folder->id)
            ->all();

        $deletedFiles = 0;

        foreach ($this->filesInFolders($userId, $folderIds) as $file) {
            if ($this->removeFileFromDisk($userId, $file)) {
                $deletedFiles++;
            }
        }

        File::query()->where('user_id', $userId)->whereIn('folder_id', $folderIds)->delete();
        FileFolder::query()->where('user_id', $userId)->whereIn('id', $folderIds)->delete();

        // Каталог на диске снимаем ПОСЛЕ базы: если удаление каталога
        // не удалось (нет прав на каталог, занят файл), папка уже не
        // показывается, и оставшийся каталог — безобидный мусор, который
        // чистится отдельной командой. Обратный порядок дал бы папку в
        // интерфейсе без единого файла внутри.
        $parentSegments = $this->tree->segments(
            $this->tree->find($folder->parent_id === null ? null : (int) $folder->parent_id, $userId),
            $userId
        );
        $segments = $this->tree->segments($folder, $userId);
        $absolute = $this->location->absolute($userId, $this->location->relativeDir($segments));

        if ($this->location->exists($absolute)) {
            $removed = $this->location->deleteDirectory($absolute);

            if (! $removed) {
                Log::warning('Каталог файлового менеджера не удалён с диска', [
                    'user_id' => $userId,
                    'path' => $absolute,
                ]);
            }
        }

        // Родитель удалённой папки мог опустеть целиком.
        $this->location->pruneEmptyDirs($userId, $parentSegments);

        return [
            'deleted_folders' => count($folderIds),
            'deleted_files' => $deletedFiles,
        ];
    }

    // ------------------------------------------------------------------
    // Файлы
    // ------------------------------------------------------------------

    /** Переименовать файл. */
    public function renameFile(User $user, int $fileId, string $rawName): File
    {
        $userId = (int) $user->id;
        $file = $this->requireFile($userId, $fileId);
        $name = EntryName::clean($rawName, 'Имя файла');

        $folderId = $file->folder_id === null ? null : (int) $file->folder_id;
        $finalName = $this->uniqueFileName($userId, $folderId, $name, (int) $file->id);

        $dir = $this->folderSegments($userId, $folderId);
        $oldRelative = (string) $file->path;
        $newRelative = $this->location->relativeFile($dir, $finalName);

        if ($oldRelative !== $newRelative) {
            $from = $this->location->absolute($userId, $oldRelative);
            $to = $this->location->absolute($userId, $newRelative);

            if ($this->location->exists($from)) {
                $this->location->makeUserDirectory($userId, $dir);

                if (! $this->location->move($from, $to)) {
                    throw new RuntimeException('Не удалось переименовать файл на диске.');
                }
            }
        }

        $file->name = $finalName;
        $file->extension = EntryName::extension($finalName);
        $file->path = $newRelative;
        $file->save();

        return $file;
    }

    /**
     * Перенести файлы в каталог.
     *
     * @param  array<int, int>  $fileIds
     * @return array{moved: int, skipped: int}
     */
    public function moveFiles(User $user, array $fileIds, ?int $targetFolderId): array
    {
        $userId = (int) $user->id;
        $target = $this->tree->find($targetFolderId, $userId);

        if ($targetFolderId !== null && $target === null) {
            throw ValidationException::withMessages(['folder_id' => 'Папка назначения не найдена.']);
        }

        $targetId = $target?->id === null ? null : (int) $target->id;
        $segments = $this->tree->segments($target, $userId);

        $moved = 0;
        $skipped = 0;

        $this->location->makeUserDirectory($userId, $segments);

        // Снимок занятых имён каталога назначения берётся ОДИН раз на
        // весь перенос. Проверка «занято ли имя» внутри цикла обращалась
        // бы к базе и к диску по разу на файл, и перенос сотни файлов
        // превращался бы в сотни одинаковых выборок. Снимок пополняется
        // по мере переноса, поэтому два файла с одинаковым именем из
        // РАЗНЫХ каталогов не схлопнутся в один.
        $taken = $this->takenFileNames($userId, $targetId);

        foreach ($this->filesByIds($userId, $fileIds) as $file) {
            if (($file->folder_id === null ? null : (int) $file->folder_id) === $targetId) {
                $skipped++;

                continue;
            }

            // Имя уточняется по каталогу НАЗНАЧЕНИЯ: там может лежать
            // файл с тем же именем, и перенос без проверки его затёр бы.
            $name = $this->pickFileName(
                (string) $file->name,
                $taken,
                fn (string $candidate): bool => $this->location->existsOnDisk($userId, $segments, $candidate)
            );

            $taken[] = $name;

            $newRelative = $this->location->relativeFile($segments, $name);
            $from = $this->location->absolute($userId, (string) $file->path);
            $to = $this->location->absolute($userId, $newRelative);

            if ($from !== $to) {
                if (! $this->location->exists($from) || ! $this->location->move($from, $to)) {
                    // Файла на диске нет: запись всё равно переводим в
                    // новый каталог, иначе она навсегда осталась бы в
                    // старом и пользователь не смог бы ею управлять.
                    Log::warning('Файл не найден на диске при переносе', [
                        'file_id' => $file->id,
                        'path' => $file->path,
                    ]);
                }
            }

            $file->folder_id = $targetId;
            $file->name = $name;
            $file->path = $newRelative;
            $file->save();
            $moved++;
        }

        return ['moved' => $moved, 'skipped' => $skipped];
    }

    /**
     * Удалить файлы с диска и из базы.
     *
     * @param  array<int, int>  $fileIds
     * @return array{deleted: int, missing: int}
     */
    public function deleteFiles(User $user, array $fileIds): array
    {
        $userId = (int) $user->id;

        $deleted = 0;
        $missing = 0;

        foreach ($this->filesByIds($userId, $fileIds) as $file) {
            if ($this->removeFileFromDisk($userId, $file)) {
                $deleted++;
            } else {
                $missing++;
            }
        }

        File::query()
            ->where('user_id', $userId)
            ->whereIn('id', $fileIds)
            ->delete();

        return ['deleted' => $deleted, 'missing' => $missing];
    }

    /** Файл пользователя для скачивания/просмотра или 404. */
    public function requireFile(int $userId, int $fileId): File
    {
        $file = File::query()
            ->where('user_id', $userId)
            ->whereKey($fileId)
            ->managed()
            ->first();

        if ($file === null) {
            throw ValidationException::withMessages(['file_id' => 'Файл не найден.']);
        }

        return $file;
    }

    public function requireFolder(int $userId, int $folderId): FileFolder
    {
        $folder = $this->tree->find($folderId, $userId);

        if ($folder === null) {
            throw ValidationException::withMessages(['folder_id' => 'Папка не найдена.']);
        }

        return $folder;
    }

    /**
     * Абсолютный путь файла с проверкой, что он внутри каталога
     * пользователя.
     *
     * Проверка обязана быть именно здесь: path приходит из базы, но
     * база могла достаться из старого состояния, из ручного UPDATE или
     * из копии. Сверка realpath с корнем пользователя — последняя
     * линия перед тем, как отдать файл наружу.
     */
    public function absolutePathFor(User $user, File $file): string
    {
        $userId = (int) $user->id;
        $relative = (string) $file->path;

        if (! $this->location->isInsideUserRoot($userId, $relative)) {
            Log::error('Путь файла вне каталога пользователя — отказ', [
                'user_id' => $userId,
                'file_id' => $file->id,
                'path' => $relative,
            ]);

            throw ValidationException::withMessages(['file' => 'Файл недоступен.']);
        }

        return $this->location->absolute($userId, $relative);
    }

    // ------------------------------------------------------------------
    // Внутреннее
    // ------------------------------------------------------------------

    /**
     * Сегменты каталога по folder_id.
     *
     * @return array<int, string>
     */
    public function folderSegments(int $userId, ?int $folderId): array
    {
        return $this->tree->segments($this->tree->find($folderId, $userId), $userId);
    }

    /**
     * Свободное имя файла в каталоге.
     *
     * Проверяются и таблица, и диск. Только база пропустила бы файл,
     * оставшийся на диске без записи: он занимает имя молча, и файл
     * перезапишет его содержимое, не спросив.
     */
    public function uniqueFileName(int $userId, ?int $folderId, string $desired, ?int $ignoreId = null): string
    {
        $taken = $this->takenFileNames($userId, $folderId, $ignoreId);
        $segments = $this->folderSegments($userId, $folderId);

        return $this->pickFileName(
            $desired,
            $taken,
            // Каждый кандидат дополнительно сверяется с диском: снимок
            // имён из базы не меняется, а подставное имя может совпасть
            // с файлом, оставшимся на диске без записи.
            fn (string $candidate): bool => $this->location->existsOnDisk($userId, $segments, $candidate)
        );
    }

    /**
     * Имена файлов каталога, занятые в базе.
     *
     * Отдельный метод, потому что массовый перенос берёт снимок ОДИН раз
     * на весь каталог назначения, а не по запросу на каждый файл: иначе
     * перенос сотни файлов — это сотня одинаковых выборок и сотня
     * обходов дерева папок.
     *
     * @return array<int, string>
     */
    private function takenFileNames(int $userId, ?int $folderId, ?int $ignoreId = null): array
    {
        return File::query()
            ->where('user_id', $userId)
            ->inFolder($folderId)
            ->managed()
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->pluck('name')
            ->all();
    }

    /**
     * Подбирает имя, которого нет ни в $taken, ни по $onDisk.
     *
     * @param  array<int, string>  $taken
     * @param  callable(string): bool  $onDisk  «занято на диске»
     */
    private function pickFileName(string $desired, array $taken, callable $onDisk): string
    {
        if (! in_array($desired, $taken, true) && ! $onDisk($desired)) {
            return $desired;
        }

        // 500 попыток хватит для любого каталога, который человек
        // способен наполнить; выход по счётчику нужен, чтобы при тысячах
        // совпадений не уйти в бесконечный цикл.
        for ($i = 2; $i <= 500; $i++) {
            $candidate = EntryName::numbered($desired, $i);

            if (! in_array($candidate, $taken, true) && ! $onDisk($candidate)) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages(['name' => 'Не удалось подобрать свободное имя файла.']);
    }

    /**
     * Перезаписывает path файлов самой папки и всех её потомков.
     *
     * Считает пути заново от корня для всей ветки: пересчёт «старый путь
     * минус старый префикс плюс новый префикс» требует аккуратной работы
     * со строками, а пересборка по дереву ошибок не содержит.
     *
     * САМА папка в обходе обязательна. Раньше обход шёл только по
     * потомкам, и файл, лежащий прямо в переименованной папке, сохранял
     * старый path: запись указывала на каталог, которого уже нет, файл
     * не открывался, и починить это можно было только вручную.
     */
    private function rewriteDescendantPaths(FileFolder $folder, int $userId): FileFolder
    {
        $branch = $this->tree->descendants((int) $folder->id, $userId)->prepend($folder);

        foreach ($branch as $node) {
            $dir = $this->tree->segments($node, $userId);
            $folderId = (int) $node->id;

            File::query()
                ->where('user_id', $userId)
                ->where('folder_id', $folderId)
                ->managed()
                ->get()
                ->each(function (File $file) use ($dir): void {
                    $file->path = $this->location->relativeFile($dir, (string) $file->name);
                    $file->save();
                });
        }

        return $folder;
    }

    /** @return Collection<int, File> */
    private function filesInFolders(int $userId, array $folderIds): Collection
    {
        return File::query()
            ->where('user_id', $userId)
            ->whereIn('folder_id', $folderIds)
            ->managed()
            ->get();
    }

    /**
     * Файлы пользователя ПО ИДЕНТИФИКАТОРАМ.
     *
     * Отдельный метод рядом с filesInFolders() — намеренно: эти два
     * метода принимают похожие на вид списки id, и когда их перепутали,
     * перенос и удаление молча «успешно» делали ноль записей, потому
     * что искали папки с такими же номерами.
     *
     * @param  array<int, int>  $fileIds
     * @return Collection<int, File>
     */
    private function filesByIds(int $userId, array $fileIds): Collection
    {
        return File::query()
            ->where('user_id', $userId)
            ->whereIn('id', $fileIds)
            ->managed()
            ->get();
    }

    private function countFiles(int $userId, array $folderIds): int
    {
        return File::query()
            ->where('user_id', $userId)
            ->whereIn('folder_id', $folderIds)
            ->managed()
            ->count();
    }

    /** Снимает файл с диска. false — файла там не было. */
    private function removeFileFromDisk(int $userId, File $file): bool
    {
        $relative = (string) $file->path;

        if ($relative === '') {
            return false;
        }

        if (! $this->location->isInsideUserRoot($userId, $relative)) {
            // На диске посторонний файл: удалять его нельзя (это может
            // быть файл соседнего пользователя), но и оставлять запись —
            // значит показывать то, что не наше. Логируем и убираем запись.
            Log::error('Путь файла вне каталога пользователя при удалении', [
                'user_id' => $userId,
                'file_id' => $file->id,
                'path' => $relative,
            ]);

            return false;
        }

        $absolute = $this->location->absolute($userId, $relative);

        if (! $this->location->exists($absolute)) {
            return false;
        }

        if (! @unlink($absolute)) {
            Log::warning('Не удалось удалить файл с диска', [
                'user_id' => $userId,
                'file_id' => $file->id,
                'path' => $absolute,
            ]);

            return false;
        }

        return true;
    }
}
