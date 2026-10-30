<?php

namespace App\Http\Controllers;

use App\Models\Aukstructure;
use App\Models\Course;
use App\Models\CourseProgress;
use App\Services\CoursePlayerService;
use App\Support\CourseAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        // Контракт: все методы требуют авторизацию (см. tests/Feature/CoursePlayerEndpointTest.php).
        // Sanctum-токен из фронтенда; сессионный guard — запасной вариант для web-сессий.
        $this->middleware(function ($request, $next) {
            if (!Auth::guard('sanctum')->check() && !Auth::check()) {
                abort(401, 'Требуется авторизация');
            }

            return $next($request);
        });
    }

    /**
     * Идентификатор текущего пользователя: sanctum-токен либо web-сессия.
     * Пустое значение — 401, а не TypeError из-за null при return type int.
     */
    private function userId(Request $request): int
    {
        $id = $request->user()?->id ?? Auth::id();

        if ($id === null) {
            abort(401, 'Требуется авторизация');
        }

        return (int) $id;
    }

    /**
     * GET /api/course/{course}/player
     *
     * Единый endpoint загрузки стартового экрана плеера.
     */
    public function show(Request $request, int $courseId)
    {
        $course = Course::findOrFail($courseId);

        // Фикс дефекта №2 (docs/TESTING-ARCHITECTURE.md): endpoint отдавал
        // структуру курса любому вошедшему по id — перебор подряд идущих
        // id читал чужие курсы. Правило доступа то же, что у content/raw:
        // запись на курс либо courses.manage/content.manage (CourseAccess).
        // Порядок: 401 (нет авторизации) → 404 (нет курса) → 403 (не записан),
        // сверено с CoursePlayerApiTest (guest→401, 999999→404, outsider→403).
        CourseAccess::authorizeOpen($request->user() ?? Auth::user(), $course);

        return response()->json($this->players->payload($course, $this->userId($request)));
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

        // Фикс дефекта №2 (продолжение): POST /progress писал resume-point в
        // ЛЮБОЙ курс по id — перебор id позволял отравлять статистику чужих
        // курсов и «отмечать прогресс» там, где пользователь не учится.
        // То же правило, что у GET /player и content/raw: 401 → 404 → 403.
        CourseAccess::authorizeOpen($request->user() ?? Auth::user(), $course);

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
            ['user_id' => $this->userId( $request), 'course_id' => $course->id],
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

        // Фикс дефекта №2 (продолжение): без проверки доступа любой вошедший
        // мог писать course_material_views в чужой курс (накрутка «прошли»,
        // порча агрегатов stats). 401 → 404 → 403 — тот же порядок, что выше.
        CourseAccess::authorizeOpen($request->user() ?? Auth::user(), $course);

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

        // P1 (аудит): firstOrNew+save — это SELECT + INSERT/UPDATE и гонка при
        // параллельных flush с двух устройств (прочитали старые секунды, сложили,
        // перезаписали). Атомарный upsert PostgreSQL: секунды накапливаются на
        // стороне БД, max_scroll — GREATEST, completed пересчитывается в том же
        // выражении. RETURNING возвращает финальное состояние без второго запроса.
        $userId = $this->userId($request);
        $row = DB::selectOne(
            'INSERT INTO course_material_views
                (user_id, course_id, aukstructure_id, seconds_viewed, max_scroll_percent,
                 completed, last_viewed_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, now(), now(), now())
             ON CONFLICT (user_id, aukstructure_id) DO UPDATE SET
                 course_id          = EXCLUDED.course_id,
                 seconds_viewed     = course_material_views.seconds_viewed + EXCLUDED.seconds_viewed,
                 max_scroll_percent = GREATEST(course_material_views.max_scroll_percent, EXCLUDED.max_scroll_percent),
                 completed          = course_material_views.completed OR EXCLUDED.completed,
                 last_viewed_at     = now(),
                 updated_at         = now()
             RETURNING completed',
            [
                $userId,
                $course->id,
                $data['aukstructure_id'],
                (int) $data['seconds_viewed'],
                (int) $data['scroll_percent'],
                ((int) $data['seconds_viewed'] >= 15 || (int) $data['scroll_percent'] >= 90),
            ]
        );

        // P1 (аудит): раньше ответ тащил payload()['stats'] — перестроение всего
        // дерева ради двух чисел удваивало TTFB hottest-endpoint'а плеера
        // (POST /view вызывается каждые 20 с активного чтения). Фронт обновляет
        // visited/completed локально (useCoursePlayer.markVisited,
        // locallyCompleted в premium-плеере), агрегаты stats пересчитываются
        // только при следующем GET /player. Контракт ответа зафиксирован в
        // CoursePlayerEndpointTest::{test_view_marks_completed_by_time_or_scroll}.
        return response()->json([
            'ok' => true,
            'completed' => (bool) $row->completed,
        ]);
    }
}
