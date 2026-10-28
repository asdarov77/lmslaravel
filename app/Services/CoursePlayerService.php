<?php

namespace App\Services;

use App\Models\Aukstructure;
use App\Models\Course;
use App\Models\CourseMaterialView;
use App\Models\CourseProgress;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * Единый серверный источник правды для плеера курса.
 *
 * Заменяет «двойной стандарт» построения дерева на фронте:
 *  - признак материала берётся из item.type === 3 (а не из схлопнутого level);
 *  - корни/дети определяются по parent_id (0|null), а не по позиции в массиве;
 *  - prev/next считаются один раз на сервере (единый цикл урока, Д8);
 *  - resume-point читается с сервера (Д6), а не только из localStorage.
 */
class CoursePlayerService
{
    /** Тип структуры АУК, являющийся материалом (открываемым файлом). */
    public const TYPE_MATERIAL = 3;

    /** TTL кэша дерева курса (сек). Дерево меняется редко — прогресс нет. */
    private const TREE_CACHE_TTL = 3600;

    /**
     * Ключ кэша дерева курса. Инвалидируется при любом изменении АУК/ссылок
     * (см. invalidateTreeCache) и дополнительно ограничен TTL.
     *
     * В тестовой среде (array/file-кэш между запросами живёт, а RefreshDatabase
     * пересоздаёт схему с new id) кэш отключён: контрактные тесты должны видеть
     * свежие данные, иначе чужое закешированное дерево даёт ложные падения.
     */
    public static function treeCacheKey(int $courseId): string
    {
        return "course-player-tree-{$courseId}";
    }

    /**
     * Явная инвалидация кэша дерева — вызывать из мест изменения структуры
     * курса (AukstructureController, импорт материалов, изменение links).
     */
    public static function invalidateTreeCache(int $courseId): void
    {
        Cache::forget(self::treeCacheKey($courseId));
    }

