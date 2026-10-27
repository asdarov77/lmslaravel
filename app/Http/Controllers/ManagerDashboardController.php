<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Category;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Group;
use App\Models\Group2learning;
use App\Models\Permission;
use App\Models\Question;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Сводка для администратора и инструктора.
 *
 * Зачем отдельный endpoint, если есть /api/my/dashboard:
 * тот отвечает на вопрос «как МОЁ обучение», и для управляющего он
 * бессмыслен — в интерфейсе администратор видел «Состояние вашего
 * обучения на сегодня», то есть дашборд обучаемого.
 *
 * Что важно в этой сводке:
 *  - счётчики не выдают то, что актор не имеет права видеть.
 *    Инструктор получает данные своей группы, а не всей системы;
 *  - «деревянный» список (что требует внимания) полезнее сумм: у
 *    администратора это группы без записи на курсы, у инструктора —
 *    группа без учебного плана;
 *  - никаких правильных ответов: попытки экзаменов содержат только
 *    счётчики верных, не сами вопросы.
 */
class ManagerDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function __invoke(Request $request)
    {
        $actor = $request->user();

        $isAdmin = $actor->isSuperAdmin();
        $managesCourses = $isAdmin || $actor->hasPermission('courses.manage');

        // Инструктор работает со своей группой. Управляющий курсами и
        // администратор видят всю систему.
        $groupScope = $this->groupScope($actor, $isAdmin || $actor->hasPermission('groups.manage'));

        $stats = [];

        // --- Пользователи ------------------------------------------------
        if ($isAdmin || $actor->hasPermission('users.view')) {
            $users = User::query();
            $this->applyGroupScope($users, $groupScope);

            $stats[] = [
                'key' => 'users',
                'label_key' => 'manager.stats.users',
                'value' => (clone $users)->count(),
                'icon' => 'mdi-account-multiple-outline',
                'tone' => 'primary',
            ];

            if (! $isAdmin) {
                // Сколько в группе людей без роли: на первом месте это
                // самая частая причина, почему человек ничего не видит.
                $stats[] = [
                    'key' => 'usersWithoutGroup',
                    'label_key' => 'manager.stats.usersOutside',
                    'value' => (clone $users)->whereNull('group_id')->count(),
                    'icon' => 'mdi-account-question-outline',
                    'tone' => 'warning',
                ];
            }
        }

        // --- Группы ------------------------------------------------------
        if ($isAdmin || $actor->hasPermission('groups.view') || $actor->hasPermission('groups.manage')) {
            $groups = Group::query();
            $this->applyGroupScope($groups, $groupScope, 'id');

            $stats[] = [
                'key' => 'groups',
                'label_key' => 'manager.stats.groups',
                'value' => (clone $groups)->count(),
                'icon' => 'mdi-account-group-outline',
                'tone' => 'primary',
            ];
        }

        // --- Курсы и записи ----------------------------------------------
        if ($isAdmin || $actor->hasPermission('courses.view') || $actor->hasPermission('courses.manage')) {
            if ($managesCourses) {
                $stats[] = [
                    'key' => 'courses',
                    'label_key' => 'manager.stats.courses',
                    'value' => Course::count(),
                    'icon' => 'mdi-book-open-page-variant-outline',
                    'tone' => 'primary',
                ];
                $stats[] = [
                    'key' => 'categories',
                    'label_key' => 'manager.stats.categories',
                    'value' => Category::count(),
                    'icon' => 'mdi-shape-outline',
                    'tone' => 'muted',
                ];
            }

            $learning = Group2learning::query();
            $this->applyGroupScope($learning, $groupScope);

            $stats[] = [
                'key' => 'assignments',
                'label_key' => 'manager.stats.assignments',
                'value' => (clone $learning)->count(),
                'icon' => 'mdi-calendar-month-outline',
                'tone' => 'muted',
            ];
        }

        // --- Банк вопросов -------------------------------------------------
        if ($isAdmin || $actor->hasPermission('questions.view') || $actor->hasPermission('questions.manage')) {
            $broken = Question::query()
                ->whereDoesntHave('answers')
                ->orWhereHas('answers', fn ($q) => $q->where('is_correct', true), '=', 0)
                ->count();

            $stats[] = [
                'key' => 'questions',
                'label_key' => 'manager.stats.questions',
                'value' => Question::count(),
                'icon' => 'mdi-help-circle-outline',
                'tone' => 'muted',
            ];

            // Вопросы без верного ответа ломают экзамен: показываем
            // количество явно, а не прячем.
            $stats[] = [
                'key' => 'questionsBroken',
                'label_key' => 'manager.stats.questionsBroken',
                'value' => $broken,
                'icon' => 'mdi-alert-circle-outline',
                'tone' => $broken > 0 ? 'danger' : 'success',
            ];
        }

        // --- Экзамены -------------------------------------------------------
        if ($isAdmin || $actor->hasPermission('exams.manage') || $actor->hasPermission('exams.take')) {
            $exams = Exam::query();
            $this->applyExamScope($exams, $actor, $groupScope);

            $attempts = ExamAttempt::query();
            $this->applyGroupScopeOnAttempts($attempts, $actor, $groupScope);

            $stats[] = [
                'key' => 'attempts',
                'label_key' => 'manager.stats.attempts',
                'value' => (clone $attempts)->count(),
                'icon' => 'mdi-clipboard-check-outline',
                'tone' => 'muted',
            ];

            $stats[] = [
                'key' => 'passed',
                'label_key' => 'manager.stats.passed',
                'value' => (clone $attempts)->where('passed', true)->count(),
                'icon' => 'mdi-check-decagram-outline',
                'tone' => 'success',
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $isAdmin ? 'admin' : ($actor->isInstructor() ? 'instructor' : 'trainee'),
                'stats' => $stats,
                'attention' => $this->attention($actor, $isAdmin, $groupScope),
                'recent' => $this->recent($actor, $groupScope),
                'trends' => $this->trends($actor, $groupScope),
                'top_courses' => $this->topCourses($actor, $groupScope),
                'review_queue' => $this->reviewQueue($actor, $groupScope),
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * Топ курсов: число зачислений (group2learnings) и попытки экзаменов
     * слушателей групп, записанных на курс.
     */
    private function topCourses(User $actor, ?array $groupScope): array
    {
        if (! ($actor->isSuperAdmin() || $actor->hasPermission('courses.manage') || $actor->hasPermission('courses.view'))) {
            return [];
        }

        return Course::query()
            ->withCount(['group2learnings' => function ($q) use ($groupScope) {
                $this->applyGroupScope($q, $groupScope);
            }])
            ->orderByDesc('group2learnings_count')
            ->limit(5)
            ->get(['id', 'title'])
            ->map(function (Course $course) {
                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'students' => (int) $course->group2learnings_count,
                    'attempts' => ExamAttempt::query()
                        ->whereIn('user_id', function ($sub) use ($course) {
                            $sub->select('users.id')
                                ->from('users')
                                ->join('group2learnings', 'group2learnings.group_id', '=', 'users.group_id')
                                ->where('group2learnings.course_id', $course->id);
                        })
                        ->count(),
                ];
            })
            ->all();
    }

    /**
     * Работы, которые ждут проверки преподавателя: сданы, но ещё без
     * оценки и комментария. Отдаём пять последних с именами, чтобы
     * сразу было куда пойти.
     */
    /**
     * GET /api/menu-counters: счётчики для бейджей меню.
     *
     * Пока один счётчик — очередь проверки: остальные бейджи (объявления,
     * форум) завести без своего источника нельзя, а «бейдж ради бейджа»
     * только шумит. Работает для manager/admin с правом проверки.
     */
    public function counters(Request $request)
    {
        $actor = $request->user();
        $canReview = $actor->isSuperAdmin() || $actor->hasPermission('grading.manage') || $actor->hasPermission('courses.manage');

        $reviewQueue = 0;

        if ($canReview) {
            $reviewQueue = (clone $this->reviewQueueQuery($actor))
                ->toBase()->count();
        }

        return response()->json([
            'success' => true,
            'data' => ['review_queue' => $reviewQueue],
            'error' => null,
            'meta' => null,
        ]);
    }

    private function reviewQueueQuery(User $actor)
    {
        $groupScope = $this->groupScope($actor, $actor->isSuperAdmin() || $actor->hasPermission('courses.manage'));

        return AssignmentSubmission::query()
            ->whereNull('score')
            ->whereNull('feedback')
            ->whereNotNull('submitted_at')
            ->when($groupScope !== null, fn ($q) => $q->whereIn('user_id',
                fn ($sub) => $sub->select('id')->from('users')->whereIn('group_id', $groupScope)));
    }

    private function reviewQueue(User $actor, ?array $groupScope): array
    {
        $canReview = $actor->isSuperAdmin() || $actor->hasPermission('grading.manage') || $actor->hasPermission('courses.manage');
        if (! $canReview) {
            return [];
        }

        $reviewModel = AssignmentSubmission::query();
        $this->applyGroupScope($reviewModel, $groupScope);

        $query = AssignmentSubmission::query()
            ->whereNull('score')
            ->whereNull('feedback')
            ->whereNotNull('submitted_at')
            ->when($groupScope !== null, fn ($q) => $q->whereIn('user_id',
                fn ($sub) => $sub->select('id')->from('users')->whereIn('group_id', $groupScope)))
            ->with(['assignment:id,title', 'user:id,fio'])
            ->orderByDesc('submitted_at')
            ->limit(5);

        return $query->get()->map(fn (AssignmentSubmission $sub) => [
            'id' => $sub->id,
            'assignment' => $sub->assignment?->title,
            'student' => $sub->user?->fio,
            'submitted_at' => $sub->submitted_at,
        ])->all();
    }

    /**
     * Область по группам: null — вся система, иначе список id.
     *
     * Пустая группа — это «нет своей части», а не «вся система»:
     * инструктор без группы не должен видеть данные всех.
     *
     * @return array<int,int>|null
     */
    private function groupScope(User $actor, bool $seesAll): ?array
    {
        if ($seesAll) {
            return null;
        }

        return $actor->group_id !== null ? [(int) $actor->group_id] : [];
    }

    /**
     * Ограничение по группам.
     *
     * Колонка указывается явно: у самой таблицы groups идентификатор
     * называется `id`, а не `group_id`. Раньше здесь было жёстко
     * `whereIn('group_id', …)`, и запрос по группам падал с
     * «столбец group_id не существует» — то есть сводка отдавала 500
     * любому, у кого есть право на группы.
     */
    private function applyGroupScope($query, ?array $scope, string $column = 'group_id'): void
    {
        if ($scope !== null) {
            $query->whereIn($column, $scope);
        }
    }

    private function applyExamScope($query, User $actor, ?array $scope): void
    {
        $isAdmin = $actor->isSuperAdmin();

        if ($isAdmin || $actor->hasPermission('exams.manage')) {
            return;
        }

        // Обучаемому и инструктору без exams.manage — только своё.
        $query->where(function ($q) use ($actor) {
            $q->where('user_id', $actor->id);

            if ($actor->group_id !== null) {
                $q->orWhere('group_id', $actor->group_id);
            }
        });
    }

    /**
     * Попытки экзаменов: у попытки нет group_id, поэтому область
     * берётся по пользователю — через его группу.
     */
    private function applyGroupScopeOnAttempts($query, User $actor, ?array $scope): void
    {
        if ($scope === null) {
            return;
        }

        $query->whereIn('user_id', function ($sub) use ($scope) {
            $sub->select('id')->from('users')->whereIn('group_id', $scope);
        });
    }

    /**
     * Ряды для графиков: динамика попыток и успеваемость по группам.
     *
     * Месяцы собираются в PHP, а не оконной функцией SQL: проект не
     * использует generate_series, а отправлять наружу все попытки ради
     * подсчёта по месяцам — это десятки тысяч строк без выигрыша.
     *
     * Данные уже ограничены областью видимости (groupScope), иначе
     * инструктор увидел бы цифры по чужим группам.
     *
     * @return array{attempts_by_month: array<int, array<string, mixed>>, groups: array<int, array<string, mixed>>}
     */
    private function trends(User $actor, ?array $groupScope): array
    {
        $months = 6;
        $buckets = [];

        // Ровно шесть последних месяцев, включая текущий: пропуск
        // месяца без попыток сжал бы график и создал бы впечатление
        // «данных нет» там, где их просто не было.
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = Carbon::today()->subMonths($i)->startOfMonth();

            $buckets[$start->format('Y-m')] = [
                'month' => $start->format('Y-m'),
                'label' => $start->translatedFormat('M Y'),
                'attempts' => 0,
                'passed' => 0,
            ];
        }

        $attempts = ExamAttempt::query();
        $this->applyGroupScopeOnAttempts($attempts, $actor, $groupScope);

        $attempts->where('created_at', '>=', Carbon::today()->subMonths($months - 1)->startOfMonth())
            ->get(['created_at', 'passed'])
            ->each(function (ExamAttempt $attempt) use (&$buckets) {
                $key = Carbon::parse($attempt->created_at)->format('Y-m');

                if (! isset($buckets[$key])) {
                    return;
                }

                $buckets[$key]['attempts']++;

                if ($attempt->passed) {
                    $buckets[$key]['passed']++;
                }
            });

        $groups = Group::query()
            ->when($groupScope !== null, fn ($q) => $q->whereIn('id', $groupScope))
            ->orderBy('groupname')
            ->get(['id', 'groupname'])
            ->map(function (Group $group) {
                $studentIds = DB::table('users')->where('group_id', $group->id)->pluck('id');

                $total = $studentIds->isEmpty()
                    ? 0
                    : ExamAttempt::whereIn('user_id', $studentIds)->count();
                $passed = $studentIds->isEmpty()
                    ? 0
                    : ExamAttempt::whereIn('user_id', $studentIds)->where('passed', true)->count();

                return [
                    'id' => $group->id,
                    'name' => $group->groupname,
                    // Процент, а не доля: «успеваемость 0.75» читается
                    // неоднозначно, «75%» — однозначно.
                    'percent' => $total > 0 ? (int) round($passed / $total * 100) : null,
                    'attempts' => $total,
                ];
            })
            ->values()
            ->all();

        return [
            'attempts_by_month' => array_values($buckets),
            'groups' => $groups,
        ];
    }

    /**
     * Что требует внимания.
     *
     * Суммы сами по себе бесполезны: «групп: 10» ничего не подсказывает.
     * Список — подсказывает, что делать.
     */
    private function attention(User $actor, bool $isAdmin, ?array $scope): array
    {
        $rows = [];

        $seesGroups = $isAdmin
            || $actor->hasPermission('groups.view')
            || $actor->hasPermission('groups.manage');

        if ($seesGroups) {
            $groups = Group::query()->whereDoesntHave('group2learnings');
            $this->applyGroupScope($groups, $scope, 'id');

            foreach ($groups->limit(5)->get() as $group) {
                $rows[] = [
                    'key' => 'groupWithoutPlan',
                    'to' => '/groups/edit/'.$group->id,
                    'text' => $group->groupname,
                    'hint_key' => 'manager.attention.groupWithoutPlan',
                ];
            }
        }

        $seesBank = $isAdmin
            || $actor->hasPermission('questions.view')
            || $actor->hasPermission('questions.manage');

        if ($seesBank && $actor->hasPermission('questions.manage')) {
            $withoutCorrect = Question::query()
                ->whereDoesntHave('answers', fn ($q) => $q->where('is_correct', true))
                ->count();

            if ($withoutCorrect > 0) {
                $rows[] = [
                    'key' => 'questionsWithoutCorrect',
                    'to' => '/questions-main',
                    'count' => $withoutCorrect,
                    'text' => null,
                    'hint_key' => 'manager.attention.questionsWithoutCorrect',
                ];
            }
        }

        return $rows;
    }

    /**
     * Последние попытки экзаменов: кто, сколько верных, сдал или нет.
     *
     * Без содержимого вопросов и без правильных ответов — только
     * итог, чтобы страница не утекала материал банка.
     */
    private function recent(User $actor, ?array $scope): array
    {
        if (! ($actor->isSuperAdmin() || $actor->hasPermission('exams.manage') || $actor->hasPermission('exams.take'))) {
            return [];
        }

        $query = ExamAttempt::query()
            ->with(['exam:id,title', 'user:id,fio'])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(8);

        $this->applyGroupScopeOnAttempts($query, $actor, $scope);

        return $query->get()->map(fn (ExamAttempt $attempt) => [
            'id' => $attempt->id,
            'exam' => $attempt->exam?->title,
            'user' => $attempt->user?->fio,
            'score' => $attempt->score === null ? null : (float) $attempt->score,
            'correct_count' => (int) $attempt->correct_count,
            'total_count' => (int) $attempt->total_count,
            'passed' => (bool) $attempt->passed,
            'submitted_at' => $attempt->submitted_at,
        ])->all();
    }
}
