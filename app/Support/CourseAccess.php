<?php

namespace App\Support;

use App\Models\Course;
use App\Models\Group2learning;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Доступ к СОДЕРЖИМОМУ курса.
 *
 * Отдельно от CourseVisibility, который отвечает на вопрос «какие курсы
 * видны в списке». Здесь — «открывается ли материал курса».
 *
 * Почему пришлось вводить при появлении витрины и самостоятельной
 * записи. До этого материал курса читался по факту входа: страница
 * /courses/desc/:id и манифест грузились любому авторизованному, кто
 * знал идентификатор. Как только каталог начал показывать все курсы и
 * позволил записаться самому, тот же обход стал читать материал
 * НЕЗАПИСАННОГО курса: идентификаторы идут подряд, перебор занимает
 * секунды.
 *
 * Правило: материал открыт тому, кто на курс записан, либо тому, кто
 * курсами управляет.
 */
class CourseAccess
{
    /** Записан ли актор (через свою группу) на этот курс. */
    public static function isEnrolled(?User $actor, Course $course): bool
    {
        if ($actor === null || $actor->group_id === null) {
            return false;
        }

        $query = Group2learning::query()
            ->where('group_id', $actor->group_id)
            ->where('course_id', $course->id);

        // Спецразделение: пилот не получает материалы бортинженера.
        // Один курс привязан к нескольким специальностям (category_course),
        // а запись группы в group2learnings несёт свою category_id — группа
        // записана на курс В РАМКАХ своей специальности. Если у курса есть
        // категории — доступ только в рамках той, на которую записана группа.
        // Курс без категорий — по старому правилу (курс целиком), чтобы не
        // ломать существующее поведение там, где специальности не используются.
        $categoryIds = DB::table('category_course')
            ->where('course_id', $course->id)
            ->pluck('category_id')
            ->all();

        if ($categoryIds !== []) {
            // Запись без category_id — это «группа записана на курс
            // целиком», а не «записи нет»: сидеры и ручная запись
            // заполняют специализацию не всегда. Такая запись даёт
            // доступ ко всем категориям курса. Раньше whereIn по
            // категориям отбрасывал NULL, и записанный обучаемый
            // получал 403 на собственном материале.
            $query->where(function ($q) use ($categoryIds) {
                $q->whereNull('category_id')->orWhereIn('category_id', $categoryIds);
            });
        }

        return $query->exists();
    }

    /** Управляет ли актор курсами: ему материал доступен всегда. */
    public static function manages(?User $actor): bool
    {
        return $actor !== null
            && ($actor->isSuperAdmin()
                || $actor->hasPermission('courses.manage')
                || $actor->hasPermission('content.manage'));
    }

    /**
     * Модули курса, к которым у группы есть доступ.
     *
     * null — открыт весь курс (запись без parent_id, то есть «группа
     * записана на курс целиком»). Массив — открыты только перечисленные
     * узлы и всё, что вложено в них. Пустой массив — не записан.
     *
     * Почему это нужно. Запись группы может относиться не к курсу, а к
     * отдельному материалу внутри него: в форме «Запись на курсы»
     * отмечаются галочками конкретные узлы дерева, и каждый пишется
     * своей строкой в group2learnings с parent_id. Такая запись —
     * осмысленная вещь для курса с несколькими специальностями: пилоту
     * отдают его блок, бортинженеру — свой. Но проверка доступа смотрела
     * только course_id и category_id, поэтому запись на один материал
     * открывала весь курс целиком, и галочки ничего не ограничивали.
     *
     * Отдельно про потомков: отметить можно и раздел, поэтому доступ
     * распространяется на всё вложенное в отмеченный узел — иначе
     * материал внутри раздела оказался бы недоступен.
     */
    public static function allowedModuleIds(?User $actor, Course $course): ?array
    {
        if ($actor === null || $actor->group_id === null) {
            return [];
        }

        $query = Group2learning::query()
            ->where('group_id', $actor->group_id)
            ->where('course_id', $course->id);

        $categoryIds = DB::table('category_course')
            ->where('course_id', $course->id)
            ->pluck('category_id')
            ->all();

        if ($categoryIds !== []) {
            $query->where(function ($q) use ($categoryIds) {
                $q->whereNull('category_id')->orWhereIn('category_id', $categoryIds);
            });
        }

        $parentIds = $query->pluck('parent_id');

        if ($parentIds->isEmpty()) {
            return [];
        }

        // Запись без parent_id — это «на курс целиком», она не ограничена.
        if ($parentIds->contains(null)) {
            return null;
        }

        return self::withDescendants($course->id, $parentIds->map(fn ($id) => (int) $id)->all());
    }

