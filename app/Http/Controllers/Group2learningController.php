<?php

namespace App\Http\Controllers;

use App\Http\Filters\Group2learningFilter;
use App\Http\Requests\Group2learning\FilterRequest;
use Illuminate\Http\Request;
use App\Models\Group2learning;




class Group2learningController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(FilterRequest $request)

    {    
            $data = $request->validated();
            $filter = app()->make(Group2learningFilter::class, ['queryParams' => array_filter($data)]);
            $learnings = Group2learning::filter($filter)->get();
            return $learnings;         
    }


    // public function index()

    // {
    //     $learnings=Group2learning::all();

    //     foreach($learnings as $_learning)        
    //     $_learning->course;

    //     return $_learning;
    // }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $learnings = Group2learning::find($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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
    $group2learn = Group2learning::findOrFail($id);

    // Находка: teacher/course_id/group_id/category_id — NOT NULL в БД.
    // Если их не передать, input() вернёт null и save() упадёт в 500,
    // затирая существующие значения. Поэтому обновляем только переданные поля.
    $fields = $request->only([
        'course_id', 'group_id', 'category_id',
        'parent_id', 'teacher', 'typeOfLesson', 'study_from', 'study_to',
    ]);
    foreach ($fields as $key => $value) {
        $group2learn->{$key} = $value;
    }

    // Сохраняем изменения в базу данных
    $group2learn->save();

    // Возвращаем обновленный экземпляр модели
    return response()->json([
        'data' => $group2learn,
        'message' => 'Ресурс успешно обновлен'
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
        
            // Находим экземпляр модели по его id
    $group2learn = Group2learning::findOrFail($id);

    // Удаляем найденный экземпляр из базы данных
    $group2learn->delete();

    // Возвращаем ответ об успешном удалении ресурса
    return response()->json([
        'message' => 'Ресурс успешно удален'
    ], 200);
    }
}
