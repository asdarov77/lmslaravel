<?php

namespace App\Services;

use App\Models\Aukstructure;
use App\Models\Course;
use App\Models\CourseMaterialView;
use App\Models\CourseProgress;
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

    /**
     * Плоский список узлов курса + готовое дерево + прогресс пользователя.
     */
    public function payload(Course $course, ?int $userId = null): array
    {
        $course->loadMissing(['aircraft', 'categories']);

        $items = Aukstructure::where('course_id', $course->id)
            ->with('links')
            ->orderBy('id')
            ->get();

        // прогресс просмотра материалов текущим пользователем
        $viewedIds = [];
        if ($userId) {
            $viewedIds = CourseMaterialView::where('user_id', $userId)
                ->where('course_id', $course->id)
                ->pluck('aukstructure_id')
                ->all();
        }

        $nodes = $items->map(function (Aukstructure $item) use ($viewedIds) {
            $link = optional($item->links->first())->link;

            return [
                'id' => $item->id,
                'parent_id' => (int) ($item->parent_id ?? 0),
                'title' => $item->title,
                'type' => (int) $item->type,
                'is_material' => (int) $item->type === self::TYPE_MATERIAL && $link !== null,
                'has_link' => $link !== null,
                'visited' => in_array($item->id, $viewedIds, true),
            ];
        })->values()->all();

        // entry = первый материал (стабильно, без clamp level)
        $materials = array_values(array_filter($nodes, fn ($n) => $n['is_material']));
        $entry = $materials[0] ?? null;

        // prev/next — единый цикл по списку материалов (Д8)
        $matIds = array_column($materials, 'id');
        $indexById = array_column($nodes, null, 'id');
        foreach ($matIds as $i => $mid) {
            $pos = $indexById[$mid];
            $nodes[$pos]['prev_id'] = $matIds[$i - 1] ?? null;
            $nodes[$pos]['next_id'] = $matIds[$i + 1] ?? null;
        }

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
        $totalMaterials = count($materials);

        return [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'path' => trim((string) $course->path),
                'aircraft_path' => trim((string) optional($course->aircraft)->path),
            ],
            'nodes' => $nodes,
            'tree' => $this->buildTree($nodes),
            'entry' => $entry ? ['item_id' => $entry['id'], 'title' => $entry['title']] : null,
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
