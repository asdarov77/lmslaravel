<?php

namespace App\Http\Controllers;

use App\Models\Group;
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
        $group = new Group();
        //$group->fill($request->all());
        $group->groupname = request('groupname');
        $group->groupdescription = request('groupdescription');
        $group->save();
        return response($group, 201);
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
        $group->groupname = request('groupname');
        $group->groupdescription = request('groupdescription');

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
        $group->delete();
        return response()->json(null, 204);
        //} else
        //    return response()->json(null, 401);
    }
}
