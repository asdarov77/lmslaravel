<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Group;
use App\Models\Group2learning;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Календарь учебного процесса.
 *
 * Зачем: /calendar отдавал недописанный демо-шаблон FullCalendar — два
 * захардкоженных события, создание через prompt(), удаление через
 * confirm() и ничего кроме памяти браузера. Таблица events при этом
 * существовала, но была пуста, и никто в неё не писал. Любое событие
 * исчезало при перезагрузке страницы.
 *
 * Решение: показывать в календаре то, что УЖЕ записано в базе. На сегодня
 * это единственные настоящие доменные даты — периоды обучения из
 * group2learnings (study_from/study_to). Поэтому календарь строится как
 * выборка из них, а не как ручной ввод событий: ручной ввод без
 * сохранения только создавал ложные ожидания.
 *
 * Формат ответа — события FullCalendar ({start, end, allDay,
 * extendedProps}), чтобы фронт не переписывал даты у себя.
 */
class CalendarController extends Controller
{
    /**
     * Лента событий учебного процесса.
     *
     * GET /api/calendar
     *
     * Фильтры: group_id, course_id, category_id, from, to, status.
     *
     * Область видимости: администратор и методист (users.courses) видят все
     * группы, остальные — только свою. Это та же логика, что у каталога
     * курсов (CourseVisibility): право «кто угодно» не должно показывать
     * расписание чужих групп.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            // Границы просмотра. Без них календарь отдал бы всю историю.
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'in:active,planned,completed'],
            // kind=period — только полосы периодов; kind=deadline — только
            // метки сроков сдачи. Без него возвращаются оба типа.
            'kind' => ['nullable', 'in:period,deadline'],
        ]);

        $user = auth('sanctum')->user();

        $maySeeAllGroups = $user !== null
            && ($user->isSuperAdmin() || $user->hasPermission('users.courses'));

        $groupId = $validated['group_id'] ?? null;

        if (! $maySeeAllGroups) {
            // Свой фильтр по группе у обучаемого игнорируется: чужую группу
            // запросить нельзя, даже передав group_id в параметрах.
            $groupId = $user?->group_id;

            // Пользователь без группы не записан ни на один курс, поэтому
            // его календарь пуст. Без этой ранней возвраты запрос уходил
            // дальше БЕЗ фильтра по группе и отдавал расписание ВСЕХ групп —
            // то есть учебные данные посторонних.
            if ($groupId === null) {
                return response()->json([
                    'data' => ['events' => [], 'filters' => ['groups' => [], 'courses' => [], 'categories' => []]],
                    'meta' => ['total' => 0],
                ]);
            }
        }

        $query = Group2learning::with([
            'course' => fn ($q) => $q->with(['aircraft', 'categories']),
            'category',
            'group:id,groupname',
        ]);

        if ($groupId !== null) {
            $query->where('group_id', $groupId);
        }

        if (! empty($validated['course_id'])) {
            $query->where('course_id', $validated['course_id']);
        }

        if (! empty($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        // Пересечение с окном просмотра, а не полное вхождение внутрь:
        // период, начавшийся до `from` и продолжающийся после него, тоже
        // должен быть виден — иначе в календаре были бы дыры.
        if (! empty($validated['from'])) {
            $query->where('study_to', '>=', Carbon::parse($validated['from'])->toDateString());
        }

        if (! empty($validated['to'])) {
            $query->where('study_from', '<=', Carbon::parse($validated['to'])->toDateString());
        }

        $rows = $query->orderBy('study_from')->orderBy('id')->get();

        $moduleTitles = $this->moduleTitles($rows->pluck('parent_id')->filter()->unique());

        $events = $rows
            ->map(function (Group2learning $row) use ($moduleTitles, $validated) {
                $from = $this->date($row->study_from);
                $to = $this->date($row->study_to);
                $status = $this->status($from, $to);

                if (! empty($validated['status']) && $status !== $validated['status']) {
                    return null;
                }

                $courseTitle = $row->course?->title ?? 'Курс удалён';

                $deadline = $this->date($row->deadline);

                return [
                    'id' => 'learning-'.$row->id,
                    'title' => $courseTitle,
                    // Полоса обучения: от первого дня до последнего включительно.
                    // У событий FullCalendar `end` не входит в диапазон, поэтому
                    // к study_to прибавляется день — иначе последний день
                    // обучения не показывался.
                    'start' => $from?->toDateString(),
                    'end' => $to?->copy()->addDay()->toDateString(),
                    'allDay' => true,
                    'extendedProps' => [
                        'learning_id' => $row->id,
                        'status' => $status,
                        'course_id' => $row->course_id,
                        'course_title' => $courseTitle,
                        'aircraft' => $row->course?->aircraft?->title,
                        'group_id' => $row->group_id,
                        'group_name' => $row->group?->groupname,
                        'module_title' => $moduleTitles[$row->parent_id] ?? null,
                        'category_title' => $row->category?->title,
                        'categories' => ($row->course?->categories ?? collect())
                            ->map(fn ($c) => ['id' => $c->id, 'title' => $c->title])
                            ->values(),
                        'lesson_type' => $row->typeOfLesson,
                        'teacher' => $row->teacher,
                        'study_from' => $from?->toDateString(),
                        'study_to' => $to?->toDateString(),
                        'deadline' => $deadline?->toDateString(),
                        // Тип события: полоса периода или метка дедлайна.
                        // По нему фронт красит и подписывает по-разному.
                        'kind' => 'period',
                    ],
                ];
            })
            ->filter()
            ->values();

        /*
         * Дедлайны — отдельные события, а не украшение полосы периода.
         *
         * Смысл дедлайна другой: период показывает, когда группа занимается,
         * дедлайн — когда закончить. Если показывать его только внутри полосы,
         * его не видно в месячном и недельном виде, где полосы сливаются;
         * отдельная метка даёт просканировать календарь взглядом и найти
         * «к чтоу сдавать».
         */
        $deadlineEvents = $rows
            ->map(function (Group2learning $row) use ($moduleTitles, $validated) {
                $deadline = $this->date($row->deadline);

                if ($deadline === null) {
                    return null;
                }

                // Фильтр по состоянию применяется и к меткам срока сдачи.
                // Раньше он проверялся только в map() для полос периодов,
                // поэтому ?status=completed возвращал просроченные метки
                // вместе с «подходящими» периодами.
                $status = $this->status(null, $deadline);

                if (! empty($validated['status']) && $status !== $validated['status']) {
                    return null;
                }

                $module = $moduleTitles[$row->parent_id] ?? null;
                $courseTitle = $row->course?->title ?? 'Курс удалён';

                return [
                    'id' => 'deadline-'.$row->id,
                    'title' => 'Срок сдачи: '.($module ? $courseTitle.' · '.$module : $courseTitle),
                    // Метка в один день: у FullCalendar end не включается,
                    // поэтому у однодневного события его нет вовсе.
                    'start' => $deadline->toDateString(),
                    'allDay' => true,
                    'extendedProps' => [
                        'learning_id' => $row->id,
                        'kind' => 'deadline',
                        'status' => $status,
                        'course_id' => $row->course_id,
                        'course_title' => $courseTitle,
                        'module_title' => $module,
                        'aircraft' => $row->course?->aircraft?->title,
                        'group_id' => $row->group_id,
                        'group_name' => $row->group?->groupname,
                        'category_title' => $row->category?->title,
                        'categories' => ($row->course?->categories ?? collect())
                            ->map(fn ($c) => ['id' => $c->id, 'title' => $c->title])
                            ->values(),
                        'lesson_type' => $row->typeOfLesson,
                        'teacher' => $row->teacher,
                        'study_from' => $this->date($row->study_from)?->toDateString(),
                        'study_to' => $this->date($row->study_to)?->toDateString(),
                        'deadline' => $deadline->toDateString(),
                    ],
                ];
            })
            ->filter();

