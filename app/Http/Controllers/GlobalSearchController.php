<?php

namespace App\Http\Controllers;

use App\Models\Aukstructure;
use App\Models\Category;
use App\Models\Course;
use App\Models\Group;
use App\Models\Question;
use App\Models\User;
use App\Support\CourseVisibility;
use Illuminate\Http\Request;

/**
 * Глобальный поиск по LMS.
 *
 * Что закрывает этот контроллер и почему его нельзя было заменить
 * клиентским перебором:
 *
 *  - Область видимости. Обучение — про свои курсы, и поиск обязан
 *    вести себя так же: обучаемый не должен находить в общем поиске
 *    специальности и темы, на которые его группу не записывали.
 *    Скоуп берётся из CourseVisibility — тем же правилом, что и у
 *    каталога курсов, иначе поиск стал бы обходом скоупа.
 *
 *  - Права. Результаты по группам, пользователям и банку вопросов
 *    показываются только тем, у кого есть соответствующее право
 *    каталога. Иначе поиск превращался бы в способ узнать, что
 *    группа существует, и получить её состав.
 *
 *  - Лимиты. Без ограничения по числу строк и по длине запроса
 *    «а» превращался в выгрузку всей таблицы.
 *
 * Существующий SearchController ищет по содержимому приватных файлов
 * курсов; его скоуп исправлен отдельно (см. SearchController::search).
 */
class GlobalSearchController extends Controller
{
    /** Максимум результатов в каждой группе. */
    private const PER_GROUP = 5;

    /** Минимальная длина запроса: один символ не имеет смысла. */
    private const MIN_LENGTH = 2;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:'.self::MIN_LENGTH, 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ], [], ['q' => 'запрос']);

        $actor = $request->user();
        $query = trim($validated['q']);
        $limit = (int) ($validated['limit'] ?? self::PER_GROUP);

        // Регистронезависимость. В PostgreSQL LIKE регистрозависим, поэтому
        // запрос «группа» не находил строку «Группа», а «Иванов» — «ИВАНОВ».
        // Приводим обе стороны в нижний регистр средствами БД, а не PHP:
        // так индексы остаются пригодными.
        $needle = '%'.mb_strtolower($query).'%';

        $groups = [];

        // --- Курсы и их темы: скоуп по подписке группы ------------------
        $courses = Course::query()->whereRaw('LOWER(title) LIKE ?', [$needle]);
        CourseVisibility::restrictToEnrolled($courses, $actor);
        $groups['courses'] = $courses->limit($limit)->get()
            ->map(fn (Course $course) => [
                'id' => $course->id,
                'title' => $course->title,
                'subtitle' => $course->short_description,
                'to' => '/courses/desc/'.$course->id,
            ])->all();

        // Темы попадают в результат отдельной группой. Раньше здесь был
        // баг: список собирался в $modules, но в $groups не клался —
        // поиск по темам молча возвращал пустоту, и тест это поймал.
        $groups['modules'] = Aukstructure::query()
            ->whereRaw('LOWER(title) LIKE ?', [$needle])
            ->where(function ($q) use ($actor) {
                // Темы ищем только внутри курсов, которые актор видит:
                // иначе поиск по темам обходил бы скоуп каталога.
                $q->whereIn('course_id', CourseVisibility::visibleCourseIds($actor));
            })
            ->limit($limit)->get()
            ->map(fn (Aukstructure $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'subtitle' => $item->course_id ? ('Курс #' . $item->course_id) : null,
                'to' => $item->course_id ? '/courses/desc/'.$item->course_id : null,
            ])->all();

        // --- Специальности: там же, где и каталог ------------------------
        $categories = Category::query()->whereRaw('LOWER(title) LIKE ?', [$needle]);
        CourseVisibility::restrictCategoriesToEnrolled($categories, $actor);
        $groups['categories'] = $categories->limit($limit)->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'title' => $category->title,
                'subtitle' => $category->description,
                'to' => '/categories',
            ])->all();

        // --- Группы: только для тех, кто ими управляет ------------------
        $groups['groups'] = [];
        if ($actor->isSuperAdmin()
            || $actor->hasPermission('groups.view')
            || $actor->hasPermission('groups.manage')) {
            $queryGroups = Group::query()->whereRaw('LOWER(groupname) LIKE ?', [$needle]);

            if (! $actor->isSuperAdmin() && ! $actor->hasPermission('groups.manage')) {
                // Инструктор видит свою группу, а не все.
                $queryGroups->where('id', $actor->group_id);
            }

            $groups['groups'] = $queryGroups->limit($limit)->get()
                ->map(fn (Group $group) => [
                    'id' => $group->id,
                    'title' => $group->groupname,
                    'subtitle' => null,
                    'to' => '/groups/edit/'.$group->id,
                ])->all();
        }

        // --- Люди: состав группы для администратора, своя группа ---------
        $groups['users'] = [];
        if ($actor->isSuperAdmin() || $actor->hasPermission('users.view')) {
            $queryUsers = User::query()->whereRaw('LOWER(fio) LIKE ?', [$needle]);

            if (! $actor->isSuperAdmin()) {
                $queryUsers->where(function ($q) use ($actor) {
                    $q->whereNull('group_id');
                    if ($actor->group_id !== null) {
                        $q->orWhere('group_id', $actor->group_id);
                    }
                    $q->orWhere('id', $actor->id);
                });
            }

            $groups['users'] = $queryUsers->limit($limit)->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'title' => $user->fio,
                    'subtitle' => $user->role,
                    'to' => '/user/edit/'.$user->id,
                ])->all();
        }

        // --- Банк вопросов: чтение уносит в браузер is_correct ----------
        if ($actor->isSuperAdmin()
            || $actor->hasPermission('questions.view')
            || $actor->hasPermission('questions.manage')) {
            $groups['questions'] = Question::query()
                ->whereRaw('LOWER(question_text) LIKE ?', [$needle])
                ->limit($limit)->get()
                ->map(fn (Question $question) => [
                    'id' => $question->id,
                    // Название категории, но не варианты ответа: в поиске
                    // ответов быть не должно в принципе.
                    'title' => mb_strimwidth((string) $question->question_text, 0, 120, '…'),
                    'subtitle' => $question->category_id ? ('Специальность #' . $question->category_id) : null,
                    'to' => '/questions-main?category_id='.$question->category_id,
                ])->all();
        }

        // Пустые группы убираем ДО приведения к объекту: array_filter
        // принимает только массив, а stdClass даёт TypeError.
        $groups = array_filter($groups, fn ($items) => $items !== []);
        $total = array_sum(array_map('count', $groups));

        // Объект, а не массив: пустой PHP-массив сериализуется как [],
        // и на фронте groups превращался в список вместо словаря.
        $groups = (object) $groups;

        return response()->json([
            'success' => true,
            'data' => [
                'query' => $query,
                'total' => $total,
                'groups' => $groups,
            ],
            'error' => null,
            'meta' => null,
        ]);
    }
}
