<?php

namespace App\Support\FileManager;

use App\Models\FileFolder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Дерево папок пользователя: кто чей, какой путь, куда можно переносить.
 *
 * Отдельный класс, потому что три вещи в дереве не выводятся из путей на
 * диске:
 *
 *  1. владелец — каталог на диске не помнит, чей он;
 *  2. родитель — путь «a/b/c» не говорит, существует ли «a» в базе, и
 *     существует ли он ВООБЩЕ;
 *  3. отсутствие зацикливания — перенос папки внутрь самой себя даёт
 *     бесконечное дерево, и путь из такого дерева перестаёт быть
 *     конечным.
 *
 * Порядок проверки в переносе важен и задан здесь, а не в контроллере:
 * сначала «не является ли новый родитель потомком самой папки», потом
 * «принадлежит ли родитель пользователю». Наоборот получилось бы, что
 * проверка зацикливания сделана для чужого узла и молча проходит.
 */
final class FolderTree
{
    public function __construct(private readonly Location $location)
    {
    }

    /**
     * Папка пользователя по id или null (для id = null).
     *
     * Явная выборка по user_id вместо find(): чужой id обязан выглядеть
     * как отсутствующий, иначе по id можно было бы переименовать или
     * удалить чужую папку, просто угадав номер.
     */
    public function find(?int $folderId, int $userId): ?FileFolder
    {
        if ($folderId === null) {
            return null;
        }

        return FileFolder::query()
            ->where('user_id', $userId)
            ->whereKey($folderId)
            ->first();
    }

    /**
     * Цепочка папок от корня до $folder включительно.
     *
     * Возвращает Collection<FileFolder> (включая null-элемент для корня,
     * чтобы позиции совпадали с сегментами пути).
     *
     * Защита от бесконечного цикла в данных: если из-за сбоя
     * parent_id образовал кольцо, подъём по родителям не должен
     * висеть вечно. Ограничение глубины — страховка, а не «ожидаемый»
     * случай.
     */
    public function chain(?FileFolder $folder, int $userId): Collection
    {
        $chain = collect();
        $current = $folder;
        $depth = 0;
        $maxDepth = 64;

        while ($current !== null) {
            $chain->prepend($current);

            if (++$depth > $maxDepth) {
                throw ValidationException::withMessages([
                    'name' => 'Дерево папок повреждено: слишком глубокая вложенность.',
                ]);
            }

            $parentId = $current->parent_id;
            $current = $parentId === null ? null : $this->find($parentId, $userId);

            // Родитель принадлежит другому пользователю: подъём
            // останавливаем, иначе путь собрался бы из чужой ветки.
            if ($current === null && $parentId !== null) {
                break;
            }
        }

        return $chain;
    }

    /**
     * Сегменты пути папки — имена от корня до $folder.
     *
     * @return array<int, string>
     */
    public function segments(?FileFolder $folder, int $userId): array
    {
        return $this->chain($folder, $userId)
            ->map(fn (FileFolder $f): string => $f->name)
            ->values()
            ->all();
    }

    /**
     * Хлебные крошки для интерфейса.
     *
     * @return array<int, array{id: int|null, name: string}>
     */
    public function breadcrumb(?FileFolder $folder, int $userId): array
    {
        $crumbs = [['id' => null, 'name' => 'Файлы']];

        foreach ($this->chain($folder, $userId) as $item) {
            $crumbs[] = ['id' => (int) $item->id, 'name' => $item->name];
        }

        return $crumbs;
    }

    /**
     * Проверяет, что $folder не является потомком $candidateParentId.
     *
     * Без этой проверки перенос папки в её же вложенную папку создаёт
     * кольцо: путь перестаёт заканчиваться, и любая сборка пути по
     * цепочке родителей зациклится.
     *
     * @throws ValidationException
     */
    public function assertNoCycle(?FileFolder $folder, ?int $candidateParentId, int $userId): void
    {
        if ($folder === null || $candidateParentId === null) {
            return;
        }

        if ((int) $folder->id === $candidateParentId) {
            throw ValidationException::withMessages([
                'folder_id' => 'Папку нельзя переместить в саму себя.',
            ]);
        }

        // Поднимаемся от кандидата вверх: если встретим переносимую
        // папку, она — потомок кандидата, и перенос создаст кольцо.
        $cursor = $this->find($candidateParentId, $userId);
        $depth = 0;

        while ($cursor !== null && $depth++ < 64) {
            if ((int) $cursor->id === (int) $folder->id) {
                throw ValidationException::withMessages([
                    'folder_id' => 'Папку нельзя переместить внутрь самой себя.',
                ]);
            }

            $parentId = $cursor->parent_id;
            $cursor = $parentId === null ? null : $this->find($parentId, $userId);
        }
    }

    /**
     * Все потомки папки — самой папки в список не входит.
     *
     * Собирается в ширину, а не рекурсией по запросам: рекурсия
     * ограничена глубиной PHP-стека, а дерево глубиной 300 уровней
     * (его можно создать многослойным переносом) уронило бы запрос.
     *
     * @return Collection<int, FileFolder>
     */
    public function descendants(int $folderId, int $userId): Collection
    {
        $all = collect();
        $frontier = [$folderId];
        $seen = [$folderId => true];

        while ($frontier !== []) {
            $children = FileFolder::query()
                ->where('user_id', $userId)
                ->whereIn('parent_id', $frontier)
                ->get();

            $next = [];

            foreach ($children as $child) {
                $id = (int) $child->id;

                // Защита от кольца в данных: узел, встреченный второй
                // раз, повторно не обходится, иначе цикл длился бы
                // вечно и подвешивал бы запрос.
                if (isset($seen[$id])) {
                    continue;
                }

                $seen[$id] = true;
                $all->push($child);
                $next[] = $id;
            }

            $frontier = $next;
        }

        return $all;
    }

    /**
     * Свободное имя папки в каталоге.
     *
     * Проверяется таблица, а не диск: каталог на диске может остаться
     * от удалённой папки, но переиспользовать имя после удаления можно
     * и нужно — иначе удалил папку и больше не смог создать такую же.
     */
    public function uniqueName(int $userId, ?int $parentId, string $desired, ?int $ignoreId = null): string
    {
        $taken = FileFolder::query()
            ->where('user_id', $userId)
            ->where(fn ($q) => $parentId === null ? $q->whereNull('parent_id') : $q->where('parent_id', $parentId))
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->pluck('name')
            ->all();

        if (! in_array($desired, $taken, true)) {
            return $desired;
        }

        for ($i = 2; $i <= 500; $i++) {
            $candidate = $desired.' ('.$i.')';

            if (! in_array($candidate, $taken, true)) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages([
            'name' => 'Не удалось подобрать свободное имя папки.',
        ]);
    }
}
