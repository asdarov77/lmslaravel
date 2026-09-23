<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use App\Models\User;
//use App\Models\Role;
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
//use App\Traits\HasRolesAndPermissions; // использование трейта

class AuthController extends Controller
{
    //use HasRolesAndPermissions;// использование трейта
    public function __construct()
    {
        //$this->middleware('throttle:3,1')->only('login');         
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
        //$roles = $user->roles;               
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




    // public function getUserList()
    // {

    //     //if(Gate::allow('view')){
    //     //if($this->hasPermission('manage-users'));
    //     if(Auth::user()->role = "Администратор")
    //     {
    //         $user = User::orderBy('id')->get();
    //     }
    //     else
    //     {
    //     $user = User::orderBy('id')
    //     ->where('group_id','=','Auth::user()->group_id')
    //     ->get();
    //     }
    //     foreach ($user as $_user) {
    //         //$_user->roles; 
    //         $_user->group;
    //         $_user->permissions;
    //     }
    //     return $user;

    //     //return $this->hasPermission('manage-users');
    //     //}
    //     //else
    //     //return 'нет прав';
    // }

    public function getUserList()
    {
        if (Auth::user()->role == "Администратор")
            $user = User::orderBy('id')->get();
        else
            $user = User::orderBy('id')
                ->where('group_id', '=', Auth::user()->group_id)
                ->get();
        foreach ($user as $_user) {
            //$_user->roles; 
            $_user->group;
            $_user->permissions;
            //$_user->categories;

        }
        return $user;
    }

    public function getUser($id)
    {

        $user = User::findOrFail($id);
        //$user->roles;
        $user->permissions;
        //$user->categories;
        //$user->group;
        //$password = $user->password;
        //$user->givePermissionsTo('manage-users');
        //echo($user->hasPerm('ase'));
        //$temp = $user->hasPerm('create-tasks');
        //$temp = $user->hasPerm('manage-users');

        //return $temp;
        return $user;

        // $response = [
        //     'user' => $user,            
        //     'password' => $password,
        // ];

        // return response($response, 201);
    }


    public function update(Request $request, $id)
    {
        // Валидация
        // $this->validate(request(), [
        //     'name' => 'required',
        //     'email' => 'required|email|unique:users',
        //     //'password' => 'required|min:6|confirmed'
        // ]);

        $user = User::findOrFail($id);
        $user->fio = request('fio');
        $user->role = request('role');
        //$user->name = request('name');
        //$user->email = request('email');
        //$user->password = bcrypt(request('password'));
        //$user->lastname = request('lastname');
        //$user->patronymic = request('patronymic');
        //$user->firstname = request('firstname');
        $user->phonenumber = request('phonenumber');
        $user->city = request('city');
        $user->country = request('country');
        $user->organization = request('organization');
        $user->position = request('position');
        $user->rank = request('rank');
        $user->spfere = request('spfere');
        $user->specialization = request('specialization');
        //$user->group_id = $request->group_id;     
        $user->group_id = request('group_id');
        $user->save();
        //$user->roles()->sync($request->role_id);                
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

            // $learning = array(
            //     'category_id' => $request->category_id,
            //     'group_id' => $request->group_id,
            //     'course_id' => $_course_id,
            //     'study_from' => $request->study_from,
            //     'study_to' => $request->study_to
            // );
            // $user = Group2learning::create($learning);


            DB::table('group2learnings')->insert(
                [
                    'group_id' => $request->group_id,                
                    'category_id' => $request->category_id,
                    // 'parent_id' => $request->parent_id,
                    'course_id' => $_course_id,                    
                    'teacher' => $request->teacher,
                    'typeOfLesson' => $request->typeOfLesson,
                    'study_from' => $request->study_from,
                    'study_to' => $request->study_to
                ],

            );
        }
    }

    // public function editData ($id)
    // {
    //     $user = User::find($id);

    //     $name = $user->name;
    //     $email = $user->email;
    //     //return view('useredit', [])
    //     //$id = Auth::user()->id;
    //     //$name = Auth::user()->name;
    //     //$email = Auth::user()->email;
    //     // return response() ->json([
    //     //     'status'=> 200,
    //     //     'data'=> $user,
    //     // ]);
    //     $data = [
    //         'id' => $id,
    //         'name' => $name,
    //         'email' => $email,
    //     ];
    //     return $data;
    // }

}
