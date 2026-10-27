<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ForumTopic;
use App\Models\Group;
use App\Models\Group2learning;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Отчёты и аналитика.
 *
 * Что здесь принципиально:
 *
 *  1. ОДНА ТОЧКА ПРАВ: reports.view. Раньше аналитики не было вовсе, и
 *     каждый, кто мог открыть сводку, получал полный срез системы.
 *     Теперь данные по группам и курсам отдаёт тот же контроллер,
 *     что и страница.
 *
 *  2. ФИЛЬТРЫ ПРИМЕНЯЮТСЯ В SQL, А НЕ В JS. Отчёт за месяц по
 *     конкретной группе не должен тянуть в браузер все строки за все
 *     годы, чтобы отбросить лишние на клиенте.
 *
 *  3. ПУСТОЙ ОТЧЁТ ЧЕСТНЫЙ, А НЕ НУЛЕВОЙ. Группа без попыток показывает
 *     прочерк по успеваемости, а не «0%»: это разные вещи, и подмена
 *     одного другим делала вид, что группа провалила экзамены.
 *
 *  4. ГРАНИЦЫ ВИДИМОСТИ — ТЕ ЖЕ, ЧТО НА ДАШБОРДЕ. Полный срез
 *     получают администратор и те, у кого есть groups.manage; остальным
 *     подставляется их группа. Расхождение с дашбордом было бы хуже
 *     любой неточности: одно и то же право открывало бы разный объём
 *     данных на разных страницах.
 */
class ReportController extends Controller
{
    /** Сколько месяцев показывать в динамике. */
    private const TIMELINE_MONTHS = 6;

    /**
     * Отчёт за период.
     *
     * GET /api/reports
     */
    public function index(Request $request)
    {
        $actor = $request->user();
        abort_if($actor === null, 401);
        abort_unless($actor->hasPermission('reports.view'), 403);

        $filters = $this->filters($request, $actor);

        return response()->json([
            'data' => [
                'filters' => $filters,
                'enrollment' => $this->enrollment($filters),
                'exams' => $this->exams($filters),
                'progress' => $this->progress($filters),
                'activity' => $this->activity($filters),
                'timeline' => $this->timeline($filters),
                'groups' => $this->groupOptions($actor),
                'courses' => $this->courseOptions(),
            ],
        ]);
    }

    /**
     * Выгрузка отчёта: CSV (по умолчанию), XLSX или PDF.
     *
     * GET /api/reports/export?report=enrollment|exams|progress|activity&format=csv|xlsx|pdf
     */
    public function export(Request $request)
    {
        $actor = $request->user();
        abort_if($actor === null, 401);
        abort_unless($actor->hasPermission('reports.view'), 403);

        $report = (string) $request->query('report', 'enrollment');
        $filters = $this->filters($request, $actor);

        $data = match ($report) {
            'enrollment' => $this->enrollment($filters),
            'exams' => $this->exams($filters),
            'progress' => $this->progress($filters),
            'activity' => $this->activity($filters),
            default => abort(422, 'Неизвестный отчёт: ' . $report),
        };

        return match ($request->query('format', 'csv')) {
            'xlsx' => $this->xlsx($report, $data),
            'pdf' => $this->pdf($report, $data),
            default => $this->csv($report, $data),
        };
    }

    /**
     * XLSX отчёта: те же строки, что и в CSV, но по-настоящему
     * формат Excel (PhpSpreadsheet), а не «CSV, переименованный в .xls».
     */
    private function xlsx(string $report, array $data): \Illuminate\Http\Response
    {
        $rows = $this->reportRows($report, $data);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rIdx => $row) {
            foreach (array_values($row) as $cIdx => $value) {
                $sheet->setCellValue([$cIdx + 1, $rIdx + 1], $value);
            }
        }

