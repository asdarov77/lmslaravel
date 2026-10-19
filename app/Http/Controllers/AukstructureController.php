<?php

namespace App\Http\Controllers;
use App\Http\Filters\AukstructureFilter;
use App\Http\Requests\Aukstructure\FilterRequest;
use Illuminate\Http\Request;
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

        $item = Aukstructure::create($validated);

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

        $item->update($validated);

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
}
