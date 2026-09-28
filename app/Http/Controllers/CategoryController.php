<?php

namespace App\Http\Controllers;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::all();

        return response()->json([
            'success' => true,
            'data' => $categories,
            'error' => null,
            'meta' => null,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required_without:name|string|max:255',
            'name' => 'required_without:title|string|max:255',
            'description' => 'nullable|string',
            'code' => 'nullable|string|max:50',
            'aircraft_id' => 'nullable|integer|exists:aircrafts,id',
        ]);

        // алиас name -> title (используется фронтендом и частью тестов)
        if (isset($validated['name']) && !isset($validated['title'])) {
            $validated['title'] = $validated['name'];
        }
        unset($validated['name']);

        $category = Category::create($validated);

        return response()->json([
            'success' => true,
            'data' => $this->withNameAlias($category),
            'error' => null,
            'meta' => null,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $category = Category::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->withNameAlias($category),
            'error' => null,
            'meta' => null,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'code' => 'nullable|string|max:50',
            'aircraft_id' => 'nullable|integer|exists:aircrafts,id',
        ]);

        $category = Category::findOrFail($id);

        // поддержка алиаса name
        if (isset($validated['name']) && !isset($validated['title'])) {
            $validated['title'] = $validated['name'];
        }
        unset($validated['name']);

        $category->fill($validated);
        $category->save();

        return response()->json([
            'success' => true,
            'data' => $this->withNameAlias($category->fresh()),
            'error' => null,
            'meta' => null,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'error' => null,
            'meta' => null,
        ], 200);
    }

    /**
     * Добавляет публичный алиас name (зеркало title) для совместимости
     * с контрактами фронтенда и тестов.
     */
    private function withNameAlias(Category $category): array
    {
        $arr = $category->toArray();
        $arr['name'] = $arr['title'] ?? null;
        return $arr;
    }
}
