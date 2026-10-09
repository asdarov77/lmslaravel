<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Group2learning;
use App\Models\User;
use App\Support\CourseVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Личный кабинет обучаемого: учебный план и данные для дашборда.
 *
 * Зачем: у обучаемого не было ни одной страницы с его учебным планом.
 * Пункт меню «Учебный план» вёл на админскую форму записи групп
 * (/group/learning, право users.courses) и отдавал 403. Теперь у него
 * есть собственный read-only план, а дашборд собирает сводку:
 * сколько курсов, что скоро заканчивается, какие экзамены сданы.
 *
 * Все методы читают данные ТОЛЬКО своей группы и ТОЛЬКО на чтение.
 * Запись плана остаётся за методистом (users.courses).
 */
class MyLearningController extends Controller
{
    /**
     * Учебный план текущего пользователя.
     *
     * GET /api/my/learning
     *
     * Возвращает плоский список назначений с раскрытыми курсом,
     * специальностью и модулем — так его удобнее рендерить в карточках
     * и сортировать по датам.
     */
    public function plan(Request $request)
    {
        $user = $request->user();

        if ($user === null || ! $user->group_id) {
            // Пользователь без группы не записан ни на один курс.
            // Это не ошибка: пустой учебный план — валидное состояние,
            // и фронт покажет пустое состояние с понятным текстом.
            return response()->json(['data' => [], 'meta' => ['total' => 0]]);
        }

        $rows = Group2learning::with([
            'course' => fn ($q) => $q->with(['aircraft', 'categories']),
            'category',
        ])
            ->where('group_id', $user->group_id)
            ->orderBy('study_to')
            ->orderBy('id')
            ->get();

        $moduleTitles = $this->moduleTitles($rows->pluck('parent_id')->filter()->unique());

        $data = $rows->map(function (Group2learning $row) use ($moduleTitles, $user) {
            $from = $this->date($row->study_from);
            $to = $this->date($row->study_to);

            return [
                'id' => $row->id,
                'course_id' => $row->course_id,
                'course_title' => $row->course->title ?? null,
                'course_path' => $row->course->path ?? null,
                'aircraft' => $row->course?->aircraft?->title ?? null,
                'categories' => ($row->course?->categories ?? collect())
                    ->map(fn ($c) => ['id' => $c->id, 'title' => $c->title])
                    ->values(),
                'category_id' => $row->category_id,
                'module_id' => $row->parent_id,
                'module_title' => $moduleTitles[$row->parent_id] ?? null,
                'lesson_type' => $row->typeOfLesson,
                'teacher' => $row->teacher,
                'study_from' => $from?->toDateString(),
                'study_to' => $to?->toDateString(),
                'deadline' => $this->date($row->deadline)?->toDateString(),
                'status' => $this->status($from, $to),
                'visits' => $this->visitCount($user->id, $row->course_id),
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $data->count(),
                'active' => $data->where('status', 'active')->count(),
            ],
        ]);
    }

    /**
     * Данные для дашборда обучаемого.
     *
     * GET /api/my/dashboard
     *
     * Отдельный эндпоинт, а не переиспользование /plan, потому что
     * дашборд отдаёт агрегаты: сколько всего, сколько активных, что
     * заканчивается на этой неделе и какие экзамены уже сданы.
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();

        $plan = $user && $user->group_id
            ? Group2learning::with(['course:id,title', 'category:id,title'])
                ->where('group_id', $user->group_id)
                ->get()
            : collect();

        $courses = $plan->pluck('course_id')->filter()->unique();
        $today = Carbon::today();
        $weekAhead = $today->copy()->addDays(7);

        // Сколько раз обучаемый открывал материалы по каждому курсу.
        $visits = $user
            ? \App\Models\Favorite::where('user_id', $user->id)
                ->select('course_id', \DB::raw('count(*) as total'))
                ->groupBy('course_id')
                ->pluck('total', 'course_id')
            : collect();

        $started = $courses->filter(fn ($id) => (int) ($visits[$id] ?? 0) > 0)->count();

        /*
         * Прогресс по урокам вместо «открывал или нет».
         *
         * Раньше started считалty по favorites, где хранились только
         * посещения материалов курса: один открытый урок из двадцати
         * давал те же 100%, что и полностью пройденный курс. Теперь
         * процент — среднее по lesson_progress, то есть по фактически
         * открытым урокам.
         */
        $lessonProgress = $user
            ? \App\Models\LessonProgress::where('user_id', $user->id)
                ->whereIn('course_id', $courses)
                ->get(['course_id', 'percent'])
                ->groupBy('course_id')
                ->map(fn ($rows) => (int) round($rows->avg('percent')))
            : collect();