    /**
     * Плоский список узлов курса + готовое дерево + прогресс пользователя.
     *
     * Производительность (P1): структура курса (узлы, дерево, entry, prev/next,
     * ссылки на контент) одинакова для всех учащихся и меняется редко, поэтому
     * кэшируется целиком (Cache::remember). Индивидуальная часть — viewed /
     * resume / stats — вычисляется поверх кэша дешёвым pluck'ом, поэтому кэш
     * никогда не «протухает» относительно прогресса конкретного пользователя.
     */
    public function payload(Course $course, ?int $userId = null): array
    {
        $course->loadMissing(['aircraft', 'categories']);

        $structure = Cache::remember(
            self::treeCacheKey($course->id),
            self::TREE_CACHE_TTL,
            fn () => $this->buildStructure($course)
        );

        $nodes = $structure['nodes'];
        $tree = $structure['tree'];
        $entry = $structure['entry'];
        $totalMaterials = $structure['total_materials'];

        // прогресс просмотра материалов текущим пользователем — вне кэша
        $viewedIds = [];
        if ($userId) {
            $viewedIds = CourseMaterialView::where('user_id', $userId)
                ->where('course_id', $course->id)
                ->pluck('aukstructure_id')
                ->all();
        }

        $viewedFlip = array_flip($viewedIds);
        foreach ($nodes as &$node) {
            $node['visited'] = isset($viewedFlip[$node['id']]);
        }
        unset($node);

        $resume = null;
        if ($userId) {
            $p = CourseProgress::where('user_id', $userId)
                ->where('course_id', $course->id)
                ->first();
            if ($p) {
                $resume = [
                    'last_item_id' => $p->last_item_id,
                    'last_anchor' => $p->last_anchor,
                    'percent' => $p->percent,
                ];
            }
        }

        $visitedCount = count($viewedIds);

        return [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'path' => trim((string) $course->path),
                'aircraft_id' => $course->aircraft_id ? (int) $course->aircraft_id : null,
                'aircraft_path' => trim((string) optional($course->aircraft)->path),
            ],
            'nodes' => $nodes,
            'tree' => $tree,
            // Д2: entry несёт content_url сразу — фронту не нужен RTT к getlink
            'entry' => $entry,
            'resume' => $resume,
            'stats' => [
                'materials_total' => $totalMaterials,
                'materials_visited' => min($visitedCount, $totalMaterials),
                'percent' => $totalMaterials > 0
                    ? (int) round(min($visitedCount, $totalMaterials) / $totalMaterials * 100)
                    : 0,
            ],
        ];
    }

    /**
     * Кэшируемая часть payload: узлы + дерево + entry + prev/next.
     * Не содержит ничего пользовательского (visited проставляется поверх).
     */
    private function buildStructure(Course $course): array
    {
        $items = Aukstructure::where('course_id', $course->id)
            ->with('links')
            ->orderBy('id')
            ->get();

        $nodes = $items->map(function (Aukstructure $item) use ($course) {
            $link = optional($item->links->first())->link;

            return [
                'id' => $item->id,
                'parent_id' => (int) ($item->parent_id ?? 0),
                'title' => $item->title,
                'type' => (int) $item->type,
                'is_material' => (int) $item->type === self::TYPE_MATERIAL && $link !== null,
                'has_link' => $link !== null,
                // единый авторизованный endpoint выдачи материала (Д1, Д2):
                // фронт не собирает пути сам и не зависит от ключей getlink
                'content_url' => $link !== null
                    ? "/api/course/{$course->id}/content/{$item->id}"
                    : null,
                'visited' => false,
            ];
        })->values()->all();

        // entry = первый материал (стабильно, без clamp level)
        $materials = array_values(array_filter($nodes, fn ($n) => $n['is_material']));
        $entry = $materials[0] ?? null;
        if ($entry) {
            // мгновенный старт кадра: URL материала уже в payload — фронту
            // не нужен дополнительный RTT к getlink (Д2, P0.1 «3 RTT»)
            $entry = ['item_id' => $entry['id'], 'title' => $entry['title'], 'content_url' => $entry['content_url']];
        }

        // prev/next — единый цикл по списку материалов (Д8)
        $matIds = array_column($materials, 'id');
        // array_column(..., null, 'id') вернул бы сами узлы (значения), а не
        // позиции — берём только ключи id и индексы массива $nodes.
        $posById = array_flip(array_column($nodes, 'id'));
        foreach ($matIds as $i => $mid) {
            $pos = $posById[$mid];
            $nodes[$pos]['prev_id'] = $matIds[$i - 1] ?? null;
            $nodes[$pos]['next_id'] = $matIds[$i + 1] ?? null;
        }

        return [
            'nodes' => $nodes,
            'tree' => $this->buildTree($nodes),
            'entry' => $entry,
            'total_materials' => count($materials),
        ];
    }

    /**
     * Дерево по parent_id: корнями считаются узлы с parent_id = 0 либо
     * с parent_id, отсутствующим среди узлов курса (Д4 — плоские курсы).
     */
    private function buildTree(array $nodes): array
    {
        $byId = [];
        foreach ($nodes as $n) {
            $n['children'] = [];
            $byId[$n['id']] = $n;
        }

        $roots = [];
        foreach ($byId as $id => $n) {
            $pid = (int) ($n['parent_id'] ?? 0);
            if ($pid !== 0 && isset($byId[$pid])) {
                $byId[$pid]['children'][] = &$byId[$id];
            } else {
                $roots[] = &$byId[$id];
            }
        }

        return $roots;
    }

    /**
     * Абсолютный URL приватного файла материала (тот же контракт, что и getlink).
     */
    public function fileUrl(Course $course, string $link): string
    {
        return Config::get('app.private_path')
            . trim((string) optional($course->aircraft)->path) . '/'
            . trim((string) $course->path) . '/' . $link;
    }
}
