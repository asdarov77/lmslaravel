<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateTutorQuestions;
use App\Models\Course;
use App\Models\TutorItem;
use App\Models\TutorMaterial;
use App\Models\TutorResponse;
use App\Models\TutorSession;
use App\Models\User;
use App\Support\Tutor\TutorAccess;
use App\Support\Tutor\TutorAnswerGrader;
use App\Support\Tutor\TutorClient;
use App\Support\Tutor\TutorMaterialIndexer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Тренажёр: самоподготовка по материалам назначенных курсов.
 *
 * ГРАНИЦА, КОТОРУЮ ЭТОТ КЛАСС ДЕРЖИТ: тренажёр никогда не пишет в
 * exam_attempts и не создаёт экзаменационных вопросов. Итоговая
 * аттестация остаётся на импортированном банке GIFT с серверной
 * проверкой. Если бы тренажёр влиял на оценку, то модель, ошибающаяся в
 * фактах, получала бы влияние на результат — а галлюцинаций не
 * существует, есть только более или менее заметные.
 *
 * Права: tutor.use — тренировка по своим курсам, tutor.manage —
 * индексация материалов. Список материалов для обычного пользователя
 * строится по Group2learning его группы, поэтому чужие материалы в
 * выдаче не появляются даже при прямом запросе идентификатора.
 */
