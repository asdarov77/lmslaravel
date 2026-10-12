<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\User;
use App\Support\CourseAccess;
use App\Support\Notifications;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Домашние задания и работы.
 *
 * Что тут принципиально:
 *
 *  1. ОБУЧАЕМЫЙ ВИДИТ ТОЛЬКО СВОИ. Задание выдано группе, поэтому в
 *     список попадают задания его группы, а не всех подряд: иначе
 *     список домашних работ соседней группы — это готовый список
 *     чужих тем.
 *
 *  2. ПРЕПОДАВАТЕЛЬ ЧИТАЕТ РАБОТЫ ПО ПРАВАМ, А НЕ ПО СВОЕЙ ГРУППЕ.
 *     Отдельного назначения преподавателя на группу в системе нет, и
 *     выдумывать его здесь означало бы, что на странице заданий
 *     действует другое правило, чем в отчётах и на дашборде.
 *
 *  3. ОЦЕНКА ВЫШЕ МАКСИМУМА НЕВОЗМОЖНА. max_score задаётся в
 *     задании, и проверка сверяется с ним: иначе «12 из 5» повесило
 *     бы средний балл и порог зачёта.
 *
 *  4. ПЕРЕСДАЧА ОБНОВЛЯЕТ СТРОКУ, А НЕ ПЛЮДИТ ДУБЛИ. Иначе через
 *     три попытки в журнале было бы три работы на одно задание.
 */
class AssignmentController extends Controller
{
    /** Куда кладутся работы. */
    private const DISK = 'public';

    /**
     * Список заданий.
     *
     * GET /api/assignments?course_id=
     */
    public function index(Request $request)
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $courseId = $request->integer('course_id') ?: null;

        $query = Assignment::with(['course:id,title', 'lesson:id,title'])
            ->withCount('submissions')
            ->when($courseId, fn ($q, $id) => $q->where('course_id', $id));

        if ($this->mayManage($user)) {
            /*
             * Преподавателю видны все задания, включая черновики:
             * он их и создаёт, а правило «свои группы» в системе не
             * определено — отдельного назначения преподавателя на
             * группу просто нет.
             */
        } else {
            $query->where('group_id', $user->group_id ?? 0);

            // Черновики обучаемому не показываются: он не должен
            // видеть задание раньше преподавателя.
            $query->whereNotNull('published_at');
        }

        $assignments = $query->orderBy('due_at')->orderBy('id')->get();

