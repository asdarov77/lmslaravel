<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use App\Models\User;

use App\Models\Group2learning;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function __construct()
    {

        $this->middleware("auth:sanctum")->except(['login', 'register']);
    }

    public function register(Request $request)
    {
        $fields = $request->validate([
            'fio' => 'required|string',
            'password' => 'required|string|confirmed',
        ]);

        $user = User::create([
            'fio' => $fields['fio'],
            'password' => bcrypt($fields['password'])
        ]);
        $user->group_id = $request->group_id;
        $user->role = $request->role;
        $user->save();
        //$token = $user->createToken($request->name)->plainTextToken;
        $response = [
            'user' => $user,
        ];
        return response()->json($response, 201);
    }

    public function login(Request $request)
    {
        $fields = $request->validate([
            'fio' => 'required|string',
            'password' => 'required|string'
        ]);

        // Check fio
        $user = User::where('fio', $fields['fio'])->first();
        if (!$user || !Hash::check($fields['password'], $user->password)) {
            return response()->json(['message' => 'неверный логин или пароль'], 401);
        }
        if (method_exists($user, 'permissions')) {
            $user->permissions;
        }

        $token = $user->createToken($request->fio)->plainTextToken;
        $permissions = property_exists($user, 'permissions') ? $user->permissions : [];

        $response = [
            'user' => $user,
            'token' => $token, //->plainTextToken

            'permissions' => $permissions
        ];
        return response()->json($response, 200);
    }

    public function logout(Request $request)
    {
        // Удаляем текущий токен (которым выполнен запрос)
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Успешный выход из системы'
        ], 200);
    }

    public function destroy($id)
    {
        if ($id != 1) {
            $user = User::findOrFail($id);
            $user->delete();
            return response()->json(null, 204);
        }
        return response()->json('невозможно удалить супер пользователя', 500);
    }

    public function getUserList()
    {
        if (Auth::user()->role == "Администратор")
            $user = User::orderBy('id')->get();
        else
            $user = User::orderBy('id')
                ->where('group_id', '=', Auth::user()->group_id)
                ->get();
        foreach ($user as $_user) {
            $_user->group;
            $_user->permissions;

        }
        return $user;
    }

    public function getUser($id)
    {

        $user = User::findOrFail($id);
        $user->permissions;

        return $user;

    }

    public function update(Request $request, $id)
    {
        // Валидация

        $user = User::findOrFail($id);
        $user->fio = request('fio');
        $user->role = request('role');
        $user->phonenumber = request('phonenumber');
        $user->city = request('city');
        $user->country = request('country');
        $user->organization = request('organization');
        $user->position = request('position');
        $user->rank = request('rank');
        $user->spfere = request('spfere');
        $user->specialization = request('specialization');
        $user->group_id = request('group_id');
        $user->save();
        $user->permissions()->sync($request->permission_id);

        return response($user, 201);
    }

    public function chpass(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->password = bcrypt(request('password'));
        $user->save();
        return response($user, 201);
    }

    public function chroll(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->roles()->sync($request->role_id);

        return response($user, 201);
    }
    public function chperm(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->permissions()->sync($request->permission_id);
        return response($user, 201);
    }

    public function group2learning(Request $request)
    {
        foreach ($request->course_id as $_course_id) {

            DB::table('group2learnings')->insert(
                [
                    'group_id' => $request->group_id,                
                    'category_id' => $request->category_id,
                    'course_id' => $_course_id,                    
                    'teacher' => $request->teacher,
                    'typeOfLesson' => $request->typeOfLesson,
                    'study_from' => $request->study_from,
                    'study_to' => $request->study_to
                ],

            );
        }
    }

}
