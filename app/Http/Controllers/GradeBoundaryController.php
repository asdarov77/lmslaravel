<?php

namespace App\Http\Controllers;

use App\Models\GradeBoundary;
use Illuminate\Http\Request;

class GradeBoundaryController extends Controller
{
    public function index()
    {
        // $gradeBoundaries = GradeBoundary::all();
        $gradeBoundaries = GradeBoundary::orderBy('id')->get();

        return response()->json($gradeBoundaries);
    }

    // public function update(Request $request, $id)
    // {
    //     $gradeBoundary = GradeBoundary::findOrFail($id);

    //     $gradeBoundary->boundary = $request->input('boundary');
    //     $gradeBoundary->grade = $request->input('grade');

    //     $gradeBoundary->save();

    //     return response()->json(['message' => 'Значение успешно обновлено'], 200);
    // }


    public function store(Request $request)
    {
        // Фронт присылает index — порядковый номер записи в отсортированном
        // списке. Раньше здесь стояло find($index + 1), то есть поиск по id:
        // при несовпадении порядка find() возвращал null и страница падала в 500
        // («Attempt to assign property "boundary" on null»).
        $index = $request->input('index');
        $value = $request->input('value');

        $gradeBoundary = GradeBoundary::orderBy('id')
            ->skip($index)
            ->take(1)
            ->first();

        if (!$gradeBoundary) {
            return response()->json(['message' => 'Запись не найдена'], 404);
        }

        $gradeBoundary->boundary = $value;
        $gradeBoundary->save();

        return response()->json(['success' => true]);
    }
}