        // Шапка жирным: одиночный визуальный акцент, не оформление ради оформления.
        if (count($rows) > 0) {
            $sheet->getStyle([1, 1, count($rows[0]), 1])->getFont()->setBold(true);
        }

        $n = max(1, count($rows[0] ?? []));
        for ($i = 1; $i <= $n; $i++) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xls');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($tmp);
        $content = (string) file_get_contents($tmp);
        unlink($tmp);
        $spreadsheet->disconnectWorksheets();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="report-' . $report . '.xlsx"',
        ]);
    }

    /**
     * PDF отчёта: простая таблица через dompdf, который уже в проекте.
     */
    private function pdf(string $report, array $data): \Illuminate\Http\Response
    {
        $rows = $this->reportRows($report, $data);

        $cells = '';
        foreach ($rows as $i => $row) {
            $tag = $i === 0 ? 'th' : 'td';
            $cells .= '<tr>' . implode('', array_map(
                fn ($v) => '<' . $tag . '>' . htmlspecialchars((string) $v, ENT_QUOTES) . '</' . $tag . '>',
                array_values($row),
            )) . '</tr>';
        }

        $html = '<html><head><meta charset="utf-8"><style>table{border-collapse:collapse}td,th{border:1px solid #999;padding:4px 8px;font-size:11px}</style></head><body><table>' . $cells . '</table></body></html>';

        return app('dompdf.wrapper')->loadHtml($html)
            ->download("report-{$report}.pdf");
    }

    /**
     * Строки отчёта для csv/xlsx/pdf: общий источник, чтобы форматы
     * не разъезжались при правке колонок.
     *
     * @param  array<string, mixed>  $data
     * @return list<list<mixed>>
     */
    private function reportRows(string $report, array $data): array
    {
        $rows = [];

        switch ($report) {
            case 'enrollment':
                $rows[] = ['Группа', 'Курс', 'Начало', 'Окончание', 'Дедлайн'];

                foreach ($data['rows'] as $row) {
                    $rows[] = [
                        $row['group'], $row['course'],
                        $row['study_from'], $row['study_to'], $row['deadline'],
                    ];
                }
                break;

            case 'exams':
                $rows[] = ['Попытки', 'Сдано', 'Успеваемость, %', 'Средний балл'];
                $rows[] = [
                    $data['attempts'],
                    $data['passed'],
                    $data['percent'] ?? '',
                    $data['average'] === null ? '' : round($data['average'] * 100),
                ];
                break;

            case 'progress':
                $rows[] = ['Уроков пройдено', 'Уроков начато', 'Средний процент, %', 'Курсов'];
                $rows[] = [
                    $data['lessons_done'], $data['lessons_started'],
                    $data['percent'] ?? '', $data['courses'],
                ];
                break;

            case 'activity':
                $rows[] = ['Новых пользователей', 'Тем форума', 'Объявлений'];
                $rows[] = [$data['new_users'], $data['topics'], $data['announcements']];
                break;
        }

        return $rows;
    }

    /**
     * Нормализует фильтры и проверяет область видимости.
     *
     * @return array{from: Carbon, to: Carbon, group_id: int|null, course_id: int|null}
     */
    private function filters(Request $request, User $actor): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
        ], [], [
            'group_id' => 'группа',
            'course_id' => 'курс',
            'to' => 'период',
        ]);

        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : Carbon::today()->endOfDay();

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : $to->copy()->subMonths(3)->startOfMonth();

        // Период длиннее года обычно означает «за всё время», а отчёт
        // по всем попыткам упирается в память. Ограничение не
        // молчаливое: meta.period_trimmed скажет об отсечении.
        $trimmed = false;

        if ($from->lt($to->copy()->subYear())) {
            $from = $to->copy()->subYear();
            $trimmed = true;
        }

        $groupId = $validated['group_id'] ?? null;

        /*
         * Инструктор не может запросить чужую группу. Раньше границы
         * применялись только на дашборде, и отчёт стал бы обходом:
         * цифры всей системы в одном запросе.
         */
        if (! $this->maySeeAllGroups($actor) && $groupId !== (int) $actor->group_id) {
            $groupId = $actor->group_id !== null ? (int) $actor->group_id : null;
        }

        if ($actor->group_id === null && ! $this->maySeeAllGroups($actor)) {
            // У пользователя без группы отчёт пуст, а не «все данные».
            $groupId = 0;
        }

        return [
            'from' => $from,
            'to' => $to,
            'group_id' => $groupId,
            'course_id' => $validated['course_id'] ?? null,
        ];
    }

    /** Записи групп на курсы: кто, куда и когда. */
    private function enrollment(array $filters): array
    {
        $query = Group2learning::query()->with('group:id,groupname', 'course:id,title')
            ->whereBetween('study_from', [$filters['from']->toDateString(), $filters['to']->toDateString()]);

        $this->applyScope($query, $filters);
        $this->applyCourse($query, $filters);

        $rows = $query->get();

        return [
            'rows' => $rows->map(fn (Group2learning $row) => [
                'group' => $row->group?->groupname ?? '—',
                'course' => $row->course?->title ?? 'Курс удалён',
                'study_from' => $row->study_from,
                'study_to' => $row->study_to,
                'deadline' => $row->deadline,
            ])->values()->all(),
            'total' => $rows->count(),
            'groups' => $rows->pluck('group.groupname')->filter()->unique()->count(),
        ];
    }

    /** Экзамены: попытки и успеваемость. */
    private function exams(array $filters): array
    {
        $attempts = ExamAttempt::query()->whereBetween('created_at', [$filters['from'], $filters['to']]);

        if (($filters['group_id'] ?? null) !== null && $filters['group_id'] !== 0) {
            $attempts->whereIn('user_id', $this->userIdsForGroup((int) $filters['group_id']));
        }

        if ($filters['course_id'] !== null) {
            $attempts->whereIn('exam_id', Exam::where('course_id', $filters['course_id'])->pluck('id'));
        }

        $total = (clone $attempts)->count();
        $passed = (clone $attempts)->where('passed', true)->count();

        // Средний результат считается по попыткам, а не по людям:
        // человек с пятью попытками иначе тянул бы среднее вверх.
        $average = $total > 0 ? (float) (clone $attempts)->avg('score') : null;

        return [
            'attempts' => $total,
            'passed' => $passed,
            'percent' => $total > 0 ? (int) round($passed / $total * 100) : null,
            'average' => $average,
            'exams' => Exam::query()->when($filters['course_id'], fn ($q, $id) => $q->where('course_id', $id))->count(),
        ];
    }

    /** Прогресс по урокам. */
    private function progress(array $filters): array
    {
        $query = LessonProgress::query()->where('last_viewed_at', '>=', $filters['from']);

        if (($filters['group_id'] ?? null) !== null && $filters['group_id'] !== 0) {
            $query->whereIn('user_id', $this->userIdsForGroup((int) $filters['group_id']));
        }

        if ($filters['course_id'] !== null) {
            $query->where('course_id', $filters['course_id']);
        }

        $rows = $query->get(['course_id', 'lesson_id', 'percent', 'completed_at']);

        $done = $rows->filter(fn ($row) => $row->completed_at !== null || $row->percent >= 100)->count();

        return [
            'lessons_done' => $done,
            'lessons_started' => $rows->count(),
            'percent' => $rows->isEmpty() ? null : (int) round($rows->avg('percent')),
            'courses' => $rows->pluck('course_id')->unique()->count(),
        ];
    }

    /** Активность: люди, темы форума, объявления за период. */
    private function activity(array $filters): array
    {
        $users = User::query()->whereBetween('created_at', [$filters['from'], $filters['to']]);

        if (($filters['group_id'] ?? null) !== null && $filters['group_id'] !== 0) {
            $users->where('group_id', $filters['group_id']);
        }

        $topics = ForumTopic::query()->whereBetween('created_at', [$filters['from'], $filters['to']]);
        $this->applyCourse($topics, $filters);

        return [
            'new_users' => $users->count(),
            'topics' => $topics->count(),
            'announcements' => Announcement::query()
                ->whereBetween('created_at', [$filters['from'], $filters['to']])
                ->count(),
        ];
    }

    /** Попытки по месяцам для графика. */
    private function timeline(array $filters): array
    {
        $buckets = [];

        for ($i = self::TIMELINE_MONTHS - 1; $i >= 0; $i--) {
            $start = Carbon::today()->subMonths($i)->startOfMonth();

            $buckets[$start->format('Y-m')] = [
                'month' => $start->format('Y-m'),
                'label' => $start->translatedFormat('M Y'),
                'attempts' => 0,
                'passed' => 0,
            ];
        }

        $attempts = ExamAttempt::query()
            ->where('created_at', '>=', Carbon::today()->subMonths(self::TIMELINE_MONTHS - 1)->startOfMonth());

        if (($filters['group_id'] ?? null) !== null && $filters['group_id'] !== 0) {
            $attempts->whereIn('user_id', $this->userIdsForGroup((int) $filters['group_id']));
        }

        if ($filters['course_id'] !== null) {
            $attempts->whereIn('exam_id', Exam::where('course_id', $filters['course_id'])->pluck('id'));
        }

        $attempts->get(['created_at', 'passed'])->each(function (ExamAttempt $attempt) use (&$buckets) {
            $key = Carbon::parse($attempt->created_at)->format('Y-m');

            if (! isset($buckets[$key])) {
                return;
            }

            $buckets[$key]['attempts']++;

            if ($attempt->passed) {
                $buckets[$key]['passed']++;
            }
        });

        return array_values($buckets);
    }

    /**
     * CSV отчёта.
     *
     * @param  array<string, mixed>  $data
     */
    private function csv(string $report, array $data): \Illuminate\Http\Response
    {
        $rows = $this->reportRows($report, $data);

        $csv = "\xEF\xBB\xBF";

        foreach ($rows as $row) {
            $csv .= implode(';', array_map(fn ($value) => '"' . str_replace('"', '""', (string) $value) . '"', $row)) . "\r\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="report-' . $report . '.csv"',
        ]);
    }

    /** @param \Illuminate\Database\Eloquent\Builder $query */
    private function applyScope($query, array $filters): void
    {
        if (($filters['group_id'] ?? null) !== null && $filters['group_id'] !== 0) {
            $query->where('group_id', (int) $filters['group_id']);
        }
    }

    /** @param \Illuminate\Database\Eloquent\Builder $query */
    private function applyCourse($query, array $filters): void
    {
        if ($filters['course_id'] !== null) {
            $query->where('course_id', (int) $filters['course_id']);
        }
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    private function userIdsForGroup(int $groupId)
    {
        return User::where('group_id', $groupId)->pluck('id');
    }

    private function maySeeAllGroups(User $actor): bool
    {
        return $actor->isSuperAdmin()
            || $actor->hasPermission('users.courses')
            || $actor->hasPermission('groups.manage');
    }

    /** @return array<int, array{id: int, name: string}> */
    private function groupOptions(User $actor): array
    {
        return Group::orderBy('groupname')
            ->when(! $this->maySeeAllGroups($actor), fn ($q) => $q->where('id', $actor->group_id))
            ->get(['id', 'groupname'])
            ->map(fn (Group $group) => ['id' => $group->id, 'name' => $group->groupname])
            ->all();
    }

    /** @return array<int, array{id: int, name: string}> */
    private function courseOptions(): array
    {
        return Course::orderBy('title')
            ->get(['id', 'title'])
            ->map(fn (Course $course) => ['id' => $course->id, 'name' => $course->title])
            ->all();
    }
}