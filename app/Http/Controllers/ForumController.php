<?php

namespace App\Http\Controllers;

use App\Models\Aukstructure;
use App\Models\Course;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\User;
use App\Support\CourseAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Форум: вопросы по курсам и ответы на них.
 *
 * Что тут принципиально:
 *
 *  1. ЧТЕНИЕ = ДОСТУП К КУРСУ. Тему по курсу, который пользователю не
 *     открыт, нельзя даже увидеть: список берётся из CourseAccess,
 *     иначе вопрос с ответом по закрытому курсу утекал бы через поиск.
 *
 *  2. ПИСАТЬ МОЖЕТ ТОТ, КТО ВИДИТ ТЕМУ. Вопрос без доступа к курсу —
 *     это либо спам, либо утечка материала в тексте вопроса.
 *
 *  3. МОДЕРАЦИЯ ОТДЕЛЕНА ОТ ПИСЬМА. Закрепить, закрыть и отметить
 *     решение может преподаватель (forum.moderate), автор может удалить
 *     только свою тему, а ответ — свой.
 *
 *  4. СЧЁТЧИК ПРОСМОТРОВ УВЕЛИЧИВАЕТСЯ НА ОТКРЫТИИ ТЕМЫ, а не на
 *     её перечислении: иначе лента «накручивала» бы просмотры сама.
 */
class ForumController extends Controller
{
    /**
     * Темы курса.
     *
     * GET /api/forum/topics?course_id=
     */
    public function index(Request $request)
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $courseId = $request->integer('course_id') ?: null;
        abort_if($courseId === null, 422, 'course_id обязателен');

        $course = Course::findOrFail($courseId);
        CourseAccess::authorizeOpen($user, $course);

