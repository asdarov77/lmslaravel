<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\GradeBoundary;
use App\Models\GradeOverride;
use App\Models\Group;
use App\Models\Group2learning;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Грейдбук преподавателя: журнал «обучаемые × экзамены».
 *
 * Что тут принципиально:
 *
 *  1. ОЦЕНКА СЧИТАЕТСЯ ИЗ СЫРОГО СЧЁТА ПО ГРАНИЦАМ, а не хранится
 *     готовой цифрой. Пороги лежат в grade_boundaries и правятся
 *     преподавателем; если бы в журнале лежала готовая «5», то после
 *     смены границ весь журнал оказался бы неверным, и пересчитывать
 *     его пришлось бы вручную.
 *
 *  2. РУЧНАЯ ОЦЕНКА ПРИОРИТЕТНЕЕ АВТОМАТИЧЕСКОЙ. Устный ответ или
 *     лабораторная работа не выражаются в доле правильных ответов,
 *     поэтому преподаватель может поставить свою оценку с
 *     комментарием — она и будет показана.
 *
 *  3. ПОКАЗЫВАЕТСЯ ЛУЧШАЯ ПОПЫТКА, а не последняя. Иначе один
 *     неудачный повтор стирал бы весь результат.
 *
 *  4. ДОСТУП — И ЧТЕНИЕ, И ЗАПИСЬ — ТОЛЬКО ПО ПРАВУ grading.manage.
 *     Обучаемый сюда не попадает: это персональные данные группы.
 */
class GradebookController extends Controller
{
    /** Пороги оценивания по возрастанию boundary. */
    public function boundaries(): array
    {
        return GradeBoundary::orderBy('boundary')->pluck('grade', 'boundary')->all();
    }

    /**
     * Матрица журнала.
     *
     * GET /api/gradebook?group_id=&course_id=
     */
    public function index(Request $request)
    {
        $actor = $request->user();
        abort_if($actor === null, 401);
        abort_unless($actor->hasPermission('grading.manage'), 403);

        $groupId = $request->integer('group_id') ?: null;
        $courseId = $request->integer('course_id') ?: null;

        $groups = Group::orderBy('groupname')->get(['id', 'groupname']);

        // Журнал по всем группам: зачастую у методиста их несколько, и
        // пустая страница без выпадающего списка выглядит как поломка.
        if ($groupId === null) {
            return response()->json([
                'data' => [
                    'groups' => $groups->map(fn ($g) => ['id' => $g->id, 'name' => $g->groupname]),
                    'courses' => [],
                    'students' => [],
                    'exams' => [],
                    'cells' => [],
                    'boundaries' => $this->boundaries(),
                ],
                'meta' => ['requires_group' => true],
            ]);
        }

        $students = User::where('group_id', $groupId)
            ->orderBy('fio')
            ->get(['id', 'fio', 'name', 'group_id']);

        // Экзамены группы: по course_id, если он задан, иначе все,
        // что назначены этой группе или конкретным её обучаемым.
        $exams = Exam::with('course:id,title')
            ->where(function ($q) use ($groupId, $courseId) {
                if ($courseId) {
                    $q->where('course_id', $courseId);
                }

                $q->where(function ($inner) use ($groupId) {
                    $inner->where('group_id', $groupId)
                        ->orWhereIn('user_id', User::where('group_id', $groupId)->select('id'));
                });
            })
            ->orderBy('id')
            ->get(['id', 'title', 'course_id', 'aukstructure_id', 'category_id', 'group_id', 'passing_score']);

        $examIds = $exams->pluck('id');

        // Лучшая попытка каждого обучаемого по каждому экзамену.
        $attempts = $examIds->isEmpty()
            ? collect()
            : ExamAttempt::whereIn('exam_id', $examIds)
                ->whereIn('user_id', $students->pluck('id'))
                ->orderByDesc('score')
                ->get(['exam_id', 'user_id', 'score', 'correct_count', 'total_count', 'submitted_at'])
                ->groupBy(fn ($a) => $a->exam_id . ':' . $a->user_id);

        $overrides = $examIds->isEmpty()
            ? collect()
            : GradeOverride::whereIn('exam_id', $examIds)
                ->whereIn('user_id', $students->pluck('id'))
                ->get()
                ->keyBy(fn ($o) => $o->exam_id . ':' . $o->user_id);

        $boundaries = $this->boundaries();

        $cells = [];

        foreach ($examIds as $examId) {
            foreach ($students as $student) {
                $key = $examId . ':' . $student->id;
                $attempt = $attempts[$key][0] ?? null;
                $override = $overrides[$key] ?? null;

                if ($attempt === null && $override === null) {
                    // Пустая ячейка шум в журнале не создаёт.
                    continue;
                }

                $cells[$key] = [
                    'score' => $attempt?->score,
                    'auto_grade' => $attempt === null ? null : $this->gradeFor((float) $attempt->score, $boundaries),
                    'manual_grade' => $override?->grade,
                    'grade' => $override?->grade ?? ($attempt === null ? null : $this->gradeFor((float) $attempt->score, $boundaries)),
                    'manual' => $override !== null,
                    'comment' => $override?->comment,
                    'correct' => $attempt?->correct_count,
                    'total' => $attempt?->total_count,
                    'submitted_at' => $attempt?->submitted_at?->toIso8601String(),
                ];
            }
        }

        return response()->json([
            'data' => [
                'groups' => $groups->map(fn ($g) => ['id' => $g->id, 'name' => $g->groupname]),
                'courses' => $this->groupCourses($groupId),
                'students' => $students->map(fn ($s) => ['id' => $s->id, 'fio' => $s->fio ?: $s->name]),
                'exams' => $exams->map(fn ($e) => [
                    'id' => $e->id,
                    'title' => $e->title,
                    'course' => $e->course?->title,
                    'passing_score' => $e->passing_score,
                ]),
                'cells' => $cells,
                'boundaries' => $boundaries,
            ],
            'meta' => [
                'group_id' => $groupId,
                'students' => $students->count(),
                'exams' => $exams->count(),
            ],
        ]);
    }