        return response()->json([
            'data' => $assignments->map(fn (Assignment $a) => $this->present($a, $user))->values(),
            'meta' => [
                'total' => $assignments->count(),
                'overdue' => $assignments->filter(fn (Assignment $a) => $a->isOverdue())->count(),
            ],
        ]);
    }

    /**
     * Создание задания.
     *
     * POST /api/assignments
     */
    public function store(Request $request)
    {
        $actor = $request->user();
        abort_if($actor === null, 401);
        abort_unless($this->mayManage($actor), 403);

        $data = $this->validatePayload($request, $actor);

        $assignment = Assignment::create($data + ['author_id' => $actor->id]);

        if (! empty($assignment->published_at)) {
            $this->notifyGroup($assignment, $actor);
        }

        return response()->json(['data' => $this->present($assignment, $actor)], 201);
    }

    /**
     * Задание и работы.
     *
     * GET /api/assignments/{assignment}
     */
    public function show(Request $request, Assignment $assignment)
    {
        $actor = $request->user();
        abort_if($actor === null, 401);

        abort_unless($this->canSee($actor, $assignment), 403);

        $assignment->load(['course:id,title', 'lesson:id,title', 'group:id,groupname']);

        $submissions = $this->mayManage($actor)
            ? $assignment->submissions()->with('user:id,fio')->get()
            : $assignment->submissions()->where('user_id', $actor->id)->get();

        return response()->json([
            'data' => array_merge($this->present($assignment, $actor), [
                'description' => $assignment->description,
                'submissions' => $submissions->map(fn (AssignmentSubmission $s) => $this->presentSubmission($s, $actor))->values(),
            ]),
            'meta' => ['can_review' => $this->mayManage($actor)],
        ]);
    }

    /**
     * Сдача работы.
     *
     * POST /api/assignments/{assignment}/submissions
     */
    public function submit(Request $request, Assignment $assignment)
    {
        $actor = $request->user();
        abort_if($actor === null, 401);

        $assignment->load('course');
        CourseAccess::authorizeOpen($actor, $assignment->course);
        abort_unless((int) $assignment->group_id === (int) $actor->group_id, 403);

        /*
         * Дедлайн проверяется здесь, а не только формой.
         *
         * Скрытие кнопки — это удобство, а не правило: запрос, отправленный
         * после срока, принимался и попадал в журнал как вовремя сданная
         * работа. Проверка обязана быть на сервере, где её нельзя обойти.
         */
        abort_if($assignment->isOverdue(), 403, 'Срок сдачи прошёл');

        $validated = $request->validate([
            'file' => ['nullable', 'file', 'max:20480'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'file' => 'файл работы',
        ]);

        if (empty($validated['file']) && blank($validated['comment'] ?? null)) {
            // Пустая сдача — это не сдача: без файла и комментария
            // строка создала бы «работу» без содержания.
            abort(422, 'Приложите файл или напишите комментарий');
        }

        $submission = AssignmentSubmission::firstOrNew([
            'assignment_id' => $assignment->id,
            'user_id' => $actor->id,
        ]);

        if (! empty($validated['file'])) {
            $file = $validated['file'];

            // Случайное имя: исходное имя файла пришло от клиента и
            // может содержать путь или перезаписать чужую работу.
            $name = Str::random(40) . '.' . ($file->extension() ?: 'bin');
            $path = $file->storeAs(
                'assignments/' . $assignment->id . '/' . $actor->id,
                $name,
                self::DISK
            );

            $submission->file_path = $path;
            $submission->file_name = $file->getClientOriginalName();
        }

        if (array_key_exists('comment', $validated)) {
            $submission->comment = $validated['comment'];
        }

        $submission->status = 'submitted';
        $submission->submitted_at = Carbon::now();
        // Пересдача снимает старую проверку: иначе обновлённая работа
        // продолжала бы показывать оценку за прошлую версию.
        $submission->score = null;
        $submission->feedback = null;
        $submission->reviewer_id = null;
        $submission->reviewed_at = null;
        $submission->save();

        return response()->json([
            'data' => $this->presentSubmission($submission, $actor),
        ], 201);
    }

    /**
     * Проверка работы.
     *
     * POST /api/assignments/{assignment}/submissions/{submission}
     */
    public function review(Request $request, Assignment $assignment, AssignmentSubmission $submission)
    {
        $actor = $request->user();
        abort_if($actor === null, 401);
        abort_unless($this->mayManage($actor), 403);

        // Работа должна принадлежать этому заданию: иначе можно было бы
        // проверить чужую работу, подставив её id в путь.
        abort_unless((int) $submission->assignment_id === (int) $assignment->id, 422);

        $validated = $request->validate([
            'score' => ['required', 'integer', 'min:0', 'max:' . (int) $assignment->max_score],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'score' => 'балл',
        ]);

        $submission->score = (int) $validated['score'];
        $submission->feedback = $validated['feedback'] ?? null;
        $submission->reviewer_id = $actor->id;
        $submission->reviewed_at = Carbon::now();
        $submission->status = 'reviewed';
        $submission->save();

        $this->notifyAboutReview($submission, $assignment, $actor);

        return response()->json([
            'data' => $this->presentSubmission($submission, $actor),
        ]);
    }

    /**
     * Публикация и правка задания.
     *
     * PATCH /api/assignments/{assignment}
     */
    public function update(Request $request, Assignment $assignment)
    {
        $actor = $request->user();
        abort_if($actor === null, 401);
        abort_unless($this->mayManage($actor), 403);

        $data = $this->validatePayload($request, $actor, $assignment);

        $wasPublished = $assignment->published_at !== null;
        $assignment->fill($data)->save();

        // Уведомляем только при первой публикации: правка текста после
        // публикации не должна слать уведомление заново.
        if (! $wasPublished && $assignment->published_at !== null) {
            $this->notifyGroup($assignment, $actor);
        }

        return response()->json(['data' => $this->present($assignment->fresh(), $actor)]);
    }

    /**
     * Удаление задания.
     *
     * DELETE /api/assignments/{assignment}
     */
    public function destroy(Request $request, Assignment $assignment)
    {
        $actor = $request->user();
        abort_if($actor === null, 401);
        abort_unless($this->mayManage($actor), 403);

        // Файлы работ удаляются вместе с заданием: иначе они остаются
        // в storage навсегда, без ссылок и без возможности удалить их.
        foreach ($assignment->submissions()->whereNotNull('file_path')->pluck('file_path') as $path) {
            Storage::disk(self::DISK)->delete($path);
        }

        $assignment->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * Проверка и нормализация полей задания.
     *
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, User $actor, ?Assignment $assignment = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'aukstructure_id' => ['nullable', 'integer', 'exists:aukstructures,id'],
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'due_at' => ['nullable', 'date'],
            'max_score' => ['nullable', 'integer', 'min:1', 'max:100'],
            'published_at' => ['nullable', 'date'],
        ], [], [
            'title' => 'название',
            'course_id' => 'курс',
            'group_id' => 'группа',
            'due_at' => 'срок сдачи',
        ]);

        $course = Course::findOrFail($validated['course_id']);

        if (! empty($validated['aukstructure_id'])) {
            $lesson = \App\Models\Aukstructure::findOrFail($validated['aukstructure_id']);
            abort_unless((int) $lesson->course_id === (int) $course->id, 422, 'Урок принадлежит другому курсу');
        }

        $validated['max_score'] = (int) ($validated['max_score'] ?? 5);

        // Правка задания не должна ломать уже выставленные оценки:
        // max_score уменьшаем только если оценок ещё нет.
        if ($assignment !== null && isset($validated['max_score']) && $assignment->submissions()->whereNotNull('score')->exists()) {
            $maxScore = $assignment->submissions()->whereNotNull('score')->max('score');

            if ($validated['max_score'] < (int) $maxScore) {
                abort(422, 'Нельзя уменьшить максимальный балл ниже уже выставленных оценок');
            }
        }

        return $validated;
    }

    /**
     * Видно ли задание этому пользователю.
     *
     * Преподавателю (по тем же правам, что и в отчётах) видны все
     * задания, включая черновики: он их и создаёт. Обучаемому — только
     * опубликованные задания своей группы.
     */
    private function canSee(User $user, Assignment $assignment): bool
    {
        if ($this->mayManage($user)) {
            return true;
        }

        return (int) $assignment->group_id === (int) $user->group_id && $assignment->isPublished();
    }

    private function mayManage(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasPermission('grading.manage')
            || $user->hasPermission('groups.manage')
            || $user->hasPermission('courses.manage');
    }

    /** Уведомляет группу о новом задании. */
    private function notifyGroup(Assignment $assignment, User $actor): void
    {
        try {
            $users = User::where('group_id', $assignment->group_id)->get();

            Notifications::toGroupUsers(
                $users,
                $actor,
                'assignment',
                'Новое домашнее задание: ' . $assignment->title,
                mb_substr((string) $assignment->description, 0, 200),
                '/assignments/' . $assignment->id,
                ['dedup_key' => 'assignment:' . $assignment->id]
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Уведомляет обучаемого о проверке работы. */
    private function notifyAboutReview(AssignmentSubmission $submission, Assignment $assignment, User $actor): void
    {
        try {
            Notifications::toUser(
                $submission->user,
                'assignment_reviewed',
                'Работа проверена: ' . $assignment->title,
                $submission->score !== null
                    ? 'Балл: ' . $submission->score . ' из ' . $assignment->max_score
                    : 'Работа проверена без балла',
                '/assignments/' . $assignment->id,
                [
                    'dedup_key' => 'assignment_reviewed:' . $submission->id . ':' . (int) $submission->reviewed_at->timestamp,
                    'assignment_id' => $assignment->id,
                ]
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Assignment $assignment, User $viewer): array
    {
        $own = $this->mayManage($viewer) ? null : $assignment->submissions()->where('user_id', $viewer->id)->first();

        return [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'course_id' => $assignment->course_id,
            'course' => $assignment->course?->title,
            'lesson_id' => $assignment->aukstructure_id,
            'lesson' => $assignment->lesson?->title,
            'group_id' => $assignment->group_id,
            'group' => $assignment->group?->groupname,
            'due_at' => $assignment->due_at?->toIso8601String(),
            'max_score' => (int) $assignment->max_score,
            'published' => $assignment->isPublished(),
            'overdue' => $assignment->isOverdue(),
            'submissions_count' => isset($assignment->submissions_count)
                ? (int) $assignment->submissions_count
                : $assignment->submissions()->count(),
            // Свою сдачу обучаемому показываем сразу: прогресс по
            // заданию — это «сдано/нет», а не «есть ли где-то работа».
            'own_submission' => $own ? $this->presentSubmission($own, $viewer) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSubmission(AssignmentSubmission $submission, User $viewer): array
    {
        $isOwn = (int) $submission->user_id === (int) $viewer->id;

        return [
            'id' => $submission->id,
            'assignment_id' => $submission->assignment_id,
            'user_id' => $submission->user_id,
            // ФИО чужой работы обучаемему не нужно: это персональные
            // данные, а ему показывают только свою.
            'user' => ($isOwn || $this->mayManage($viewer)) ? $submission->user?->fio : null,
            'file_name' => $submission->file_name,
            'file_url' => $submission->file_path && ($isOwn || $this->mayManage($viewer))
                ? $this->fileUrl($submission->file_path)
                : null,
            'comment' => $isOwn || $this->mayManage($viewer) ? $submission->comment : null,
            'score' => $isOwn || $this->mayManage($viewer) ? $submission->score : null,
            'feedback' => $isOwn || $this->mayManage($viewer) ? $submission->feedback : null,
            'reviewed' => $submission->isReviewed(),
            'submitted_at' => $submission->submitted_at?->toIso8601String(),
            'reviewed_at' => $submission->reviewed_at?->toIso8601String(),
        ];
    }

    private function fileUrl(string $path): string
    {
        return Storage::disk(self::DISK)->url($path);
    }
}