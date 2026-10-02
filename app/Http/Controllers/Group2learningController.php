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
    /**
     * Список записей на курсы.
     *
     * Раньше возвращались ВСЕ строки таблицы любому авторизованному:
     * маршрут висел только на auth:sanctum. То есть обучаемый мог
     * перечислить учебные планы чужих групп — кто на какой курс записан
     * и когда. Это учебные данные другого человека.
     *
     * Теперь выдача ограничена областью видимости, а не только фактом
     * входа: обучаемый видит свою группу, тот, у кого есть users.courses
     * (методист), — все группы. Так же, как с каталогом курсов.
     */
    public function index(FilterRequest $request)
    {
        $data = $request->validated();
        $filter = app()->make(Group2learningFilter::class, ['queryParams' => array_filter($data)]);
        $query = Group2learning::filter($filter);

        $user = auth('sanctum')->user();

        $maySeeAll = $user !== null
            && ($user->isSuperAdmin() || $user->hasPermission('users.courses'));

        if (! $maySeeAll) {
            $groupId = $data['group_id'] ?? $user?->group_id;

            if (! $groupId) {
                return response()->json(['data' => [], 'meta' => ['total' => 0]]);
            }

            $query->where('group_id', (int) $groupId);
        }

        return $query->get();
    }


    // public function index()

    // {
    //     $learnings=Group2learning::all();

    //     foreach($learnings as $_learning)        
    //     $_learning->course;

    //     return $_learning;
    // }


    /**
     * Создание записи через RESTful-маршрут POST /api/learning.
     *
     * Метод был пустой заглушкой и возвращал 200, НИЧЕГО не записывая.
     * Это худший вид поломки: клиент (в том числе автотесты) получал
     * «успех», а учебный план оставался пустым — запись группы молча
     * терялась.
     *
     * Валидацию и саму запись переиспользуем у AuthController@group2learning:
     * там она уже исправлялась (пустые course_id, нечисловые id, NOT NULL,
     * транзакция против частичной записи, контракт ответа), и дублировать
     * её во второй раз — значит завести два разных поведения на одну
     * операцию.
     */
    public function store(Request $request)
    {
        return app(AuthController::class)->group2learning($request);
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // Раньше здесь был `Group2learning::find($id);` без return —
        // метод всегда отдавал null, то есть 200 с пустым телом вместо
        // самой записи.
        return Group2learning::findOrFail($id);
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
        'parent_id', 'teacher', 'typeOfLesson', 'study_from', 'study_to', 'deadline',
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
