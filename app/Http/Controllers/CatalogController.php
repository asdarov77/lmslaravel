<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use App\Models\Group2learning;
use App\Support\CourseAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Витрина курсов и самостоятельная запись.
 *
 * Чем отличается от /api/courses:
 *  - /api/courses — это УЧЕБНЫЙ ПЛАН: там только то, на что записана
 *    группа пользователя (CourseVisibility);
 *  - /api/catalog — ВИТРИНА: там все опубликованные курсы с отметкой
 *    «записан / не записан», чтобы человек мог выбрать и записаться.
 *
 * Именно поэтому витрина не отдаёт содержимое курса: метаданные
 * (название, специальность, типсамолёт, число тем) — это описание
 * товара, а материал открывается только записанному (CourseAccess).
 *
 * Запись идёт только в свою группу. Право users.courses (запись
 * произвольных групп) остаётся у методиста; самостоятельная запись —
 * это courses.view плюс наличие группы.
 */
class CatalogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'min:2', 'max:120'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'mine' => ['nullable', 'boolean'],
        ]);

        $actor = $request->user();

        // Витрина — это каталог, поэтому здесь НЕ применяется
        // CourseVisibility: показывать надо все опубликованные курсы,
        // иначе записаться было бы не на что.
        $courses = Course::query()
            ->with(['categories:id,title', 'aircraft:id,title,path'])
            ->when($validated['q'] ?? null, fn ($q, $term) => $q->whereRaw('LOWER(courses.title) LIKE ?', ['%'.mb_strtolower($term).'%']))
            ->when($validated['category_id'] ?? null, fn ($q, $id) => $q->whereHas(
                'categories',
                fn ($c) => $c->where('categories.id', $id)
            ))
            ->orderBy('courses.title')
            ->get();

        // Курсы, на которые записана группа актора. Одним запросом,
        // а не по одному на курс.
        $enrolledIds = $this->enrolledCourseIds($actor);

        $items = $courses->map(fn (Course $course) => $this->present($course, $enrolledIds))->all();

        // Фильтр «только мои» удобнее клиентского: он не требует
        // загружать весь каталог целиком.
        if (! empty($validated['mine'])) {
            $items = array_values(array_filter(
                $items,
                fn (array $item) => $item['enrolled']
            ));
        }

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'categories' => Category::orderBy('title')->get(['id', 'title'])->all(),
                'meta' => [
                    'total' => count($items),
                    // Сколько всего курсов без фильтров — чтобы
                    // фильтр не выглядел «пустой витриной».
                    'all' => Course::count(),
                    'enrolled' => count($enrolledIds),
                ],
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * Записаться на курс.
     *
     * Идемпотентно: повторная запись не плодит дубли, а сразу
     * сообщает, что запись уже есть.
     */
    public function enroll(Request $request, Course $course)
    {
        $actor = $this->requireGroup($request);

        if (CourseAccess::manages($actor)) {
            // Управляющему запись не нужна: он и так видит материал.
            return $this->fail('Управляющему курсами запись не требуется', 422);
        }

        if (! $actor->hasPermission('courses.view')) {
            return $this->fail('Недостаточно прав для записи на курс', 403);
        }

        $already = Group2learning::query()
            ->where('group_id', $actor->group_id)
            ->where('course_id', $course->id)
            ->exists();

        if ($already) {
            return response()->json([
                'success' => true,
                'data' => ['course_id' => $course->id, 'enrolled' => true, 'changed' => false],
                'error' => null,
                'meta' => null,
            ]);
        }

        // Запись создаётся без указания темы: тема (parent_id) не
        // обязательна, а курс целиком открывает пользователю материал.
        DB::table('group2learnings')->insert([
            'group_id' => $actor->group_id,
            'course_id' => $course->id,
            'category_id' => $course->categories->first()?->id,
            'typeOfLesson' => 'Самостоятельная подготовка',
            'study_from' => now()->toDateString(),
            'study_to' => now()->addYear()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => ['course_id' => $course->id, 'enrolled' => true, 'changed' => true],
            'error' => null,
            'meta' => null,
        ], 201);
    }

    /**
     * Отписаться.
     *
     * Удаляются только записи СВОЕЙ группы: чужую запись самостоятельной
     * отпиской снять нельзя.
     */
    public function unenroll(Request $request, Course $course)
    {
        $actor = $this->requireGroup($request);

        $deleted = Group2learning::query()
            ->where('group_id', $actor->group_id)
            ->where('course_id', $course->id)
            ->delete();

        return response()->json([
            'success' => true,
            'data' => [
                'course_id' => $course->id,
                'enrolled' => false,
                'changed' => $deleted > 0,
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    private function requireGroup(Request $request)
    {
        $actor = $request->user();

        if ($actor->group_id === null) {
            abort(422, 'Запись на курс доступна только сотрудникам, привязанным к группе');
        }

        return $actor;
    }

    /**
     * @param  array<int,int>  $enrolledIds
     * @return array<string,mixed>
     */
    private function present(Course $course, array $enrolledIds): array
    {
        $topics = $course->aukstructures()->count();

        return [
            'id' => $course->id,
            'title' => $course->title,
            'short_description' => $course->short_description,
            'long_description' => $course->long_description,
            'aircraft' => $course->aircraft?->name,
            'categories' => $course->categories->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
            ])->all(),
            'topics' => $topics,
            'enrolled' => in_array((int) $course->id, $enrolledIds, true),
            // Ссылки на материал намеренно не отдаются: витрина
            // показывает описание, содержимое открывает CourseAccess.
            'to' => '/courses/desc/'.$course->id,
        ];
    }

    /** @return array<int,int> */
    private function enrolledCourseIds($actor): array
    {
        if ($actor->group_id === null) {
            return [];
        }

        return DB::table('group2learnings')
            ->where('group_id', $actor->group_id)
            ->distinct()
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function fail(string $message, int $status)
    {
        return response()->json([
            'success' => false,
            'data' => ['message' => $message],
            'error' => ['code' => (string) $status, 'message' => $message],
            'meta' => null,
        ], $status);
    }
}