        /*
         * Где остановился обучаемый: последний просмотренный урок и
         * файл внутри него. Без этого «продолжить» всегда открывало
         * курс с начала, хотя человек знает, где остановился.
         */
        $resumePoints = $user
            ? \App\Models\LessonProgress::where('user_id', $user->id)
                ->whereIn('course_id', $courses)
                ->orderByDesc('last_viewed_at')
                ->orderByDesc('updated_at')
                ->get()
                // groupBy + first, а не mapWithKeys: у курса несколько
                // уроков, и mapWithKeys оставлял бы ПОСЛЕДНЮЮ строку
                // в порядке выборки, то есть самый старый просмотр —
                // «продолжить» уводило бы к началу курса.
                ->groupBy('course_id')
                ->map(fn ($rows) => $rows->first())
            : collect();

        $lessonTotals = $courses->isEmpty()
            ? collect()
            : \App\Models\Aukstructure::whereIn('course_id', $courses)
                ->select('course_id')
                ->selectRaw('count(*) as total')
                ->groupBy('course_id')
                ->pluck('total', 'course_id');

        /*
         * Экзамены: попытки пользователя.
         *
         * Раньше считалось по test_results, куда ничего не писалось:
         * страница экзамена не вызывала отправку результата, а маршрута
         * /api/student-answers не существовало. Поэтому блок «Экзаменов
         * сдано» показывал 0 у кого угодно, включая отученных.
         *
         * Теперь попытки пишутся в exam_attempts при сдаче через
         * POST /exams/{id}/attempts, где score — доля верных ответов.
         */
        $attempts = $user
            ? \App\Models\ExamAttempt::where('user_id', $user->id)->get()
            : collect();

        $passedCount = $attempts->where('passed', true)->count();