class TutorController extends Controller
{
    public function __construct(
        private readonly TutorClient $client,
        private readonly TutorAnswerGrader $grader,
        private readonly TutorMaterialIndexer $indexer,
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * Состояние тренажёра: доступен ли движок и какие модели стоят.
     *
     * GET /api/v1/tutor/health
     *
     * Отдельный эндпоинт, потому что «движок недоступен» — это не
     * ошибка запроса, а состояние окружения, и фронт обязан показать его
     * до того, как пользователь начнёт тренировку.
     */
    public function health(Request $request)
    {
        $this->authorizeTutor($request, 'use');

        $health = $this->client->health();

        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => $this->client->enabled(),
                'available' => (bool) ($health['available'] ?? false),
                'model' => $health['model'] ?? $this->client->model(),
                'model_present' => (bool) ($health['model_present'] ?? false),
                'models' => $health['models'] ?? [],
                'embeddings' => $this->client->embedAvailable(),
                'backcheck' => (bool) config('tutor.backcheck', true),
                // Синхронная очередь означает, что генерация выполнится
                // прямо в HTTP-запросе. Фронт обязан это показать:
                // иначе «Начать тренинг» выглядит как зависшая страница.
                'async' => config('queue.default') !== 'sync',
                'error' => $health['error'] ?? null,
                'default_types' => config('tutor.default_types', ['mcq', 'short']),
                'question_types' => config('tutor.question_types', ['mcq', 'short', 'open']),
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * Материалы, доступные для тренировки.
     *
     * GET /api/v1/tutor/materials
     *
     * Для обучаемого — только материалы курсов, назначенных его группе.
     * Управляющему, если он включил tutor.use, — то же плюс его курсы:
     * иначе методист не смог бы проверить, как выглядит тренажёр на его
     * материале.
     */
    public function materials(Request $request)
    {
        $this->authorizeTutor($request, 'use');

        $actor = $request->user();

        $query = TutorMaterial::query()
            ->with(['course:id,title', 'category:id,title'])
            ->whereIn('status', [TutorMaterial::STATUS_INDEXED])
            ->where('chunks_count', '>', 0);

        // Область — по паре (курс, специальность), а не по курсу. Иначе
        // группа, записанная на курс в рамках своей специальности, видела
        // бы материалы остальных специальностей того же курса.
        $visible = TutorAccess::visibleMaterialIds($actor);
        $query->whereIn('id', $visible === [] ? [0] : $visible);

        $data = $query
            ->orderBy('title')
            ->get()
            ->map(fn (TutorMaterial $m) => [
                'id' => $m->id,
                'course_id' => $m->course_id,
                'course_title' => $m->course?->title,
                'category_id' => $m->category_id,
                'category_title' => $m->category?->title,
                'title' => $m->title,
                'source' => $m->source,
                'status' => $m->status,
                'chunks_count' => $m->chunks_count,
                'sessions' => $m->sessions()->where('user_id', $actor->id)->count(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $data,
            'error' => null,
            'meta' => ['total' => $data->count()],
        ]);
    }

    /**
     * Начать или продолжить сессию.
     *
     * POST /api/v1/tutor/sessions
     *
     * Идемпотентно по паре пользователь+материал: повторный клик не
     * плодит сессии, а возвращает активную. Иначе два открытых окна
     * тренажёра делили бы вопросы и портили статистику.
     */
    public function startSession(Request $request)
    {
        $this->authorizeTutor($request, 'use');

        $data = $request->validate([
            'material_id' => ['required', 'integer', 'exists:tutor_materials,id'],
            'count' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $actor = $request->user();
        $material = TutorMaterial::findOrFail($data['material_id']);

        $this->assertMaterialAllowed($actor, $material);

        if (! $material->isReady()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => $material->status === TutorMaterial::STATUS_EMPTY
                    ? 'По материалу нечего спрашивать: в нём нет текста'
                    : 'Материал ещё не подготовлен',
                'meta' => ['status' => $material->status],
            ], 409);
        }

        $session = TutorSession::firstOrCreate(
            [
                'user_id' => $actor->id,
                'material_id' => $material->id,
                'status' => TutorSession::STATUS_ACTIVE,
            ],
            ['started_at' => now(), 'context_window' => []]
        );

        $count = (int) ($data['count'] ?? config('tutor.batch_size', 6));

        $available = $session->items()->where('asked_count', '<', 3)->count();

        if ($available < 2) {
            // Метку ставим ДО постановки задания: иначе запрос вернёт
            // «вопросов нет» раньше, чем задание вообще начнёт работать,
            // и фронт перестанет опрашивать.
            $session->forceFill(['generation_started_at' => now()])->save();

            GenerateTutorQuestions::dispatch($session->id, $count);

            // При синхронной очереди задача уже отработала к этому
            // моменту, и пересчёт даёт честное «вопросы готовы».
            // Без пересчёта ответ всегда утверждал бы «готовим», даже
            // когда вопросы уже лежат в базе.
            $available = $session->items()->where('asked_count', '<', 3)->count();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->id,
                'material' => [
                    'id' => $material->id,
                    'title' => $material->title,
                    'course_title' => $material->course?->title,
                ],
                'available' => $available,
                'generating' => $session->fresh()->isGenerating(),
                'async' => config('queue.default') !== 'sync',
                'worker_stalled' => $this->isGenerationStalled($session->fresh()),
            ],
            'error' => null,
            'meta' => null,
        ], $session->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Задание ждёт обработчика очереди.
     *
     * Отличаем две ситуации, которые снаружи выглядят одинаково —
     * бесконечный спиннер:
     *
     *  1. Генерация идёт: модель думает, всё нормально.
     *  2. Задание не началось: `php artisan queue:work` не запущен.
     *
     * Признак второго — задание всё ещё лежит в таблице jobs спустя
     * пороговое время с момента постановки.
     */
    protected function isGenerationStalled(TutorSession $session): bool
    {
        if (! $session->isGenerating() || $session->generation_started_at === null) {
            return false;
        }

        $threshold = (int) config('tutor.worker_stall_seconds', 45);

        if ($threshold < 1 || $session->generation_started_at->lt(now()->subSeconds($threshold)) === false) {
            return false;
        }

        // Ищем именно наши задания по имени класса в payload: у другой
        // работы в очереди (импорт, письма) пауза не про тренажёр.
        return DB::table('jobs')
            ->where('payload', 'like', '%GenerateTutorQuestions%')
            ->exists();
    }

    /**
     * Следующий вопрос.
     *
     * GET /api/v1/tutor/sessions/{session}/next-question
     *
     * Порядок выбора: сначала непоказанные, затем показанные один раз.
     * Вопросы, заданные трижды, не предлагаются — иначе тренировка
     * превращается в одно и то же по кругу.
     */
    public function nextQuestion(Request $request, TutorSession $session)
    {
        $this->authorizeTutor($request, 'use');

        $this->assertSessionAllowed($request->user(), $session);

        $item = $session->items()
            ->where('asked_count', '<', 3)
            ->orderByRaw('asked_count asc')
            ->orderBy('id')
            ->first();

        if ($item === null) {
            return response()->json([
                'success' => true,
                'data' => null,
                'error' => null,
                'meta' => [
                    'exhausted' => true,
                    'hint' => 'Новые вопросы готовятся. Пока можно повторить пройденное.',
                ],
            ]);
        }

        $item->increment('asked_count');
        $session->touchChunk($item->chunk_id);
        $session->save();

        return response()->json([
            'success' => true,
            'data' => [
                'item' => $item->toPlayerArray(),
                'remaining' => $session->items()->where('asked_count', '<', 3)->count(),
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * Ответить на вопрос.
     *
     * POST /api/v1/tutor/items/{item}/answer
     *
     * Эталон и цитата отдаются только здесь и только по явному запросу
     * show_reference — иначе правильный ответ едет в ответе рядом с
     * вопросом, и тренажёр становится проверяемым на автомате.
     */
    public function answer(Request $request, TutorItem $item)
    {
        $this->authorizeTutor($request, 'use');

        $session = $item->session()->firstOrFail();

        $this->assertSessionAllowed($request->user(), $session);

        $data = $request->validate([
            'answer' => ['nullable', 'string', 'max:4000'],
            'seconds_spent' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'show_reference' => ['nullable', 'boolean'],
        ]);

        $result = $this->grader->grade($item, $data['answer'] ?? null);

        $response = TutorResponse::create([
            'item_id' => $item->id,
            'verdict' => $result['verdict'],
            'answer' => $data['answer'] ?? null,
            'feedback' => $result['feedback'],
            'auto_score' => $result['score'],
            'seconds_spent' => $data['seconds_spent'] ?? null,
            'llm_meta' => $result['meta'],
        ]);

        $this->recordContext($session, $item, $result['verdict']);

        $payload = [
            'response_id' => $response->id,
            'verdict' => $result['verdict'],
            'score' => $result['score'],
            'feedback' => $result['feedback'],
        ];

        if ((bool) ($data['show_reference'] ?? false)) {
            $payload['reference_answer'] = $item->reference_answer;
            $payload['source_quote'] = $item->source_quote;
        }

        return response()->json([
            'success' => true,
            'data' => $payload,
            'error' => null,
            'meta' => null,
        ], 201);
    }

    /**
     * Состояние сессии: сколько вопросов готово и сколько отвечено.
     *
     * GET /api/v1/tutor/sessions/{session}
     *
     * Отдельный метод нужен фронту после перезагрузки страницы: без него
     * окно тренажёра не знает, на каком вопросе остановилось, и
     * предлагает первый заново.
     */
    public function sessionShow(Request $request, TutorSession $session)
    {
        $this->authorizeTutor($request, 'use');
        $this->assertSessionAllowed($request->user(), $session);

        $session->loadMissing('material:id,title,course_id');

        $answered = $session->responses()->distinct('item_id')->count();
        $left = $session->items()->where('asked_count', '<', 3)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->id,
                'status' => $session->status,
                'material' => [
                    'id' => $session->material_id,
                    'title' => $session->material?->title,
                ],
                'available' => $left,
                'answered' => $answered,
                // Признак берётся из метки задания, а не из наличия
                // вопросов: наличие вопросов — следствие генерации, а
                // не признак её завершения.
                'generating' => $session->isGenerating(),
                'started_at' => optional($session->started_at)->toDateTimeString(),
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * Завершить сессию.
     *
     * POST /api/v1/tutor/sessions/{session}/finish
     *
     * Вопросы остаются в базе: их можно переиграть, а статистика по
     * ним уже собрана. Удаление здесь было бы потерей прогресса.
     */
    public function finishSession(Request $request, TutorSession $session)
    {
        $this->authorizeTutor($request, 'use');
        $this->assertSessionAllowed($request->user(), $session);

        $session->forceFill([
            'status' => TutorSession::STATUS_FINISHED,
            'finished_at' => now(),
        ])->save();

        return response()->json([
            'success' => true,
            'data' => ['session_id' => $session->id, 'status' => $session->status],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * Статистика тренажёра.
     *
     * GET /api/v1/tutor/stats
     *
     * Считается по tutor_responses и намеренно отдельно от
     * exam_attempts: сводка экзаменов не должна содержать тренировку,
     * а сводка тренажёра — экзамены. Иначе «прогресс» в одном из них
     * окажется смесью двух разных вещей.
     */
    public function stats(Request $request)
    {
        $this->authorizeTutor($request, 'use');

        $actor = $request->user();

        // Статистика тренажёра — личная: свои сессии и ответы в них.
        // Сводка по группе тут была бы соблазном показать методисту
        // «доля правильных» по обучаемым, но это уже оценка, которой
        // тренажёр не является. Если понадобится — отдельным эндпоинтом
        // для tutor.manage и с явным названием.
        // Идентификаторы собираем массивом ОДИН раз. Один и тот же
        // Builder, переданный в два подзапроса, накапливал в них
        // лишние столбцы — Postgres отвечал «слишком много столбцов»,
        // и статистика отдавала 500.
        $sessionIds = TutorSession::where('user_id', $actor->id)->pluck('id')->all();

        // select('id') обязателен: без него подзапрос выбирает все
        // столбцы tutor_items, и Postgres отвечает «слишком много
        // столбцов» — статистика отдавала 500.
        $responses = TutorResponse::query()
            ->whereIn('item_id', TutorItem::query()->select('id')->whereIn('session_id', $sessionIds));

        $total = (clone $responses)->count();
        $graded = (clone $responses)->where('verdict', '!=', TutorResponse::VERDICT_UNGRADED)->count();
        $correct = (clone $responses)->where('verdict', TutorResponse::VERDICT_CORRECT)->count();

        $byType = DB::table('tutor_responses as r')
            ->join('tutor_items as i', 'i.id', '=', 'r.item_id')
            ->whereIn('i.session_id', $sessionIds)
            ->selectRaw('i.qtype, count(*) total, sum(case when r.verdict = ? then 1 else 0 end) correct', [TutorResponse::VERDICT_CORRECT])
            ->groupBy('i.qtype')
            ->get()
            ->map(fn ($row) => [
                'qtype' => $row->qtype,
                'total' => (int) $row->total,
                'correct' => (int) $row->correct,
                'percent' => (int) $row->total > 0
                    ? (int) round((int) $row->correct / (int) $row->total * 100)
                    : 0,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'sessions' => count($sessionIds),
                'answers' => $total,
                'graded' => $graded,
                'correct' => $correct,
                // Доля считается по оценённым ответам. Считать по всем
                // было бы неверно: неотвеченное не равно незнанию, а при
                // недоступном движке все ответы были бы ungraded.
                'percent' => $graded > 0 ? (int) round($correct / $graded * 100) : 0,
                'by_type' => $byType,
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * Индексация материала курса.
     *
     * POST /api/v1/tutor/admin/materials/index  (tutor.manage)
     */
    public function indexMaterial(Request $request)
    {
        $this->authorizeTutor($request, 'manage');

        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
        ]);

        $course = Course::with('aircraft')->findOrFail($data['course_id']);

        $result = $this->indexer->indexCourse($course);

        return response()->json([
            'success' => true,
            'data' => [
                'material_id' => $result['material']?->id,
                'materials' => $result['materials'] ?? 0,
                'status' => $result['material']?->status,
                'chunks' => $result['chunks'],
                'characters' => $result['characters'],
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * Все материалы со статусом — экран методиста.
     *
     * GET /api/v1/tutor/admin/materials  (tutor.manage)
     */
    public function adminMaterials(Request $request)
    {
        $this->authorizeTutor($request, 'manage');

        $materials = TutorMaterial::with(['course:id,title', 'category:id,title'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (TutorMaterial $m) => [
                'id' => $m->id,
                'course_id' => $m->course_id,
                'course_title' => $m->course?->title,
                'category_id' => $m->category_id,
                'category_title' => $m->category?->title,
                'title' => $m->title,
                'status' => $m->status,
                'chunks_count' => $m->chunks_count,
                'error' => $m->error,
                'ready' => $m->isReady(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $materials,
            'error' => null,
            'meta' => ['total' => $materials->count()],
        ]);
    }

    // --- Проверки доступа --------------------------------------------

    /** Право на тренажёр с понятной ошибкой вместо 403. */
    private function authorizeTutor(Request $request, string $capability): void
    {
        $actor = $request->user();

        $permission = (string) config('tutor.permissions.'.$capability);

        if ($actor === null || ! $actor->hasPermission($permission)) {
            abort(403, 'Тренажёр недоступен: нет права '.$permission);
        }
    }

    /**
     * Материал назначен пользователю?
     *
     * Проверяется пара (курс, специальность): группа записывается на
     * курс в рамках своей специальности, поэтому запись только по курсу
     * открывала бы материалы чужих специальностей. Право видеть курс у
     * методиста есть по всем курсам, и без этой проверки тренажёр был бы
     * открыт всем, у кого есть tutor.use.
     */
    private function assertMaterialAllowed(User $actor, TutorMaterial $material): void
    {
        if (! TutorAccess::allows($actor, $material)) {
            abort(403, 'Этот материал не назначен вашей группе в рамках вашей специальности');
        }
    }

    /** Сессия принадлежит пользователю. */
    private function assertSessionAllowed(?User $actor, TutorSession $session): void
    {
        if ($actor === null || $session->user_id !== $actor->id) {
            abort(403, 'Сессия тренажёра принадлежит другому пользователю');
        }
    }

    /** Отметить результат в окне контекста сессии. */
    private function recordContext(TutorSession $session, TutorItem $item, string $verdict): void
    {
        $window = collect($session->context_window ?? []);
        $entry = $window->firstWhere('chunk_id', $item->chunk_id)
            ?? ['chunk_id' => $item->chunk_id, 'asked' => 0, 'correct' => 0];

        $entry['asked'] = (int) $entry['asked'] + 1;
        $entry['correct'] = (int) $entry['correct']
            + ($verdict === TutorResponse::VERDICT_CORRECT ? 1 : 0);

        $window->push($entry);

        $session->context_window = $window->values()->all();
        $session->save();
    }
}