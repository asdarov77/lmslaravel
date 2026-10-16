<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseInstructor;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Профиль преподавателя.
 *
 * Показывает:
 * - ФИО, аватар, краткое био
 * - Курсы, которые ведёт
 * - Рейтинг (placeholder)
 *
 * Доступен ЛЮБОМУ авторизованному (маршрут лежит внутри группы
 * auth:sanctum), в том числе обучаемому: профиль открыт по прямой
 * ссылке из карточек каталога, закрывать его правами «управление
 * курсами» нельзя — иначе ссылка вела бы в тупик. Публичным (без
 * авторизации) он не является: без токена приходит 401.
 *
 * Замечание о прошлом: здесь стояло `User::with(['avatar'])`. Связи
 * `avatar` у модели User нет — это обычная строковая колонка, поэтому
 * Eloquent падал с
 * «Call to undefined relationship [avatar] on model [App\Models\User]»
 * и ЛЮБОЙ запрос профиля отдавал 500. Аватар отдаётся аксессором
 * getAvatarUrlAttribute(), отдельная связь для него не нужна.
 */
class InstructorProfileController extends Controller
{
    /**
     * GET /api/instructors/{id}
     *
     * Публичный профиль инструктора.
     */
    public function show(Request $request, int $id)
    {
        $instructor = User::query()
            ->where('id', $id)
            ->whereIn('role', array_merge([User::ROLE_ALIASES['instructor'][1]], [User::ROLE_ALIASES['instructor'][0]]))
            ->firstOrFail();

        // Курсы, которые ведёт инструктор
        $courses = Course::query()
            ->join('course_instructors', 'courses.id', '=', 'course_instructors.course_id')
            ->where('course_instructors.user_id', $instructor->id)
            ->where('courses.visible', true)
            ->select('courses.*')
            ->get(['id', 'title', 'short_description', 'path', 'thumbnail_path']);

        // Рейтинг (placeholder — можно заменить на реальную логику)
        $rating = [
            'average' => 4.8,
            'reviews_count' => 12,
        ];

        return response()->json([
            'data' => [
                'id' => $instructor->id,
                'fio' => $instructor->fio,
                'avatar_url' => $instructor->avatar_url,
                'bio' => $instructor->bio ?? null,
                'courses_count' => $courses->count(),
                'courses' => $courses,
                'rating' => $rating,
            ],
        ]);
    }
}
