<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Policies\ExamPolicy;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Экзамены: назначение, выдача вопросов, приём попыток.
 *
 * Что здесь принципиально:
 *
 *  1. ПРАВИЛЬНЫЕ ОТВЕТЫ НИКОГДА НЕ УХОДЯТ НА КЛИЕНТ.
 *     Раньше /api/questions отдавал все ответы вместе с is_correct, а
 *     страница экзамена считала результат у себя в браузере. Это значило,
 *     что «сдать экзамен на пять» можно было не отвечая: правильные
 *     ответы лежали в JSON-ответе, а результат нигде не проверялся.
 *     Здесь клиент получает вопросы без is_correct, а проверка идёт
 *     на сервере по отправленным answer_id.
 *
 *  2. ПОПЫТКА ПИШЕТСЯ НА СЕРВЕРЕ И УЧИТЫВАЕТСЯ.
 *     Раньше метод submitTest() во фронте был написан, но ни разу не
 *     вызывался, а маршрута /api/student-answers не существовало вовсе —
 *     поэтому test_results оставались пустыми (0 строк), а дашборд
 *     показывал «Экзаменов сдано: 0» у любого, включая отученных.
 *
 *  3. ЛИМИТ ПОПЫТОК И ОКНО ПРОВЕРЯЮТСЯ НА СЕРВЕРЕ.
 *     Проверять их во фронте бессмысленно: ограничение обходится
 *     простым повтором запроса.
 */
class ExamController extends Controller
{
    public function __construct()
    {
        // Чтение — любой авторизованный (у каждого свой список, см. scope).
        // Запись — по правам управления экзаменами.
        $this->middleware('auth:sanctum')->except(['questions', 'submit']);
    }

    /**
     * Список экзаменов.
     *
     * Область видимости: методист (exams.manage) видит все, обучаемый —
     * только назначенные ему или его группе.
     */
    public function index(Request $request)
    {
        $actor = $request->user();

        $query = Exam::with(['course:id,title', 'module:id,title', 'category:id,title', 'group:id,groupname']);

        if (! $this->manages($actor)) {
            $query->where(function (Builder $q) use ($actor) {
                $q->whereNull('group_id')->whereNull('user_id');

                if ($actor->group_id) {
                    $q->orWhere('group_id', $actor->group_id);
                }

                $q->orWhere('user_id', $actor->id);
            });
        }

        return response()->json([
            'data' => $query->orderByDesc('id')->get()->map(fn (Exam $e) => $this->present($e, $actor)),
            'meta' => ['total' => $query->count()],
        ]);
    }