        return response()->json([
            'data' => [
                'courses' => [
                    'total' => $plan->count(),
                    'distinct' => $courses->count(),
                    'active' => $plan->filter(function ($r) use ($today, $weekAhead) {
                        $from = $this->date($r->study_from);
                        $to = $this->date($r->study_to);

                        return $to === null || $to >= $today
                            ? ($from === null || $from <= $weekAhead)
                            : false;
                    })->count(),
                    'completed' => $plan->filter(fn ($r) => $this->status($this->date($r->study_from), $this->date($r->study_to)) === 'completed')->count(),
                    'started' => $started,
                ],
                /*
                 * Ближайшие сроки считаются по ДЕДЛАЙНУ, если он задан,
                 * иначе — по концу периода.
                 *
                 * Раньше использовался только study_to, то есть конец
                 * занятий группы. Для обучаемого это не то же самое:
                 * период может длиться месяц, а сдать нужно к конкретной
                 * дате. Считать «осталось N дней» до конца занятий значило
                 * показывать неверный срок.
                 */
                'upcoming' => $plan
                    ->map(function ($r) use ($today) {
                        $due = $this->date($r->deadline) ?? $this->date($r->study_to);

                        return [
                            'course_id' => $r->course_id,
                            'title' => $r->course->title ?? null,
                            'due_at' => $due?->toDateString(),
                            'source' => $this->date($r->deadline) !== null ? 'deadline' : 'study_to',
                            'days_left' => $due === null ? null : (int) $today->diffInDays($due, false),
                        ];
                    })
                    ->filter(fn ($r) => $r['days_left'] !== null && $r['days_left'] >= 0 && $r['days_left'] <= 7)
                    ->sortBy('days_left')
                    ->values()
                    ->all(),
                'exams' => [
                    'attempts' => $attempts->count(),
                    'avg_result' => $attempts->count()
                        ? round((float) $attempts->avg('score'), 2)
                        : null,
                    'passed' => $passedCount,
                    // Экзамены, которые ещё можно сдать: их видит
                    // обучаемый, поэтому счётчик должен быть не только
                    // по прошлым попыткам.
                    'assigned' => $this->assignedExams($user)->count(),
                    'available' => $this->assignedExams($user)
                        ->filter(fn ($e) => $e->stateFor($user)['available'])
                        ->count(),
                ],
                /*
                 * «Продолжить обучение»: один конкретный курс, который
                 * имеет смысл открыть прямо сейчас.
                 *
                 * Порядок выбора: сначала те, где обучаемый уже был
                 * (по таблице favorites), потом — ближайшие по сроку.
                 * Это осознанно НЕ «первый в списке»: план отсортирован по
                 * датам начала, и в начале семестра первым стоял бы курс,
                 * который открывать позже всего.
                 *
                 * Точного процента по темам нет и не выдумывается: favorites
                 * хранит посещения на уровне курса, а не модуля. Поэтому
                 * возвращается признак started, а процент остаётся общим
                 * по плану (progress.percent).
                 */
                'continue' => $this->continueLearning($plan, $visits, $today, $resumePoints, $lessonProgress),
                'progress' => [
                    /*
                     * Процент по плану считается по урокам, а не по
                     * курсам: иначе один открытый урок делал весь план
                     * «на 100%». Знаменатель — сумма уроков всех курсов
                     * плана, поэтому измерение у всех одно.
                     */
                    'percent' => $this->planPercent($lessonProgress, $lessonTotals),
                    // Сколько уроков плана пройдено и сколько всего.
                    'lessons_completed' => $this->completedLessons($user, $courses),
                    'lessons_total' => (int) $lessonTotals->sum(),
                ],
            ],
        ]);
    }

    /**
     * Процент плана по урокам.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $progress  процент по каждому курсу
     * @param  \Illuminate\Support\Collection<int, int>  $totals  сколько уроков в каждом курсе
     */
    private function planPercent($progress, $totals): int
    {
        $total = (int) $totals->sum();

        if ($total === 0) {
            return 0;
        }

        // Считаем взвешенно по урокам: у курса на 10 уроков вклад в
        // десять раз больше, чем у курса с одним. Усреднение по курсам
        // завышало результат на коротких курсах.
        $sum = 0;

        foreach ($totals as $courseId => $lessonCount) {
            $sum += (int) ($progress[$courseId] ?? 0) * (int) $lessonCount;
        }

        return (int) round($sum / $total);
    }

    /** Сколько уроков плана отмечено завершёнными. */
    private function completedLessons(?User $user, $courses): int
    {
        if ($user === null || $courses->isEmpty()) {
            return 0;
        }

        return \App\Models\LessonProgress::where('user_id', $user->id)
            ->whereIn('course_id', $courses)
            ->where(function ($q) {
                $q->whereNotNull('completed_at')->orWhere('percent', '>=', 100);
            })
            ->count();
    }

    /**
     * Курс, который обучаемому стоит открыть следующим.
     *
     * @param  \Illuminate\Support\Collection<int, Group2learning>  $plan
     * @param  \Illuminate\Support\Collection<int, int>  $visits
     * @param  \Illuminate\Support\Collection<int, LessonProgress>  $resumePoints  последний урок по курсу
     * @return array{course_id: int, title: string|null, module_title: string|null, status: string, started: bool, due_at: string|null, percent: int, resume_lesson_id: int|null, resume_file: string|null}|null
     */
    private function continueLearning($plan, $visits, Carbon $today, $resumePoints, $lessonProgress): ?array
    {
        $open = $plan->filter(function ($r) use ($today) {
            $to = $this->date($r->study_to);

            return $this->status($this->date($r->study_from), $to) !== 'completed'
                && ($to === null || $to >= $today);
        });

        if ($open->isEmpty()) {
            return null;
        }

        $ordered = $open->sortBy(function ($r) use ($visits, $today, $resumePoints) {
            // Первым идёт курс, где обучаемый уже что-то открывал и
            // не закончил: это и есть «продолжить с последней точки».
            $partiallyDone = $resumePoints->get($r->course_id) !== null
                && ! $resumePoints->get($r->course_id)->isCompleted()
                ? 0
                : 1;
            $started = (int) ($visits[$r->course_id] ?? 0) > 0 ? 0 : 1;
            $due = $this->date($r->deadline) ?? $this->date($r->study_to);

            return [$partiallyDone, $started, $due === null ? PHP_INT_MAX : abs($today->diffInDays($due, false))];
        })->values();

        $row = $ordered->first();

        return [
            'course_id' => (int) $row->course_id,
            'title' => $row->course?->title,
            'module_title' => $row->parent_id
                ? \App\Models\Aukstructure::whereKey($row->parent_id)->value('title')
                : null,
            'status' => $this->status($this->date($row->study_from), $this->date($row->study_to)),
            'started' => (int) ($visits[$row->course_id] ?? 0) > 0,
            'due_at' => ($this->date($row->deadline) ?? $this->date($row->study_to))?->toDateString(),
            // Точка возврата: процент курса и последний открытый урок.
            'percent' => (int) ($lessonProgress[$row->course_id] ?? 0),
            'resume_lesson_id' => $resumePoints->get($row->course_id)?->lesson_id,
            'resume_file' => $resumePoints->get($row->course_id)?->last_file,
        ];
    }

    /**
     * Экзамены, доступные пользователю: свои, своей группы и общие.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Exam>
     */
    private function assignedExams(?User $user)
    {
        if ($user === null) {
            return collect();
        }

        return \App\Models\Exam::with(['course', 'module', 'category', 'group'])
            ->where(function ($q) use ($user) {
                $q->whereNull('group_id')->whereNull('user_id');

                if ($user->group_id) {
                    $q->orWhere('group_id', $user->group_id);
                }

                $q->orWhere('user_id', $user->id);
            })
            ->get();
    }

    /**
     * Заголовки модулей (aukstructure) одним запросом.
     *
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

    /**
     * Состояние назначения относительно сегодняшнего дня.
     */
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

    /**
     * Сколько избранных (открытых) материалов у обучаемого по курсу.
     */
    private function visitCount(int $userId, ?int $courseId): int
    {
        if (! $courseId) {
            return 0;
        }

        return \App\Models\Favorite::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->count();
    }
}
