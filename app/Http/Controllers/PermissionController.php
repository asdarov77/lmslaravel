<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use App\Support\PermissionCatalog;

class PermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $permission = Permission::all();
        return $permission;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $fields = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:permissions,slug',
        ]);

        $permission = Permission::create($fields);
        PermissionCatalog::flushCache();

        return response()->json($permission, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return Permission::findOrFail($id);
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
        $permission = Permission::findOrFail($id);

        // Системные права нельзя переименовывать — на них завязаны
        // middleware и legacy-алиасы каталога.
        if (PermissionCatalog::isProtected($permission->slug)) {
            abort(403, 'Системное право защищено от изменения');
        }

        $fields = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|nullable|string|max:255|unique:permissions,slug,' . $permission->id,
        ]);

        $permission->update($fields);
        PermissionCatalog::flushCache();

        return response()->json($permission);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $permission = Permission::findOrFail($id);

        if (PermissionCatalog::isProtected($permission->slug)) {
            abort(403, 'Системное право защищено от удаления');
        }

        $permission->roles()->detach();
        $permission->delete();
        PermissionCatalog::flushCache();

        return response()->json(['success' => true]);
    }
}