        $topics = ForumTopic::with(['author:id,fio', 'lesson:id,title'])
            ->withCount('posts')
            ->where('course_id', $course->id)
            ->when($request->integer('lesson_id'), fn ($q, $lessonId) => $q->where('aukstructure_id', $lessonId))
            ->when($request->boolean('unanswered'), fn ($q) => $q->whereNull('solution_post_id'))
            ->orderByDesc('pinned')
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (ForumTopic $t) => $t->visibleFor($user))
            ->values();

        return response()->json([
            'data' => $topics->map(fn (ForumTopic $t) => $this->presentTopic($t)),
            'meta' => [
                'course_id' => $course->id,
                'total' => $topics->count(),
                'unanswered' => $topics->whereNull('solution_post_id')->count(),
            ],
        ]);
    }

    /**
     * Новая тема.
     *
     * POST /api/forum/topics
     */
    public function storeTopic(Request $request)
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'aukstructure_id' => ['nullable', 'integer', 'exists:aukstructures,id'],
            'private' => ['sometimes', 'boolean'],
        ], [], [
            'title' => 'заголовок',
            'body' => 'текст вопроса',
            'course_id' => 'курс',
        ]);

        $course = Course::findOrFail($data['course_id']);
        CourseAccess::authorizeOpen($user, $course);

        // Урок должен принадлежать этому же курсу: иначе вопрос «по
        // этому уроку» висит в другом курсе и путает читателей.
        if (! empty($data['aukstructure_id'])) {
            $lesson = Aukstructure::findOrFail($data['aukstructure_id']);
            abort_unless((int) $lesson->course_id === (int) $course->id, 422);
        }

        $topic = ForumTopic::create([
            'title' => $data['title'],
            'body' => $data['body'],
            'course_id' => $course->id,
            'aukstructure_id' => $data['aukstructure_id'] ?? null,
            'author_id' => $user->id,
            // Приватность всегда от группы, а не «просто закрыто».
            'group_id' => ($data['private'] ?? false) ? $user->group_id : null,
            'last_activity_at' => Carbon::now(),
        ]);

        return response()->json(['data' => $this->presentTopic($topic->load('author:id,fio'))], 201);
    }

    /**
     * Тема с ответами.
     *
     * GET /api/forum/topics/{topic}
     */
    public function showTopic(Request $request, ForumTopic $topic)
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $topic->loadMissing(['author:id,fio', 'lesson:id,title', 'course:id,title', 'solution']);
        CourseAccess::authorizeOpen($user, $topic->course);
        abort_unless($topic->visibleFor($user), 403);

        // Просмотр засчитывается на открытии, а не на перечислении.
        $topic->increment('views');

        $posts = $topic->posts()->with('author:id,fio')->get()
            ->map(fn (ForumPost $p) => $this->presentPost($p))
            ->values();

        return response()->json([
            'data' => array_merge($this->presentTopic($topic), ['posts' => $posts]),
            'meta' => ['can_moderate' => $user->hasPermission('forum.moderate')],
        ]);
    }

    /**
     * Ответ в теме.
     *
     * POST /api/forum/topics/{topic}/posts
     */
    public function storePost(Request $request, ForumTopic $topic)
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $topic->loadMissing('course');
        CourseAccess::authorizeOpen($user, $topic->course);
        abort_unless($topic->visibleFor($user), 403);

        // Закрытая тема не принимает ответов: разбор окончен, и тема
        // иначе расползлась бы на десятки сообщений «а ещё вопрос».
        if ($topic->locked && ! $user->hasPermission('forum.moderate')) {
            abort(403, 'Тема закрыта для новых ответов');
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ], [], ['body' => 'текст ответа']);

        $post = ForumPost::create([
            'forum_topic_id' => $topic->id,
            'author_id' => $user->id,
            'body' => $data['body'],
            // Ответ сотрудника помечается: читателю важно, что это не
            // ещё один вопрос от обучаемого.
            'from_staff' => $user->hasPermission('forum.moderate'),
        ]);

        // Активность темы определяет порядок в ленте, поэтому она
        // обновляется на каждом ответе.
        $topic->forceFill(['last_activity_at' => Carbon::now()])->save();

        return response()->json([
            'data' => $this->presentPost($post->load('author:id,fio')),
        ], 201);
    }

    /**
     * Модерация темы: закрепить, закрыть, снять решение.
     *
     * PATCH /api/forum/topics/{topic}
     */
    public function updateTopic(Request $request, ForumTopic $topic)
    {
        $user = $request->user();
        abort_if($user === null, 401);
        abort_unless($user->hasPermission('forum.moderate'), 403);

        $topic->loadMissing('course');
        CourseAccess::authorizeOpen($user, $topic->course);

        $data = $request->validate([
            'pinned' => ['sometimes', 'boolean'],
            'locked' => ['sometimes', 'boolean'],
            'solution_post_id' => ['nullable', 'integer', 'exists:forum_posts,id'],
        ]);

        // Решением может быть только ответ ИЗ ЭТОЙ темы: иначе можно
        // было бы сослаться на чужой пост и пометить тему решённой.
        if (! empty($data['solution_post_id'])) {
            $post = ForumPost::findOrFail($data['solution_post_id']);
            abort_unless((int) $post->forum_topic_id === (int) $topic->id, 422);
        }

        $topic->fill(array_intersect_key($data, array_flip(['pinned', 'locked', 'solution_post_id'])));
        $topic->save();

        return response()->json(['data' => $this->presentTopic($topic->fresh())]);
    }

    /**
     * Удаление темы.
     *
     * DELETE /api/forum/topics/{topic}
     */
    public function destroyTopic(Request $request, ForumTopic $topic)
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $topic->loadMissing('course');
        CourseAccess::authorizeOpen($user, $topic->course);

        $isAuthor = (int) $topic->author_id === (int) $user->id;
        abort_unless($isAuthor || $user->hasPermission('forum.moderate'), 403);

        // Автор не удаляет тему, которую уже кто-то ответил: это
        // уничтожение чужого ответа, а не своей заметки.
        if ($isAuthor && ! $user->hasPermission('forum.moderate') && $topic->posts()->exists()) {
            abort(403, 'На тему уже есть ответы — удалить её может только преподаватель');
        }

        $topic->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * Удаление ответа.
     *
     * DELETE /api/forum/posts/{post}
     */
    public function destroyPost(Request $request, ForumPost $post)
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $post->loadMissing('topic.course');

        $isAuthor = (int) $post->author_id === (int) $user->id;
        abort_unless($isAuthor || $user->hasPermission('forum.moderate'), 403);

        $topic = $post->topic;

        // Решение нельзя оставить висеть на удалённом ответе: тема
        // выглядела бы решённой, а ответа с ним нет.
        if ($topic->solution_post_id === $post->id) {
            $topic->forceFill(['solution_post_id' => null])->save();
        }

        $post->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentTopic(ForumTopic $topic): array
    {
        return [
            'id' => $topic->id,
            'title' => $topic->title,
            'body' => $topic->body,
            'course_id' => $topic->course_id,
            'lesson_id' => $topic->aukstructure_id,
            'lesson_title' => $topic->lesson?->title,
            'author' => $topic->author?->fio,
            'author_id' => $topic->author_id,
            'private' => $topic->group_id !== null,
            'pinned' => (bool) $topic->pinned,
            'locked' => (bool) $topic->locked,
            'solved' => $topic->solution_post_id !== null,
            'solution_post_id' => $topic->solution_post_id,
            'posts_count' => isset($topic->posts_count) ? (int) $topic->posts_count : $topic->posts()->count(),
            'views' => (int) $topic->views,
            'last_activity_at' => $topic->last_activity_at?->toIso8601String(),
            'created_at' => $topic->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentPost(ForumPost $post): array
    {
        return [
            'id' => $post->id,
            'body' => $post->body,
            'author' => $post->author?->fio,
            'author_id' => $post->author_id,
            'from_staff' => (bool) $post->from_staff,
            'created_at' => $post->created_at?->toIso8601String(),
        ];
    }
}