    /** Экзамены конкретного обучаемого — для его кабинета. */
    public function mine(Request $request)
    {
        $actor = $request->user();

        $query = Exam::with(['course:id,title', 'module:id,title', 'category:id,title', 'group:id,groupname'])
            ->where(function (Builder $q) use ($actor) {
                $q->whereNull('group_id')->whereNull('user_id');

                if ($actor->group_id) {
                    $q->orWhere('group_id', $actor->group_id);
                }

                $q->orWhere('user_id', $actor->id);
            })
            ->orderByRaw('coalesce(due_at, closes_at, opens_at) asc nulls last');

        $exams = $query->get()->map(fn (Exam $e) => $this->present($e, $actor));

        return response()->json([
            'data' => $exams,
            'meta' => [
                'total' => $exams->count(),
                'available' => $exams->where('available', true)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        // Без назначения экзамен увидит вообще весь мир (scope трактует
        // group_id = null как «все группы»), поэтому для новой записи
        // это не то же самое, что «никого не назначил».
        // validate() возвращает ТОЛЬКО переданные ключи, поэтому отсутствующие
        // group_id/user_id надо читать через ?? — иначе PHP notice и сравнение
        // с null не срабатывает, и «экзамен без назначения» проходил.
        if (($data['group_id'] ?? null) === null && ($data['user_id'] ?? null) === null) {
            return $this->fail('Нужно назначить экзамен группе или конкретному пользователю', 422);
        }

        $exam = Exam::create($data);

        return response()->json([
            'data' => $this->present($exam->fresh(['course', 'module', 'category', 'group']), $request->user()),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $exam = Exam::findOrFail($id);

        // Частичное обновление: title обязателен только при СОЗДАНИИ.
        // Раньше update() звал те же правила, что и store(), поэтому
        // PATCH с одним полем (например, сменить срок сдачи) отклонялся
        // с 422 «The title field is required» — изменить существующий
        // экзамен было невозможно, кроме как пересоздав его.
        $exam->update($this->validated($request, true));

        return response()->json([
            'data' => $this->present($exam->fresh(['course', 'module', 'category', 'group']), $request->user()),
        ]);
    }

    public function destroy($id)
    {
        // Попытки не удаляем: exam_id объявлен nullOnDelete, так что
        // история сдачи сохраняется и после снятия экзамена.
        Exam::findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    /**
     * Вопросы экзамена БЕЗ правильных ответов.
     *
     * GET /api/exams/{exam}/questions
     */
    public function questions(Request $request, $id)
    {
        $exam = Exam::with(['course', 'module', 'category'])->findOrFail($id);
        $actor = $request->user();

        if (! $this->assignedTo($exam, $actor)) {
            return $this->fail('Экзамен не назначен вам', 403);
        }

        $state = $exam->stateFor($actor);

        if (! $state['available']) {
            return $this->fail('Экзамен недоступен: '.$state['label'], 403);
        }

        $questions = $exam->questionQuery()->get()->map(function (Question $q) {
            return [
                'id' => $q->id,
                'question_text' => $q->question_text,
                // is_correct здесь и не должно быть: клиент не должен
                // знать ответ до сдачи.
                'answers' => $q->answers->map(fn ($a) => [
                    'id' => $a->id,
                    'answer' => $a->answer,
                ])->values(),
            ];
        });

        return response()->json([
            'data' => [
                'exam' => $this->present($exam, $actor),
                'questions' => $questions,
            ],
        ]);
    }

    /**
     * Приём попытки: серверная проверка и запись результата.
     *
     * POST /api/exams/{exam}/attempts
     */
    public function submit(Request $request, $id)
    {
        $exam = Exam::with(['module', 'category'])->findOrFail($id);
        $actor = $request->user();

        $validated = $request->validate([
            'answers' => ['required', 'array', 'min:1'],
            // answer_id, а не «правильно/неправильно»: иначе клиент сам
            // объявляет свой результат.
            'answers.*.question_id' => ['required', 'integer', 'exists:questions,id'],
            'answers.*.answer_id' => ['required', 'integer', 'exists:answers,id'],
        ]);

        if (! $this->assignedTo($exam, $actor)) {
            return $this->fail('Экзамен не назначен вам', 403);
        }

        $state = $exam->stateFor($actor);

        if (! $state['available']) {
            return $this->fail('Экзамен недоступен: '.$state['label'], 403);
        }

        // Сверяем ответы с правильными ИМЕННО по вопросам этого экзамена.
        $answers = DB::table('answers')
            ->join('questions', 'questions.id', '=', 'answers.question_id')
            ->whereIn('answers.id', collect($validated['answers'])->pluck('answer_id'))
            ->get(['answers.id', 'answers.question_id', 'answers.is_correct']);

        $byAnswer = $answers->keyBy('id');

        $correctIds = $exam->questionQuery()->pluck('id');

        $total = 0;
        $correct = 0;

        foreach ($validated['answers'] as $given) {
            $questionId = (int) $given['question_id'];

            // Вопрос вне экзамена не засчитываем: иначе можно было бы
            // угадать правильные ответы по всему банку разом.
            if (! $correctIds->contains($questionId)) {
                continue;
            }

            $answer = $byAnswer->get((int) $given['answer_id']);

            // Ответ, принадлежащий другому вопросу, не засчитываем.
            if ($answer === null || (int) $answer->question_id !== $questionId) {
                $total++;
                continue;
            }

            $total++;

            if ((bool) $answer->is_correct) {
                $correct++;
            }
        }

        $score = $total > 0 ? round($correct / $total, 4) : 0.0;

        $attempt = ExamAttempt::create([
            'exam_id' => $exam->id,
            'user_id' => $actor->id,
            'total_count' => $total,
            'correct_count' => $correct,
            'score' => $score,
            'passed' => $score >= (float) $exam->passing_score,
            'submitted_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'attempt_id' => $attempt->id,
                'total_count' => $total,
                'correct_count' => $correct,
                'score' => $score,
                'passed' => $attempt->passed,
                'passing_score' => (float) $exam->passing_score,
            'question_limit' => $exam->question_limit,
                'attempts_left' => max(0, $exam->max_attempts - $exam->attempts()->where('user_id', $actor->id)->count()),
            ],
        ], 201);
    }

    /**
     * История попыток текущего пользователя — для кабинета.
     */
    public function attempts(Request $request)
    {
        return response()->json([
            'data' => ExamAttempt::with('exam:id,title')
                ->where('user_id', $request->user()->id)
                ->orderByDesc('submitted_at')
                ->get(),
        ]);
    }

    /**
     * Правила валидации создания/изменения.
     *
     * @param  bool  $partial  режим частичного обновления: обязательные
     *                        поля становятся «передал — проверь».
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'title' => $partial
                ? ['sometimes', 'required', 'string', 'max:255']
                : ['required', 'string', 'max:255'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'aukstructure_id' => ['nullable', 'integer', 'exists:aukstructures,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after_or_equal:opens_at'],
            'due_at' => ['nullable', 'date'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:20'],
            'passing_score' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'question_limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);
    }

    /**
     * Есть ли право управлять экзаменами.
     *
     * Правило перенесено в ExamPolicy: там же оно доступно Gate и
     * @can. Здесь осталась тонкая обёртка, чтобы не переписывать все
     * места вызова.
     */
    private function manages(?User $actor): bool
    {
        return $actor !== null && app(ExamPolicy::class)->manage($actor);
    }

    /** Назначен ли экзамен этому пользователю. */
    private function assignedTo(Exam $exam, ?User $actor): bool
    {
        if ($actor === null) {
            return false;
        }

        // Управляющему доступно всё; остальным — только назначенное.
        return app(ExamPolicy::class)->view($actor, $exam);
    }

    /**
     * Представление экзамена для клиента.
     *
     * @return array<string, mixed>
     */
    private function present(Exam $exam, ?User $actor): array
    {
        $state = $exam->stateFor($actor);

        $myAttempts = $actor
            ? $exam->attempts()->where('user_id', $actor->id)->orderByDesc('id')->get()
            : collect();

        return [
            'id' => $exam->id,
            'title' => $exam->title,
            'course_id' => $exam->course_id,
            'course_title' => $exam->course?->title,
            'module_id' => $exam->aukstructure_id,
            'module_title' => $exam->module?->title,
            'category_id' => $exam->category_id,
            'category_title' => $exam->category?->title,
            'group_id' => $exam->group_id,
            'group_name' => $exam->group?->groupname,
            'user_id' => $exam->user_id,
            'opens_at' => $exam->opens_at?->toDateTimeString(),
            'closes_at' => $exam->closes_at?->toDateTimeString(),
            'due_at' => $exam->due_at?->toDateTimeString(),
            'max_attempts' => $exam->max_attempts,
            'passing_score' => (float) $exam->passing_score,
            'question_limit' => $exam->question_limit,
            'state' => $state['key'],
            'state_label' => $state['label'],
            'available' => $state['available'],
            'attempts_used' => $myAttempts->count(),
            'attempts_left' => max(0, $exam->max_attempts - $myAttempts->count()),
            'best_score' => $myAttempts->isEmpty() ? null : (float) $myAttempts->max('score'),
            'passed' => $myAttempts->contains('passed', true),
        ];
    }

    /** Ответ с ошибкой в конверте приложения. */
    private function fail(string $message, int $status)
    {
        return response()->json([
            'success' => false,
            'data' => null,
            'error' => ['code' => (string) $status, 'message' => $message],
            'meta' => null,
        ], $status);
    }
}
