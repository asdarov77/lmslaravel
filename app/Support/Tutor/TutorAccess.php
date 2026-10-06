<?php

namespace App\Support\Tutor;

use App\Models\Group2learning;
use App\Models\TutorMaterial;
use App\Models\User;

/**
 * Что конкретному пользователю показывать в тренажёре.
 *
 * Правило одно и оно жёсткое: материал виден, если группа пользователя
 * записана на ПАРУ (курс, специальность) этого материала.
 *
 * Почему пара, а не курс. Один курс в системе привязан к нескольким
 * специальностям сразу: «Конструкция самолета» — это и командир экипажа,
 * и штурман, и бортовой радист, и бортовой техник по АДО (в базе — шесть
 * записей в category_course). Запись группы в group2learnings при этом
 * несёт свою category_id, то есть группа записана на курс В РАМКАХ
 * своей специальности.
 *
 * Проверка только по курсу давала бы лётчику материалы радиста: курс-то
 * один и тот же. Специальность в записи — это и есть указание «мне
 * нужна вот эта часть».
 *
 * Несколько специальностей у группы — это несколько записей в
 * group2learnings, и каждая открывает свои материалы. Отдельной логики
 * не нужно: правило проверяет наличие любой подходящей записи.
 */
final class TutorAccess
{
    /**
     * Пары (course_id, category_id), на которые записана группа.
     *
     * @return array<int,array{0:int,1:int}>
     */
    public static function enrolledPairs(User $actor): array
    {
        if ($actor->group_id === null) {
            return [];
        }

        return Group2learning::query()
            ->where('group_id', $actor->group_id)
            ->whereNotNull('category_id')
            ->get(['course_id', 'category_id'])
            ->map(fn ($row) => [(int) $row->course_id, (int) $row->category_id])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Идентификаторы материалов, доступных пользователю.
     *
     * @return array<int,int>
     */
    public static function visibleMaterialIds(User $actor): array
    {
        // Управляющему индексацией видны все материалы: он и готовит их,
        // и проверяет результат. Это НЕ относится к обучаемому — там
        // действует правило пары (курс, специальность).
        if ($actor->hasPermission('tutor.manage')) {
            return TutorMaterial::pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $pairs = self::enrolledPairs($actor);

        if ($pairs === []) {
            return [];
        }

        return TutorMaterial::query()
            ->where(function ($q) use ($pairs) {
                foreach ($pairs as [$courseId, $categoryId]) {
                    $q->orWhere(function ($inner) use ($courseId, $categoryId) {
                        $inner->where('course_id', $courseId)->where('category_id', $categoryId);
                    });
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Доступен ли материал конкретному пользователю.
     */
    public static function allows(User $actor, TutorMaterial $material): bool
    {
        if ($actor->hasPermission('tutor.manage')) {
            return true;
        }

        return in_array((int) $material->id, self::visibleMaterialIds($actor), true);
    }

    /**
     * Название специальности для показа в интерфейсе.
     */
    public static function categoryTitle(?int $categoryId): ?string
    {
        if ($categoryId === null) {
            return null;
        }

        return \App\Models\Category::whereKey($categoryId)->value('title');
    }
}
