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
            // Без проверки объект/строка из v-combobox уезжает в bigint → 500
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
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

        $token = $user->createToken($request->fio)->plainTextToken;

        // Находка: property_exists() для magic-relation всегда false,
        // поэтому permissions раньше всегда приходили пустым массивом.
        // Обращаемся к relation напрямую.
        $permissions = $user->permissions()->get();

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

    /**
     * Список пользователей для API v1 (resources).
     */
    public function index()
    {
        return User::with('permissions')
            ->orderBy('id')
            ->get();
    }

    /**
     * Создание пользователя для API v1.
     */
    public function store(Request $request)
    {
        $fields = $request->validate([
            'fio' => 'required|string|max:150|unique:users,fio',
            'password' => 'required|string|min:6',
            'email' => 'nullable|string|email|max:255|unique:users,email',
            'role' => 'nullable|string|max:15',
            'group_id' => 'nullable|integer|exists:groups,id',
            'phonenumber' => 'nullable|string|max:16',
            'city' => 'nullable|string|max:25',
            'country' => 'nullable|string|max:30',
            'organization' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'rank' => 'nullable|string|max:30',
            'spfere' => 'nullable|string|max:100',
            'specialization' => 'nullable|string|max:100',
        ]);

        $user = User::create(array_merge($fields, [
            'password' => bcrypt($fields['password']),
        ]));

        if ($request->filled('permission_id')) {
            $user->permissions()->sync($request->input('permission_id'));
        }

        return response()->json($user->fresh(), 201);
    }

    /**
     * Конкретный пользователь для API v1.
     */
    public function show($id)
    {
        return User::with('permissions')->findOrFail($id);
    }

    public function destroy($id)
    {
        if ($id != 1) {
            $user = User::findOrFail($id);
            $user->delete();
            return response()->json(null, 200);
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
        // Валидация. Без неё group_id-объект/строка уезжает в bigint
        // и пользователь получает 500 вместо внятной ошибки валидации.
        $request->validate([
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
        ]);

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
        if ($request->has('permission_id')) {
            $user->permissions()->sync($request->input('permission_id') ?? []);
        }

        return response()->json($user->fresh(), 200);
    }

    public function chpass(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->password = bcrypt(request('password'));
        $user->save();
        // json(), а не response(): иначе ответ уходит без конверта
        return response()->json($user->fresh(), 201);
    }

    public function chroll(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->roles()->sync($request->role_id);

        // json(), а не response(): иначе ответ уходит без конверта
        return response()->json($user->fresh(), 201);
    }
    public function chperm(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->permissions()->sync($request->permission_id);
        // json(), а не response(): иначе ответ уходит без конверта
        return response()->json($user->fresh(), 201);
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
