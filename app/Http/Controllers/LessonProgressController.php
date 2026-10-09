<?php

namespace App\Http\Controllers;

use App\Models\Aukstructure;
use App\Models\Course;
use App\Models\LessonProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Прогресс обучаемого по урокам курса.
 *
 * Раньше посещённые уроки хранились в localStorage одного браузера.
 * Теперь состояние в базе, поэтому «продолжить обучение» работает на
 * любом устройстве, а процент на дашборде считается по реальным данным,
 * а не по тому, что браузер запомнил на этой машине.
 *
 * Запись идёт по паре (курс, урок). Право открыть курс проверяется
 * через CourseAccess — записаться в чужой курс и накрутить себе
 * процент нельзя.
 */
class LessonProgressController extends Controller
{
    /**
     * Прогресс по курсу: все уроки и состояние каждого.
     *
     * GET /api/my/progress/{course}
     */
    public function show(Request $request, Course $course)
    {
        $user = $request->user();
        abort_if($user === null, 401);

        \App\Support\CourseAccess::authorizeOpen($user, $course);

        $lessons = Aukstructure::where('course_id', $course->id)
            ->orderBy('parent_id')
            ->orderBy('id')
            ->get(['id', 'parent_id', 'title']);

        $progress = LessonProgress::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->get()
            ->keyBy('lesson_id');

        $rows = $lessons->map(function (Aukstructure $lesson) use ($progress) {
            $row = $progress->get($lesson->id);

            return [
                'lesson_id' => $lesson->id,
                'parent_id' => $lesson->parent_id,
                'title' => $lesson->title,
                'percent' => $row?->percent ?? 0,
                'completed' => $row !== null && $row->isCompleted(),
                'last_file' => $row?->last_file,
                'last_anchor' => $row?->last_anchor,
                'last_viewed_at' => $row?->last_viewed_at?->toIso8601String(),
            ];
        });

        // Процент по курсу — среднее по урокам, а не «хоть что-то открыто».
        // Иначе один открытый урок из двадцати выглядит как 100%.
        $percent = $rows->isEmpty()
            ? 0
            : (int) round($rows->avg('percent'));

        return response()->json([
            'data' => [
                'course_id' => $course->id,
                'percent' => $percent,
                'completed' => $rows->where('completed', true)->count(),
                'total' => $rows->count(),
                'lessons' => $rows->values(),
            ],
        ]);
    }

    /**
     * Отметка просмотра урока.
     *
     * POST /api/my/progress
     *
     * Пишет, где остановился обучаемый. Процент приходит с клиента
     * и ограничивается сверху: иначе можно было бы отправить 100
     * одним запросом, не открыв ничего.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'lesson_id' => ['required', 'integer', 'exists:aukstructures,id'],
            'percent' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'last_file' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_anchor' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $course = Course::findOrFail($validated['course_id']);
        \App\Support\CourseAccess::authorizeOpen($user, $course);

        $lesson = Aukstructure::findOrFail($validated['lesson_id']);

        // Урок обязан принадлежать этому курсу, иначе можно было бы
        // «отметить» чужой раздел и получить 100% чужого курса.
        abort_unless((int) $lesson->course_id === (int) $course->id, 422);

        $row = LessonProgress::firstOrNew([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
        ]);

        // Процент не уменьшается: перечитывание материала не отменяет
        // уже пройденное.
        $incoming = (int) ($validated['percent'] ?? 0);
        $row->percent = max((int) $row->percent, min(100, $incoming));
        $row->course_id = $course->id;

        if (array_key_exists('last_file', $validated)) {
            $row->last_file = $validated['last_file'];
        }
        if (array_key_exists('last_anchor', $validated)) {
            $row->last_anchor = $validated['last_anchor'];
        }

        $row->last_viewed_at = Carbon::now();

        // 100% и раньше не достигнутый прогресс означают завершение.
        if ($row->percent >= 100 && $row->completed_at === null) {
            $row->completed_at = Carbon::now();
        }

        $row->save();

        return response()->json([
            'data' => [
                'course_id' => $row->course_id,
                'lesson_id' => $row->lesson_id,
                'percent' => $row->percent,
                'completed' => $row->isCompleted(),
                'last_file' => $row->last_file,
                'last_anchor' => $row->last_anchor,
                'last_viewed_at' => $row->last_viewed_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Сброс прохождения урока.
     *
     * DELETE /api/my/progress/{lesson}
     *
     * Нужен, чтобы обучаемый мог пройти заново: без сброса percent
     * не уменьшается и повторное прохождение ничего не меняет.
     */
    public function destroy(Request $request, Aukstructure $lesson)
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $course = Course::findOrFail($lesson->course_id);
        \App\Support\CourseAccess::authorizeOpen($user, $course);

        LessonProgress::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }
}