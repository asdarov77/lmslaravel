<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Support\Facades\DB;
use App\Models\Group2learning;
use Illuminate\Http\Request;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;


class GroupController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //if (Gate::allows('view-group'))
        //$this->authorize('view', auth()->user());
        //   $user = auth()->user;        

        //if ($user->cant('vewAny', Group::class)) {            
        //        throw new AuthorizationException('This action is unauthorized.');
        //    }

        //{

        if (Auth::user()->role == "Администратор")
            $group = Group::orderBy('id')->get();
        else {
            $group = Group::orderBy('id')
                ->where('id', '=', Auth::user()->group_id)
                ->get();
        }
        foreach ($group as $item)
            $item->group2learnings;            
        return $group;

        //}
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Раньше groupdescription брался «как есть»: при отсутствии поля
        // в БД уходил NULL, а колонка NOT NULL → 500 с утечкой SQLSTATE.
        // Форма создания группы description не отправляет, поэтому дефект
        // был на основном пути пользователя.
        $data = $request->validate([
            'groupname' => 'required|string|max:255',
            'groupdescription' => 'nullable|string',
        ]);

        $group = new Group();
        $group->groupname = $data['groupname'];
        $group->groupdescription = $data['groupdescription'] ?? '';
        $group->save();
        // Именно json(), а не response(): middleware ApiResponseEnvelope
        // оборачивает только JsonResponse, поэтому response($group, 201)
        // возвращал группу без конверта — в отличие от всех остальных
        // эндпоинтов (например, POST /api/categories).
        return response()->json($group, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $group = Group::findOrFail($id);
        return $group;
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

        $group = Group::findOrFail($id);

        // Тот же дефект, что был в store(): обновление без groupdescription
        // писало NULL в NOT NULL-колонку → 500. Плюс name без проверки
        // затирал значение на NULL. Теперь description опционален.
        $data = $request->validate([
            'groupname' => 'sometimes|required|string|max:255',
            'groupdescription' => 'sometimes|nullable|string',
        ]);

        if (array_key_exists('groupname', $data)) {
            $group->groupname = $data['groupname'];
        }
        if (array_key_exists('groupdescription', $data)) {
            $group->groupdescription = $data['groupdescription'] ?? '';
        }

        $group->save();
        return $group;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //  $user = auth()->user();
        //  if ($user->('delete', Group::class)) {
        //      throw new AuthorizationException('This action is unauthorized.');
        //  } else {
        //      $group = Group::findOrFail($id);
        //      $group->delete();
        //      return response()->json(null, 204);
        //  }    

        //return Auth::guard('api')->user();
        // 
        //$this->authorize('view' );
        //if (Gate::allows('delete-group')) {
        $group = Group::findOrFail($id);

        // Записи группы на курсы удаляем вместе с группой.
        //
        // Раньше удаление группы оставляло строки в group2learnings
        // «сиротами»: внешнего ключа на groups там нет, поэтому группа
        // исчезала, а её учебный план оставался в таблице навсегда —
        // и всплывал в общем списке /api/learning.
        DB::table('group2learnings')->where('group_id', $group->id)->delete();

        $group->delete();
        return response()->json(null, 204);
        //} else
        //    return response()->json(null, 401);
    }
}
