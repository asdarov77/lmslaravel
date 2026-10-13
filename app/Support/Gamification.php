<?php

namespace App\Support;

use App\Models\Aukstructure;
use App\Models\AssignmentSubmission;
use App\Models\ExamAttempt;
use App\Models\Group2learning;
use App\Models\LessonProgress;
use App\Models\PointEvent;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Геймификация: XP, бейджи, streak, лидерборд.
 *
 * Три решения, из-за которых класс выглядит именно так:
 *
 * 1. XP — сумма журнала, а не счётчик. Счётчик нельзя объяснить и
 *    нельзя починить, журнал можно пересчитать и показать человеку
 *    («за что эти баллы»).
 *
 * 2. Бейджи выводятся из агрегатов, а не раздаются в момент события.
 *    Streak и «сотня уроков» нельзя честно посчитать в момент
 *    события: streak меняется со временем сам. Синхронизация
 *    бейджей идёт при чтении профиля — это несколько счётчиков по
 *    индексу, дешевле, чем ошибка «бейдж не выдался, потому что
 *    человек не заходил на страницу в тот день».
 *
 * 3. Начисление идемпотентно по source_key. Повторный запрос
 *    прогресса, двойное нажатие «сдать» или переотправка формы не
 *    должны удваивать баллы.
 */
