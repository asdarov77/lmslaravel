<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Course;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Объявления: чтение ленты и управление публикациями.
 *
 * Ключевое здесь — читатель. Объявления часто адресованы конкретной
 * группе или курсу, поэтому «отдавать всем, кто авторизован» означало
 * бы показывать учебному заведению внутренние объявления соседней
 * группы. Видимость решается в Announcement::visibilityFor() по тем же
 * признакам, что и меню: роли и группа из role_user, а не из строки
 * users.role.
 */
class AnnouncementController extends Controller
{
    /**
     * Лента объявлений для текущего пользователя.
     *
     * GET /api/announcements
     */
    public function index(Request $request)
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $query = Announcement::with('author:id,fio')
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', Carbon::now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', Carbon::now());
            });

        // Страница нужна и администратору: он ведёт публикации, ему
        // показываем всё, включая то, что адресовано конкретной группе.
        $isManager = $user->isSuperAdmin() || $user->hasPermission('announcements.manage');

        $announcements = $query->orderByDesc('pinned')->orderByDesc('published_at')->get()
            ->filter(fn (Announcement $a) => $isManager || $a->visibilityFor($user))
            ->values();

        return response()->json([
            'data' => $announcements->map(fn (Announcement $a) => $this->present($a)),
            'meta' => [
                'total' => $announcements->count(),
                'pinned' => $announcements->where('pinned', true)->count(),
            ],
        ]);
    }

    /**
     * Создание объявления.
     *
     * POST /api/announcements
     */
    public function store(Request $request)
    {
        $actor = $request->user();
        abort_if($actor === null, 401);
        abort_unless($actor->hasPermission('announcements.manage'), 403);

        $data = $this->validatePayload($request);

        $announcement = Announcement::create($data + ['author_id' => $actor->id]);

        return response()->json(['data' => $this->present($announcement)], 201);
    }

    /**
     * Обновление объявления.
     *
     * PUT /api/announcements/{announcement}
     */
    public function update(Request $request, Announcement $announcement)
    {
        $actor = $request->user();
        abort_if($actor === null, 401);
        abort_unless($actor->hasPermission('announcements.manage'), 403);

        $announcement->fill($this->validatePayload($request))->save();

        return response()->json(['data' => $this->present($announcement->fresh())]);
    }

    /**
     * Удаление объявления.
     *
     * DELETE /api/announcements/{announcement}
     */
    public function destroy(Request $request, Announcement $announcement)
    {
        $actor = $request->user();
        abort_if($actor === null, 401);
        abort_unless($actor->hasPermission('announcements.manage'), 403);

        $announcement->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * Справочники для формы: группы, курсы, роли.
     *
     * GET /api/announcements/audiences
     */
    public function audiences(Request $request)
    {
        $actor = $request->user();
        abort_if($actor === null, 401);
        abort_unless($actor->hasPermission('announcements.manage'), 403);

        return response()->json([
            'data' => [
                'groups' => Group::orderBy('groupname')->get(['id', 'groupname'])
                    ->map(fn ($g) => ['id' => $g->id, 'name' => $g->groupname]),
                'courses' => Course::orderBy('title')->get(['id', 'title'])
                    ->map(fn ($c) => ['id' => $c->id, 'name' => $c->title]),
                'roles' => \App\Models\Role::orderBy('rolename')->get(['id', 'rolename'])
                    ->map(fn ($r) => ['id' => $r->id, 'name' => $r->rolename]),
            ],
        ]);
    }

    /**
     * Проверка входных данных объявления.
     *
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'audience_all' => ['sometimes', 'boolean'],
            'audience_groups' => ['sometimes', 'nullable', 'array'],
            'audience_groups.*' => ['integer', 'exists:groups,id'],
            'audience_courses' => ['sometimes', 'nullable', 'array'],
            'audience_courses.*' => ['integer', 'exists:courses,id'],
            'audience_roles' => ['sometimes', 'nullable', 'array'],
            'audience_roles.*' => ['string', 'max:64'],
            'pinned' => ['sometimes', 'boolean'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:published_at'],
        ], [], [
            'title' => 'заголовок',
            'body' => 'текст',
            'expires_at' => 'срок действия',
        ]);

        /*
         * Пустая аудитория молча превращается в «всем».
         *
         * Иначе объявление без уточнения аудитории просто не увидит
         * никто, и автор решит, что публикация сломанной. Явное
         * намерение «никому» в системе не предусмотрено намеренно.
         */
        $hasTarget = ! empty($data['audience_groups'])
            || ! empty($data['audience_courses'])
            || ! empty($data['audience_roles']);

        $data['audience_all'] = array_key_exists('audience_all', $data)
            ? (bool) $data['audience_all']
            : ! $hasTarget;

        // Пустые массивы сохраняем как null: в ответе это читается как
        // «не задано», а [] выглядело бы как «выбрано пустое».
        foreach (['audience_groups', 'audience_courses', 'audience_roles'] as $key) {
            if (array_key_exists($key, $data) && empty($data[$key])) {
                $data[$key] = null;
            }
        }

        return $data;
    }

    /**
     * Представление объявления для клиента.
     *
     * @return array<string, mixed>
     */
    private function present(Announcement $announcement): array
    {
        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'body' => $announcement->body,
            'author' => $announcement->author?->fio,
            'audience_all' => (bool) $announcement->audience_all,
            'audience_groups' => $announcement->audience_groups ?? [],
            'audience_courses' => $announcement->audience_courses ?? [],
            'audience_roles' => $announcement->audience_roles ?? [],
            'pinned' => (bool) $announcement->pinned,
            'published_at' => $announcement->published_at?->toIso8601String(),
            'expires_at' => $announcement->expires_at?->toIso8601String(),
            'live' => $announcement->isLive(),
        ];
    }
}