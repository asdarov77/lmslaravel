<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Group2learning;
use App\Models\LessonProgress;
use App\Support\CourseAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Сертификаты об окончании курса.
 *
 * Сделано как печатная HTML-страница, а не как готовый PDF-файл.
 * Причина практическая: в проекте нет ни dompdf, ни snappy, и
 * тянуть генератор PDF ради одной страницы — лишняя зависимость и
 * лишние шрифты. Браузер печатает страницу в PDF тем же
 * «Сохранить как PDF», поэтому документ выходит с тем же кодом
 * проверки и без расхождений между «на экране» и «в файле».
 *
 * Условие выдачи: все уроки курса пройдены И все назначенные по
 * курсу экзамены сданы. Иначе можно было бы напечатать «сертификат»
 * по курсу, который просто закончился по датам.
 *
 * Код проверки — первые 8 символов HMAC от пары «пользователь + курс»,
 * поэтому подделать его без ключа приложения нельзя, а проверить
 * можно без базы: /api/certificates/verify/{code}.
 */
class CertificateController extends Controller
{
    /** Сколько уроков и экзаменов нужно закрыть для выдачи. */
    private const LESSON_THRESHOLD = 100;

    /**
     * Список сертификатов, доступных обучаемому.
     *
     * GET /api/my/certificates
     */
    public function index(Request $request)
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $rows = $this->planRows($user);