    /**
     * Ручная оценка.
     *
     * PUT /api/gradebook/cell
     *
     * Пустая оценка снимает перекрытие: ячейка снова показывает
     * автоматический результат. Это осознанное решение — иначе
     * «отменить» можно было бы только удалением записи из базы.
     */
    public function updateCell(Request $request)
    {
        $actor = $request->user();
        abort_if($actor === null, 401);
        abort_unless($actor->hasPermission('grading.manage'), 403);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'exam_id' => ['required', 'integer', 'exists:exams,id'],
            'grade' => ['nullable', 'integer', 'between:2,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $student = User::findOrFail($validated['user_id']);
        $exam = Exam::findOrFail($validated['exam_id']);

        // Преподаватель не должен ставить оценки человеку из другой
        // группы: журнал открыт по своей группе, и запись должна
        // проверяться так же строго, как чтение.
        abort_unless((int) $student->group_id === (int) $exam->group_id
            || Exam::where('id', $exam->id)->where('user_id', $student->id)->exists(),
            403, 'Обучаемый не назначен на этот экзамен');

        $grade = $validated['grade'] ?? null;

        if ($grade === null) {
            GradeOverride::where('user_id', $student->id)
                ->where('exam_id', $exam->id)
                ->delete();

            return response()->json(['data' => ['cleared' => true]]);
        }

        $override = GradeOverride::updateOrCreate(
            ['user_id' => $student->id, 'exam_id' => $exam->id],
            [
                'grade' => $grade,
                'comment' => $validated['comment'] ?? null,
                'teacher_id' => $actor->id,
            ]
        );

        return response()->json([
            'data' => [
                'user_id' => $override->user_id,
                'exam_id' => $override->exam_id,
                'grade' => $override->grade,
                'comment' => $override->comment,
                'manual' => true,
            ],
        ]);
    }

    /**
     * Выгрузка журнала в CSV.
     *
     * GET /api/gradebook/export
     *
     * Отдельная ручная сборка CSV на фронте ломалась на русских ФИО и
     * запятых в названиях экзаменов: Excel открывает такой файл как
     * одну колонку. Здесь разделитель и кавычки выставлены по RFC 4180
     * и добавлен BOM — без него Excel не понимает кириллицу.
     */
    public function export(Request $request)
    {
        $actor = $request->user();
        abort_if($actor === null, 401);
        abort_unless($actor->hasPermission('grading.manage'), 403);

        $groupId = $request->integer('group_id') ?: null;
        abort_if($groupId === null, 422);

        // Тот же сбор данных, что и в index: два разных пути к одним и
        // тем же цифрам расходились бы при любой правке.
        $payload = $this->index($request)->getData(true)['data'];

        $header = ['ФИО'];
        foreach ($payload['exams'] as $exam) {
            $header[] = $exam['title'];
        }
        $header[] = 'Средний балл';

        $rows = [$header];

        foreach ($payload['students'] as $student) {
            $row = [$student['fio']];
            $sum = 0;
            $count = 0;

            foreach ($payload['exams'] as $exam) {
                $cell = $payload['cells'][$exam['id'] . ':' . $student['id']] ?? null;

                if ($cell === null) {
                    $row[] = '';
                    continue;
                }

                // Оценка и комментарий в одной ячейке: так колонка
                // остаётся читаемой и в Excel, и в plain-тексте.
                $row[] = $cell['grade'] . ($cell['manual'] ? '*' : '');
                $sum += (int) $cell['grade'];
                $count++;
            }

            $row[] = $count ? round($sum / $count, 2) : '';
            $rows[] = $row;
        }

        $csv = "\xEF\xBB\xBF";

        foreach ($rows as $row) {
            $csv .= implode(';', array_map(function ($value) {
                return '"' . str_replace('"', '""', (string) $value) . '"';
            }, $row)) . "\r\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="gradebook.csv"',
        ]);
    }

    /** Оценка по доле правильных ответов и границам оценивания. */
    private function gradeFor(float $score, array $boundaries): ?int
    {
        if ($boundaries === []) {
            return null;
        }

        // score хранится как доля (0..1), а границы — в процентах.
        $percent = $score <= 1 ? $score * 100 : $score;
        $grade = null;

        foreach ($boundaries as $boundary => $value) {
            if ($percent >= (float) $boundary) {
                $grade = (int) $value;
            }
        }

        // Ниже нижней границы оценки нет: это «не сдано», а не двойка.
        return $grade;
    }

    /** Курсы, на которые записана группа — для фильтра журнала. */
    private function groupCourses(int $groupId): array
    {
        return Group2learning::where('group_id', $groupId)
            ->join('courses', 'courses.id', '=', 'group2learnings.course_id')
            ->orderBy('courses.title')
            ->get(['courses.id', 'courses.title'])
            ->map(fn ($row) => ['id' => (int) $row->id, 'name' => $row->title])
            ->all();
    }
}