class Gamification
{
    /**
     * Начисляет XP за действие, если оно ещё не учтено.
     *
     * @param string      $kind       вид действия (lesson_completed и т. п.)
     * @param string      $sourceKey  уникальный ключ источника
     * @param int|null    $courseId   курс, если действие связано с курсом
     * @param Carbon|null $occurredAt когда произошло (для streak)
     * @return bool       true, если начисление состоялось
     */
    public static function award(
        User $user,
        string $kind,
        string $sourceKey,
        ?int $courseId = null,
        ?Carbon $occurredAt = null
    ): bool {
        $points = (int) config("gamification.points.{$kind}", 0);

        if ($points === 0) {
            // Вид действия не заработан в конфиге: это не ошибка
            // начисления, но и не повод молча писать нули в журнал —
            // они копились бы и делали сумму нечитаемой.
            return false;
        }

        $created = PointEvent::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'source_key' => $sourceKey,
            ],
            [
                'course_id' => $courseId,
                'kind' => $kind,
                'points' => $points,
                'occurred_at' => $occurredAt ?? Carbon::now(),
            ]
        );

        // Уже было — повторное начисление. Платим только за новую
        // запись, иначе первый же дубль испортил бы итог.
        return $created->wasRecentlyCreated;
    }

    /**
     * Агрегаты пользователя для правил бейджей.
     *
     * @return array<string, int>
     */
    public static function metrics(User $user): array
    {
        $lessons = LessonProgress::query()
            ->where('user_id', $user->id)
            ->where('percent', 100)
            ->count();

        // Экзамены считаем по курсам, а не по попыткам: сдача одного
        // экзамена пять раз — это один экзамен.
        $exams = ExamAttempt::query()
            ->where('user_id', $user->id)
            ->where('passed', true)
            ->distinct()
            ->count('exam_id');

        $courses = self::completedCourses($user);
        $certificates = $courses;

        $assignments = AssignmentSubmission::query()
            ->where('user_id', $user->id)
            ->distinct()
            ->count('assignment_id');

        return [
            'lessons' => $lessons,
            'exams' => $exams,
            'courses' => $courses,
            'certificates' => $certificates,
            'assignments' => $assignments,
            'xp' => self::totalXp($user),
            'streak' => self::streak($user),
        ];
    }

    /**
     * Курсы, по которым обучение действительно завершено.
     *
     * Считаем те же условия, что и выдача сертификата, иначе бейдж
     * «курс закрыт» мог бы появиться раньше сертификата, а человек
     * обнаружил бы это уже на странице наград.
     */
    public static function completedCourses(User $user): int
    {
        if (! $user->group_id) {
            return 0;
        }

        $courseIds = Group2learning::query()
            ->where('group_id', $user->group_id)
            ->whereNotNull('study_to')
            ->where('study_to', '<=', Carbon::now())
            ->distinct()
            ->pluck('course_id');

        if ($courseIds->isEmpty()) {
            return 0;
        }

        // Сколько уроков в каждом курсе и сколько из них закрыто.
        //
        // Сравниваются СЧИТКИ, а не min(percent): у курса, где закрыт
        // один урок из двух, строки прогресса есть только по одному, и
        // min() по нему даёт 100 — курс выглядел бы закрытым, хотя
        // второй урок человек даже не открывал.
        $totalLessons = Aukstructure::query()
            ->whereIn('course_id', $courseIds)
            ->groupBy('course_id')
            ->selectRaw('course_id, count(*) as total')
            ->pluck('total', 'course_id');

        $closedLessons = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('course_id', $courseIds)
            ->where('percent', 100)
            ->groupBy('course_id')
            ->selectRaw('course_id, count(distinct lesson_id) as closed')
            ->pluck('closed', 'course_id');

        // course_id лежит в exams, а не в попытке: попытка знает
        // только экзамен. Без join курс «без уроков» не закрылся бы
        // никогда, и запрос падал бы на несуществующем столбце.
        $passedExams = ExamAttempt::query()
            ->join('exams', 'exams.id', '=', 'exam_attempts.exam_id')
            ->where('exam_attempts.user_id', $user->id)
            ->where('exam_attempts.passed', true)
            ->whereIn('exams.course_id', $courseIds)
            ->distinct()
            ->pluck('exams.course_id')
            ->flip();

        $done = 0;

        foreach ($courseIds as $courseId) {
            $total = (int) ($totalLessons[$courseId] ?? 0);

            if ($total > 0) {
                if ((int) ($closedLessons[$courseId] ?? 0) === $total) {
                    $done++;
                }

                continue;
            }

            // Курс без уроков закрывается сданным экзаменом: иначе он
            // не закрылся бы никогда — закрывать там нечего.
            if (isset($passedExams[$courseId])) {
                $done++;
            }
        }

        return $done;
    }

    /**
     * Сумма всех начислений.
     */
    public static function totalXp(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        return (int) PointEvent::query()
            ->where('user_id', $user->id)
            ->sum('points');
    }

    /**
     * Текущий уровень по формуле floor(sqrt(xp / divisor)) + 1.
     */
    public static function level(int $xp): int
    {
        $divisor = max(1, (int) config('gamification.level.divisor', 100));

        return (int) floor(sqrt(max(0, $xp) / $divisor)) + 1;
    }

    /**
     * Сколько XP до следующего уровня.
     *
     * @return array{level: int, current: int, next: int|null, need: int}
     */
    public static function levelProgress(int $xp): array
    {
        $divisor = max(1, (int) config('gamification.level.divisor', 100));
        $level = self::level($xp);
        $next = (int) pow($level, 2) * $divisor;

        return [
            'level' => $level,
            'current' => max(0, $xp - ((int) pow($level - 1, 2) * $divisor)),
            'next' => $next,
            'need' => max(0, $next - $xp),
        ];
    }

    /**
     * Дни активности за последние N дней — основа streak и для
     * «календаря» в интерфейсе.
     *
     * @return array<int, array{date: string, points: int}>
     */
    public static function activityDays(User $user, int $days = 30): array
    {
        $since = Carbon::today()->subDays($days - 1);

        $sums = PointEvent::query()
            ->where('user_id', $user->id)
            ->where('occurred_at', '>=', $since->startOfDay())
            ->get(['occurred_at', 'points'])
            ->groupBy(fn ($event) => $event->occurred_at->toDateString())
            ->map(fn ($events) => (int) $events->sum('points'));

        $out = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i)->toDateString();
            $out[] = ['date' => $date, 'points' => $sums[$date] ?? 0];
        }

        return $out;
    }

    /**
     * Текущий streak: сколько дней подряд была активность.
     *
     * Считается от сегодня или от вчера: иначе утром, до первого
     * действия, streak у человека с вчерашним занятием обнулялся бы,
     * и это выглядело бы как потеря достижения из-за часов.
     */
    public static function streak(User $user): int
    {
        $days = PointEvent::query()
            ->where('user_id', $user->id)
            ->where('occurred_at', '>=', Carbon::today()->subDays(365))
            ->get(['occurred_at'])
            ->map(fn ($event) => $event->occurred_at->toDateString())
            ->unique();

        if ($days->isEmpty()) {
            return 0;
        }

        $set = $days->flip();
        $cursor = Carbon::today();

        if (! $set->has($cursor->toDateString())) {
            $cursor = Carbon::yesterday();

            if (! $set->has($cursor->toDateString())) {
                return 0;
            }
        }

        $streak = 0;

        while ($set->has($cursor->toDateString())) {
            $streak++;
            $cursor = $cursor->copy()->subDay();
        }

        return $streak;
    }

    /**
     * Выдаёт недостающие бейджи и убирает те, условия которых
     * перестали выполняться.
     *
     * Убирать нужно честно: счётчик уроков может уменьшиться при
     * пересчёте прогресса, и бейдж, оставшийся в галерее, был бы
     * враньём.
     */
    public static function syncBadges(User $user): void
    {
        $definitions = (array) config('gamification.badges', []);
        $metrics = self::metrics($user);

        $granted = [];

        foreach ($definitions as $key => $badge) {
            $metric = $badge['metric'] ?? null;

            if ($metric === null || (int) ($metrics[$metric] ?? 0) >= (int) ($badge['threshold'] ?? 0)) {
                $granted[] = $key;
            }
        }

        $now = Carbon::now();

        foreach ($granted as $key) {
            UserBadge::query()->firstOrCreate(
                ['user_id' => $user->id, 'badge_key' => $key],
                ['awarded_at' => $now]
            );
        }

        UserBadge::query()
            ->where('user_id', $user->id)
            ->whereNotIn('badge_key', $granted)
            ->delete();
    }

    /**
     * Галерея бейджей: все с условиями, отмеченные — выданные.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function badgeGallery(User $user): array
    {
        self::syncBadges($user);

        $granted = UserBadge::query()
            ->where('user_id', $user->id)
            ->pluck('awarded_at', 'badge_key');

        $metrics = self::metrics($user);
        $gallery = [];

        foreach ((array) config('gamification.badges', []) as $key => $badge) {
            $metric = $badge['metric'] ?? null;
            $current = (int) ($metrics[$metric] ?? 0);
            $threshold = (int) ($badge['threshold'] ?? 0);

            $gallery[] = [
                'key' => $key,
                'title' => $badge['title'] ?? $key,
                'hint' => $badge['hint'] ?? '',
                'icon' => $badge['icon'] ?? 'mdi-check-circle-outline',
                'granted' => $granted->has($key),
                'awarded_at' => $granted->get($key)?->toIso8601String(),
                'progress' => $threshold > 0
                    ? [
                        'current' => $current,
                        'total' => $threshold,
                        // Процент capped: у «сотни уроков» при 300
                        // закрытых показывать 300% бессмысленно.
                        'percent' => (int) min(100, round($current / $threshold * 100)),
                    ]
                    : null,
            ];
        }

        return $gallery;
    }

    /**
     * Лидерборд группы.
     *
     * Только своя группа: сравнивать человека с чужими группами
     * бессмысленно — состав и нагрузка у них разные.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function leaderboard(User $user, int $limit = 10): array
    {
        if (! $user->group_id) {
            return [];
        }

        // Список членов группы — из users, а не из role_user:
        // лидерборд показывает всех обучающихся группы, а не роли.
        $members = User::query()
            ->where('group_id', $user->group_id)
            ->pluck('id');

        if ($members->isEmpty()) {
            return [];
        }

        $xp = PointEvent::query()
            ->whereIn('user_id', $members)
            ->groupBy('user_id')
            ->selectRaw('user_id, sum(points) as xp')
            ->pluck('xp', 'user_id');

        $users = User::query()->whereIn('id', $members)->get();

        // Активных дней за год: тай-брейкер при равном XP.
        // Один запрос на всех, а не streak() в цикле — иначе лидерборд
        // на 30 человек делал бы 30 запросов.
        $activeDays = PointEvent::query()
            ->whereIn('user_id', $members)
            ->where('occurred_at', '>=', Carbon::today()->subDays(365))
            ->get(['user_id', 'occurred_at'])
            ->groupBy('user_id')
            ->map(fn ($events) => $events
                ->map(fn ($event) => $event->occurred_at->toDateString())
                ->unique()
                ->count());

        $rows = $users->map(fn ($member) => [
            'user_id' => (int) $member->id,
            'fio' => $member->fio ?? $member->name,
            'xp' => (int) ($xp[$member->id] ?? 0),
            'active_days' => (int) ($activeDays[$member->id] ?? 0),
        ])->all();

        usort($rows, function ($a, $b) {
            // Порядок детерминирован: иначе при равном XP строки
            // прыгали бы между обновлениями страницы.
            return [$b['xp'], $b['active_days'], $a['fio']]
                <=> [$a['xp'], $a['active_days'], $b['fio']];
        });

        foreach ($rows as $index => $row) {
            $rows[$index]['rank'] = $index + 1;
            $rows[$index]['is_me'] = (int) $row['user_id'] === (int) $user->id;
        }

        return array_slice($rows, 0, $limit);
    }
}
