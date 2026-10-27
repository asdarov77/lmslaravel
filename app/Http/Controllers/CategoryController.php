<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * CRUD справочника специальностей.
 *
 * Чтение открыто любому авторизованному (с CourseVisibility::restrictCategoriesToEnrolled),
 * запись защищена маршрутом: categories.manage или courses.manage.
 * Поддерживает алиас name <-> title, потому что фронт и старые данные
 * называют поле по-разному.
 */
class CategoryController extends Controller
{
    /**
     * Поля, принимаемые в качестве названия категории.
     * API v1 отдаёт и принимает `name`, историческое поле в БД — `title`.
     * Достаточно заполнить любое одно из них.
     *
     * @return array<string, string>
     */
    private function nameRules(bool $required = true): array
    {
        $rule = $required ? 'required_without' : 'sometimes';

        return [
            'name' => $rule . ':title|nullable|string|max:255',
            'title' => $required ? 'required_without:name|nullable|string|max:255' : 'sometimes|nullable|string|max:255',
        ];
    }

    /**
     * Сводит name/title к одному значению и убирает отсутствующие ключи,
     * чтобы не затирать существующие значения в БД.
     *
     * @return array<string, mixed>
     */
    private function normalizeName(Request $request): array
    {
        // Явный title побеждает алиас name. Раньше приоритет был обратный,
        // и это ломало редактирование: фронт отправляет round-trip модели,
        // где name — устаревший appended-алиас, который молча перетирал
        // только что изменённое пользователем название (PUT отвечал 200,
        // а в БД оставалось старое значение).
        $title = $request->has('title')
            ? $request->input('title')
            : $request->input('name');

        if ($title === null) {
            return [];
        }

        return ['title' => $title];
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = Category::orderBy('id');

        // Справочник специальностей целиком нужен методисту, который эти
        // категории и ведёт. Обучаемому показываем только те, где у его
        // группы есть назначенный курс: в LMS (Moodle/Canvas) список
        // категорий ученика — это его учебный план, а не весь справочник.
        //
        // Намерение было заложено здесь же, но осталось закомментированным,
        // поэтому любой авторизованный получал все 13 специальностей.
        \App\Support\CourseVisibility::restrictCategoriesToEnrolled($query, $request->user());

        return $query->get();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate(array_merge(
            $this->nameRules(),
            [
                'description' => 'nullable|string',
                'code' => 'nullable|string|max:50',
                'aircraft_id' => 'nullable|integer|exists:aircrafts,id',
            ]
        ));

        $category = Category::create(array_merge(
            $this->normalizeName($request),
            array_filter([
                'description' => $validated['description'] ?? null,
                'code' => $validated['code'] ?? null,
                'aircraft_id' => $validated['aircraft_id'] ?? null,
            ], static fn ($value) => $value !== null)
        ));

        return response()->json([
            'success' => true,
            'data' => $category,
            'error' => null,
            'meta' => null,
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return Category::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate(array_merge(
            $this->nameRules(false),
            [
                'description' => 'nullable|string',
                'code' => 'nullable|string|max:50',
                'aircraft_id' => 'nullable|integer|exists:aircrafts,id',
            ]
        ));

        $category->fill($this->normalizeName($request));
        $category->fill(array_filter([
            'description' => $validated['description'] ?? null,
            'code' => $validated['code'] ?? null,
            'aircraft_id' => $validated['aircraft_id'] ?? null,
        ], static fn ($value) => $value !== null));
        $category->save();

        return response()->json([
            'success' => true,
            'data' => $category,
            'error' => null,
            'meta' => null,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json(null, 200);
    }
}
