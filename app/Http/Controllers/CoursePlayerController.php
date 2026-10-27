<?php

namespace App\Http\Controllers;

use App\Models\Aukstructure;
use App\Models\Course;
use App\Models\CourseMaterialView;
use App\Models\CourseProgress;
use App\Services\CoursePlayerService;
use Illuminate\Http\Request;

/**
 * API плеера курса (player-endpoint).
 *
 * Контракт ответа зафиксирован тестом tests/Feature/CoursePlayerEndpointTest.php:
 *  - дерево и признак материала считаются на сервере (Д3, Д4);
 *  - entry/resume отдаются сразу — фронту не нужно гадать по ключам (Д2)
 *    и делать лишние RTT к getlink;
 *  - прогресс пишется на сервер и читается с сервера (Д6, событийный P2).
 */
class CoursePlayerController extends Controller
{
    public function __construct(private CoursePlayerService $players)
    {
    }

    /**
     * GET /api/course/{course}/player
     *
     * Единый endpoint загрузки стартового экрана плеера.
     */
    public function show(Request $request, int $courseId)
    {
        $course = Course::findOrFail($courseId);
        $userId = $request->user()?->id;

        return response()->json($this->players->payload($course, $userId));
    }

    /**
     * POST /api/course/{course}/progress
     *
     * Сохранение точки возврата (resume-point). Вызывается «на unload»
     * и при каждой смене материала — дёшево, один upsert.
     */
    public function progress(Request $request, int $courseId)
    {
        $course = Course::findOrFail($courseId);

        $data = $request->validate([
            'last_item_id' => ['required', 'integer', 'exists:aukstructures,id'],
            'last_anchor' => ['nullable', 'string', 'max:255'],
            'percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        // узел должен принадлежать этому курсу
        abort_unless(
            Aukstructure::where('id', $data['last_item_id'])
                ->where('course_id', $course->id)->exists(),
            422,
            'Узел не принадлежит курсу'
        );

        $progress = CourseProgress::updateOrCreate(
            ['user_id' => $request->user()->id, 'course_id' => $course->id],
            [
                'last_item_id' => $data['last_item_id'],
                'last_anchor' => $data['last_anchor'] ?? null,
                'percent' => $data['percent'] ?? 0,
            ]
        );

        return response()->json(['ok' => true, 'saved_at' => $progress->updated_at]);
    }

    /**
     * POST /api/course/{course}/view
     *
     * Событийный прогресс: фронт шлёт накопленное время в кадре и
     * достигнутый процент прокрутки. completed = время >= 15с ИЛИ скролл >= 90%
     * («доскроллил» больше не единственное основание отметки).
     */
    public function view(Request $request, int $courseId)
    {
        $course = Course::findOrFail($courseId);

        $data = $request->validate([
            'aukstructure_id' => ['required', 'integer', 'exists:aukstructures,id'],
            'seconds_viewed' => ['required', 'integer', 'min:0', 'max:86400'],
            'scroll_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        abort_unless(
            Aukstructure::where('id', $data['aukstructure_id'])
                ->where('course_id', $course->id)
                ->where('type', CoursePlayerService::TYPE_MATERIAL)
                ->exists(),
            422,
            'Материал не найден в курсе'
        );

        $view = CourseMaterialView::firstOrNew([
            'user_id' => $request->user()->id,
            'aukstructure_id' => $data['aukstructure_id'],
        ]);

        $view->course_id = $course->id;
        $view->seconds_viewed = (int) $view->seconds_viewed + (int) $data['seconds_viewed'];
        $view->max_scroll_percent = max((int) $view->max_scroll_percent, (int) $data['scroll_percent']);
        $view->completed = $view->completed
            || $view->seconds_viewed >= 15
            || $view->max_scroll_percent >= 90;
        $view->last_viewed_at = now();
        $view->save();

        return response()->json([
            'ok' => true,
            'completed' => $view->completed,
            'stats' => $this->players->payload($course, $request->user()->id)['stats'],
        ]);
    }
}