        return response()->json([
            'data' => collect($rows)->map(function (array $row) use ($user) {
                $status = $this->statusFor($user, $row);

                return [
                    'course_id' => $row['course']->id,
                    'title' => $row['course']->title,
                    'module_title' => $row['module_title'],
                    'completed_at' => $row['study_to'],
                    'lessons_percent' => $status['lessons_percent'],
                    'lessons_total' => $status['lessons_total'],
                    'lessons_done' => $status['lessons_done'],
                    'exams_total' => $status['exams_total'],
                    'exams_passed' => $status['exams_passed'],
                    'available' => $status['available'],
                    'code' => $status['available'] ? $this->code($user->id, $row['course']->id) : null,
                ];
            })->values(),
            'meta' => [
                'total' => count($rows),
                'available' => collect($rows)
                    ->filter(fn ($r) => $this->statusFor($user, $r)['available'])
                    ->count(),
            ],
        ]);
    }

    /**
     * Данные одного сертификата.
     *
     * GET /api/my/certificates/{course}
     */
    public function show(Request $request, Course $course)
    {
        $user = $request->user();
        abort_if($user === null, 401);

        // Сначала «есть ли такой курс у этого пользователя», и только
        // потом проверка доступа. Обратный порядок отдавал бы 403 и
        // постороннему, то есть подтверждал бы, что курс существует.
        $rows = collect($this->planRows($user))->firstWhere('course.id', $course->id);

        if ($rows === null) {
            // Курс не в плане группы: сертификат не выдаётся, даже если
            // уроки случайно закрыты.
            abort(404);
        }

        // Свою закрытую практику не показываем: сертификат выдаётся
        // за курс, который открыт пользователю.
        CourseAccess::authorizeOpen($user, $course);

        $status = $this->statusFor($user, $rows);

        abort_unless($status['available'], 403, 'Курс ещё не завершён');

        return response()->json([
            'data' => [
                'fio' => $user->fio ?? $user->name,
                'course' => $rows['course']->title,
                'module_title' => $rows['module_title'],
                'study_from' => $rows['study_from'],
                'study_to' => $rows['study_to'],
                'issued_at' => Carbon::now()->toDateString(),
                'lessons_done' => $status['lessons_done'],
                'lessons_total' => $status['lessons_total'],
                'exams_passed' => $status['exams_passed'],
                'exams_total' => $status['exams_total'],
                'code' => $this->code($user->id, $course->id),
            ],
        ]);
    }

    /**
     * Проверка кода сертификата без авторизации.
     *
     * GET /api/certificates/verify/{code}
     *
     * Публичный метод: именно он делает код полезным. Ответ содержит
     * только ФИО и название курса — без групп, дат обучения и прочего.
     */
    public function verify(string $code)
    {
        $code = strtoupper(trim($code));

        // Код несёт id пользователя и курса, поэтому проверка — это два
        // точечных запроса, а не перебор всех пар. Раньше вариант с
        // перебором на каждый запрос дёргал cursor() по всем
        // пользователям и курсам: на реальной базе это секунды.
        if (! preg_match('/^([0-9A-Z]{6})([0-9A-Z]{6})([0-9A-Z]{6})$/', $code, $m)) {
            abort(404);
        }

        $userId = $this->fromBase36($m[1]);
        $courseId = $this->fromBase36($m[2]);

        abort_unless(
            $userId !== null && $courseId !== null && hash_equals($this->signature($userId, $courseId), $m[3]),
            404
        );

        $user = \App\Models\User::find($userId);
        $course = Course::find($courseId);

        // Подпись верна, а записи уже нет — сертификат недействителен.
        if ($user === null || $course === null) {
            abort(404);
        }

        return response()->json([
            'data' => [
                'valid' => true,
                'fio' => $user->fio ?? $user->name,
                'course' => $course->title,
            ],
        ]);
    }

    /**
     * Назначения группы с курсом и модулем.
     *
     * @return array<int, array{course: Course, module_title: string|null, study_from: string|null, study_to: string|null}>
     */
    private function planRows($user): array
    {
        if ($user === null || $user->group_id === null) {
            return [];
        }

        return Group2learning::with('course:id,title')
            ->where('group_id', $user->group_id)
            ->get()
            ->map(function (Group2learning $row) {
                return [
                    'course' => $row->course,
                    'module_title' => $row->parent_id
                        ? \App\Models\Aukstructure::whereKey($row->parent_id)->value('title')
                        : null,
                    'study_from' => $row->study_from,
                    'study_to' => $row->study_to,
                ];
            })
            ->filter(fn ($row) => $row['course'] !== null)
            ->values()
            ->all();
    }

    /**
     * Готов ли сертификат по курсу.
     *
     * @return array{available: bool, lessons_percent: int, lessons_total: int, lessons_done: int, exams_total: int, exams_passed: int}
     */
    private function statusFor($user, array $row): array
    {
        $course = $row['course'];

        $lessonsTotal = \App\Models\Aukstructure::where('course_id', $course->id)->count();

        $progress = LessonProgress::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->get(['percent', 'completed_at']);

        $lessonsDone = $progress->filter(
            fn ($row) => $row->completed_at !== null || $row->percent >= self::LESSON_THRESHOLD
        )->count();

        $lessonsPercent = $lessonsTotal > 0
            ? (int) round($progress->avg('percent'))
            : ($lessonsTotal === 0 && $lessonsDone === 0 ? 100 : 0);

        // Курс без уроков не блокирует выдачу: это тестовый курс,
        // где важны только экзамены.
        $lessonsOk = $lessonsTotal === 0 ? true : $lessonsDone >= $lessonsTotal;

        $exams = Exam::where('course_id', $course->id)
            ->where(function ($q) use ($user) {
                $q->whereNull('group_id')->whereNull('user_id');

                if ($user->group_id) {
                    $q->orWhere('group_id', $user->group_id);
                }

                $q->orWhere('user_id', $user->id);
            })
            ->get(['id']);

        $examsTotal = $exams->count();
        $examsPassed = $examsTotal === 0
            ? 0
            : ExamAttempt::where('user_id', $user->id)
                ->whereIn('exam_id', $exams->pluck('id'))
                ->where('passed', true)
                ->distinct('exam_id')
                ->count('exam_id');

        return [
            'available' => $lessonsOk && $examsPassed >= $examsTotal,
            'lessons_percent' => $lessonsPercent,
            'lessons_total' => $lessonsTotal,
            'lessons_done' => $lessonsDone,
            'exams_total' => $examsTotal,
            'exams_passed' => $examsPassed,
        ];
    }

    /**
     * Код сертификата: id пользователя, id курса и подпись.
     *
     * Три блока по 6 символов в base36 — всего 18 символов. Идея в том,
     * что код сам говорит, кого и какой курс он подтверждает, поэтому
     * проверка не требует ни таблицы сертификатов, ни перебора, а
     * подделать его без ключа приложения нельзя: подпись не сойдётся.
     *
     * Алфавит base36 не содержит 0 в начале, поэтому фиксированная
     * ширина 6 символов читается однозначно: '000012' — это id 12,
     * а не «пусто + 12».
     */
    private function code(int $userId, int $courseId): string
    {
        return strtoupper(
            $this->toBase36($userId, 6)
            . $this->toBase36($courseId, 6)
            . $this->signature($userId, $courseId)
        );
    }

    /**
     * Подпись: ровно 6 символов HMAC от «пользователь:курс».
     *
     * Ровно — не украшение. hexdec от 8 hex-символов даёт число до
     * 2^32, которое в base36 занимает 7 символов, и str_pad его не
     * обрезает: код получался длиной 19-20 вместо 18, а маршрут
     * проверки объявлен с регуляркой {18}. Тогда запрос с «правильным»
     * кодом не находил маршрут и проваливался в SPA-fallback, который
     * отдавал 500. Поэтому число заранее урезается по модулю 36^6.
     */
    private function signature(int $userId, int $courseId): string
    {
        $hash = hash_hmac('sha256', "{$userId}:{$courseId}", (string) config('app.key'));
        $value = (int) hexdec(substr($hash, 0, 8)) % (36 ** 6);

        return $this->toBase36($value, 6);
    }

    /** Положительное число в base36 фиксированной ширины. */
    private function toBase36(int $value, int $width): string
    {
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $out = '';

        do {
            $out = $alphabet[$value % 36] . $out;
            $value = intdiv($value, 36);
        } while ($value > 0);

        return str_pad($out, $width, '0', STR_PAD_LEFT);
    }

    /** Обратное преобразование; null — если символы не из алфавита. */
    private function fromBase36(string $value): ?int
    {
        $result = 0;

        foreach (str_split(strtoupper($value)) as $char) {
            $digit = strpos('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', $char);

            if ($digit === false) {
                return null;
            }

            $result = $result * 36 + $digit;
        }

        return $result;
    }
}