        // Три состояния, а не два: без kind — оба типа, kind=deadline —
        // только сроки сдачи, kind=period — только полосы периодов.
        // Раньше kind=period уходил в ветку «оба типа» и возвращал
        // дедлайны вместе с периодами.
        $kind = $validated['kind'] ?? null;

        $events = match ($kind) {
            'deadline' => $deadlineEvents->values(),
            'period' => $events->values(),
            default => $events->concat($deadlineEvents)->values(),
        };

        return response()->json([
            'data' => [
                'events' => $events,
                'filters' => $this->filterOptions($user, $maySeeAllGroups),
            ],
            'meta' => [
                'total' => $events->count(),
            ],
        ]);
    }

    /**
     * Варианты для фильтров: группы, курсы, категории.
     *
     * Отдаются вместе с лентой, чтобы фильтры не требовали трёх
     * дополнительных запросов и не расходились по доступности с самой
     * лентой (для обучаемого здесь будет ровно его группа).
     */
    private function filterOptions(?\App\Models\User $user, bool $maySeeAllGroups): array
    {
        $groups = Group::orderBy('groupname')
            ->when(! $maySeeAllGroups && $user?->group_id, fn ($q) => $q->where('id', $user->group_id))
            ->get(['id', 'groupname'])
            ->map(fn ($g) => ['id' => $g->id, 'title' => $g->groupname]);

        // Курсы и категории ограничиваем тем, что реально встречается
        // в учебных периодах: пустой фильтр «все курсы системы» вводит
        // в заблуждение — в календаре их всё равно не будет.
        $courses = Course::orderBy('title')
            ->whereIn('id', Group2learning::distinct()->pluck('course_id'))
            ->get(['id', 'title'])
            ->map(fn ($c) => ['id' => $c->id, 'title' => $c->title]);

        $categories = \App\Models\Category::orderBy('title')
            ->whereIn('id', Group2learning::distinct()->pluck('category_id'))
            ->get(['id', 'title'])
            ->map(fn ($c) => ['id' => $c->id, 'title' => $c->title]);

        return [
            'groups' => $groups,
            'courses' => $courses,
            'categories' => $categories,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $ids
     * @return array<int, string>
     */
    private function moduleTitles($ids): array
    {
        $ids = $ids->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return \App\Models\Aukstructure::whereIn('id', $ids)
            ->pluck('title', 'id')
            ->map(fn ($t) => (string) $t)
            ->all();
    }

    private function status(?Carbon $from, ?Carbon $to): string
    {
        $today = Carbon::today();

        if ($from !== null && $from->gt($today)) {
            return 'planned';
        }

        if ($to !== null && $to->lt($today)) {
            return 'completed';
        }

        return 'active';
    }

    private function date(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
