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
        $index = $request->input('index'); //
        $value = $request->input('value');
        $id=$index+1;
        // Обновление значения в базе данных
        $gradeBoundary = GradeBoundary::find($id);
        $gradeBoundary->boundary = $value;
        $gradeBoundary->save();

        return response()->json(['success' => true]);
    }
}

