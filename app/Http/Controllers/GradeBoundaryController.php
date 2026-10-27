<?php

namespace App\Http\Controllers;

use App\Models\GradeBoundary;
use Illuminate\Http\Request;

/**
 * Границы оценочных шкал (/api/grade-boundary).
 *
 * ВНИМАНИЕ: store меняет boundary записи, найденной по ПОРЯДКОВОЙ ПОЗИЦИИ
 * (skip/take), а не по id. Это не опечатка, а сознательное поведение под
 * порядок шкалы, но правка по id здесь невозможна.
 *
 * Методов show/update/destroy здесь нет — и маршрутов на них тоже нет:
 * apiResource их генерировал, и любой такой запрос падал с 500 вместо
 * 404. Маршруты убраны (см. routes/api.php).
 */
class GradeBoundaryController extends Controller
{
    /**
     * Текущие границы шкал.
     *
     * Право — settings.manage, как и у всех соседних маршрутов блока.
     * Раньше index висел только на auth:sanctum, то есть шкалы читаЛ
     * любой вошедший, включая обучаемого, — при том что сама страница
     * «Шкалы оценок» закрыта от него же правом settings.manage.
     */
    public function index()
    {
        return response()->json(GradeBoundary::orderBy('id')->get());
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

