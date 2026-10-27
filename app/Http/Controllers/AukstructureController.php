<?php

namespace App\Http\Controllers;
use App\Http\Filters\AukstructureFilter;
use App\Http\Requests\Aukstructure\FilterRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Aukstructure;

/**
 * Модули и уроки курса (Aukstructure).
 *
 * Иерархия: course_id + parent_id=null -> модуль (тема)
 * course_id + parent_id=module_id -> урок (материал)
 *
 * type: 1=текст, 2=видео, 3=файл, 4=тест
 */
class AukstructureController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Список модулей/уроков курса.
     *
     * GET /api/aukstructure?course_id=X&parent_id=Y
     */
    public function index(FilterRequest $request)
    {
        $data = $request->validated();
        $filter = app()->make(AukstructureFilter::class, ['queryParams' => array_filter($data)]);
        $query = Aukstructure::filter($filter);

        if (!empty($data['course_id'])) {
            $query->where('course_id', $data['course_id']);
        }
        if (!empty($data['parent_id'])) {
            $query->where('parent_id', $data['parent_id']);
        }

        $items = $query->orderBy('parent_id')->orderBy('id')->get();

        return response()->json([
            'data' => $items,
            'meta' => ['total' => $items->count()],
        ]);
    }

    /**
     * Создание модуля или урока.
     *
     * POST /api/aukstructure
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'parent_id' => ['nullable', 'integer', 'exists:aukstructures,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'integer', 'min:1', 'max:4'],
            'description' => ['nullable', 'string'],
            'identifier' => ['nullable', 'string', 'max:100'],
        ]);

        $item = Aukstructure::create([
            ...$validated,
            'description' => \App\Support\HtmlSanitizer::clean($validated['description'] ?? null),
        ]);

        return response()->json([
            'data' => $item,
        ], 201);
    }

    /**
     * Показать модуль/урок.
     *
     * GET /api/aukstructure/{id}
     */
    public function show($id)
    {
        $item = Aukstructure::with(['children', 'links'])->findOrFail($id);

        return response()->json([
            'data' => $item,
        ]);
    }

    /**
     * Обновление модуля/урока.
     *
     * PUT/PATCH /api/aukstructure/{id}
     */
    public function update(Request $request, $id)
    {
        $item = Aukstructure::findOrFail($id);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['nullable', 'integer', 'min:1', 'max:4'],
            'description' => ['nullable', 'string'],
            'identifier' => ['nullable', 'string', 'max:100'],
        ]);

        $item->update(array_merge($validated, [
            'description' => \App\Support\HtmlSanitizer::clean($validated['description'] ?? null),
        ]));

        return response()->json([
            'data' => $item->fresh(),
        ]);
    }

    /**
     * Удаление модуля/урока.
     *
     * DELETE /api/aukstructure/{id}
     */
    public function destroy($id)
    {
        $item = Aukstructure::findOrFail($id);
        $item->delete();

        return response()->json(null, 204);
    }

    /**
     * Раздел 8 (остаток бэка): переупорядочивание узлов курса.
     *
     * POST /api/aukstructure/reorder { course_id, ids: [..] }
     * ids — полный список узлов курса в новом порядке: договорённость
     * «прислал всё» проще дельт и не может рассинхронизироваться.
     * Узлы, не попавшие в список, в хвост не передвигаются — их позиции
     * не трогаются (частичный список не должен перетряхивать дерево).
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $ids = $validated['ids'];
        $duplicate = count($ids) !== count(array_unique($ids));
        if ($duplicate) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => 'В списке порядка есть повторяющиеся узлы',
                'meta' => null,
            ], 422);
        }

        // Все id обязаны принадлежать курсу (иначе за узлом другого курса
        // зашита чужая позиция).
        $foreign = Aukstructure::whereIn('id', $ids)
            ->where('course_id', '<>', $validated['course_id'])
            ->count();
        if ($foreign > 0) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => 'В списке есть узлы из другого курса',
                'meta' => null,
            ], 422);
        }

        $count = Aukstructure::where('course_id', $validated['course_id'])->count();
        if (count($ids) !== $count) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => "Список порядка отображает не все узлы курса ({$count} узлов, пришло " . count($ids) . ')',
                'meta' => null,
            ], 422);
        }

        // Один UPDATE по CASE: параметр курса — positional '?' вместо named,
        // чтобы не смешивать стили плейсхолдеров в одном запросе.
        $cases = implode(' ', collect($ids)
            ->map(fn ($id, $i) => 'WHEN ' . (int) $id . ' THEN ' . ($i + 1))->all());
        $bindings = array_merge([$validated['course_id']], array_map('intval', $ids));
        DB::update(
            'UPDATE aukstructures SET position = CASE id ' . $cases . " END
             WHERE course_id = ? AND id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')',
            $bindings
        );

        return response()->json([
            'success' => true,
            'data' => ['reordered' => count($ids)],
            'error' => null,
            'meta' => null,
        ]);
    }
}