    /**
     * Разворачивает набор узлов в полное множество: сами узлы и все
     * вложенные в них.
     *
     * Дерево курса целиком читается одним запросом и раскладывается в
     * карту «родитель → дети», поэтому обход в ширину обходится без
     * запроса на уровень (при ~100 узлах курса это десятки запросов).
     */
    private static function withDescendants(int $courseId, array $roots): array
    {
        if ($roots === []) {
            return [];
        }

        $childrenOf = [];
        DB::table('aukstructures')
            ->where('course_id', $courseId)
            ->whereNotNull('parent_id')
            ->select('id', 'parent_id')
            ->orderBy('id')
            ->get()
            ->each(function ($row) use (&$childrenOf) {
                $childrenOf[(int) $row->parent_id][] = (int) $row->id;
            });

        $result = [];
        $queue = $roots;

        while ($queue !== []) {
            $id = array_shift($queue);

            if (isset($result[$id])) {
                continue;
            }

            $result[$id] = true;

            foreach ($childrenOf[$id] ?? [] as $child) {
                $queue[] = $child;
            }
        }

        return array_keys($result);
    }

    /**
     * Открыт ли конкретный модуль курса.
     *
     * Здесь сходятся оба ограничения, и раньше они жили в разных местах:
     * ограничение по материалу (parent_id) — здесь, а по специальности —
     * отдельным блоком прямо в CourseController::getlink. Из-за этого
     * правила можно было обойти, просто не вызвав тот блок, и любая
     * новая точка проверки рисковала забыть про специальность.
     *
     * Ограничения независимы: курс открыт целиком, но материал чужой
     * специальности всё равно закрыт; и наоборот — отмечен один модуль,
     * а открывать можно только его.
     */
    public static function canOpenModule(?User $actor, Course $course, int $moduleId): bool
    {
        if (self::manages($actor)) {
            return true;
        }

        $allowed = self::allowedModuleIds($actor, $course);

        // null — открыт весь курс.
        if ($allowed !== null && ! in_array($moduleId, $allowed, true)) {
            return false;
        }

        return self::hasSpecialtyAccess($actor, $course, $moduleId);
    }

    /**
     * Спецразделение материала: пилот не должен видеть файлы бортинженера
     * внутри одного и того же курса.
     *
     * Материал открыт только в рамках категории, на которую записана
     * группа. Курс и материал без категорий — по старому правилу.
     *
     * Условие deliberately строгое: запись без category_id сюда не
     * подходит (в отличие от isEnrolled, где NULL означает «на курс
     * целиком»). Ослабление открыло бы материалы, которые раньше честно
     * отдавали 403.
     */
    public static function hasSpecialtyAccess(?User $actor, Course $course, int $moduleId): bool
    {
        if ($actor === null || $actor->group_id === null) {
            return false;
        }

        $moduleCategoryIds = DB::table('aukstructure_category')
            ->where('aukstructure_id', $moduleId)
            ->pluck('category_id')
            ->all();

        if ($moduleCategoryIds === []) {
            return true;
        }

        return Group2learning::query()
            ->where('group_id', $actor->group_id)
            ->where('course_id', $course->id)
            ->whereIn('category_id', $moduleCategoryIds)
            ->exists();
    }

    /**
     * Завершение запроса с 403, если модуль закрыт.
     *
     * Сообщения разные намеренно: «не входит в запись» и «не ваша
     * специальность» — разные ситуации, и по одному тексту человек не
     * поймёт, куда ему идти.
     */
    public static function authorizeOpenModule(?User $actor, Course $course, int $moduleId): void
    {
        if (self::manages($actor)) {
            return;
        }

        if (! self::hasSpecialtyAccess($actor, $course, $moduleId)) {
            abort(403, 'Материал не относится к вашей специальности');
        }

        $allowed = self::allowedModuleIds($actor, $course);

        if ($allowed !== null && ! in_array($moduleId, $allowed, true)) {
            abort(403, 'Материал не входит в запись вашей группы по специальности');
        }
    }

    /** Открывается ли материал курса этому пользователю. */
    public static function canOpen(?User $actor, Course $course): bool
    {
        return self::manages($actor) || self::isEnrolled($actor, $course);
    }

    /**
     * Завершение запроса с 403, если материал закрыт.
     *
     * Отдельный метод, чтобы контроллеры не собирали текст ошибки
     * каждый по-своему.
     */
    public static function authorizeOpen(?User $actor, Course $course): void
    {
        if (self::canOpen($actor, $course)) {
            return;
        }

        abort(403, 'Курс не открыт: запишитесь на него, чтобы увидеть материал');
    }
}