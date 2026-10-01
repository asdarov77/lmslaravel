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
        //
        // RBAC: нормализуем контракт ответа — права (прямые + через роли,
        // с legacy-алиасами) кладём ВНУТРЬ user.permissions, чтобы фронт
        // сохранял их одним объектом и не терял при перелогине.
        // Поле верхнего уровня 'permissions' оставлено для совместимости.
        // Супер-администратор (роль «Администратор» в поле role или связи role_user)
        // получает ВЕСЬ каталог прав + legacy-алиасы, даже если в permissions_users
        // у него пусто — иначе фронт при логине сохранит пустой список и боковое
        // меню отфильтруется до укороченного варианта.
        $slugs = collect($user->permissionSlugs());
        if ($user->isSuperAdmin()) {
            $aliasMap = \App\Support\PermissionCatalog::legacyAliases();
            $slugs = collect(array_keys(config('permissions.permissions', [])))
                ->merge($slugs)
                ->merge(collect($aliasMap)->flatten())
                ->unique();
        }

        // Права могут быть назначены пользователю или роли (permissions_roles),
        // поэтому берём ВСЕ записи из таблицы, а не только whereIn('slug').
        // Иначе несуществующий в таблице slug (например users.view до запуска
        // permissions:sync) терялся, а раньше здесь же падало исключение
        // "class not found" из-за отсутствующего use App\Models\Permission —
        // это и давало 500 на POST /api/login.
        $allPermissions = Permission::query()->get(['id', 'name', 'slug']);
        $wanted = $slugs->flip();
        $permissions = $allPermissions
            ->filter(fn (Permission $p) => $wanted->has((string) $p->slug))
            ->values()
            ->map(function (Permission $p) use ($user) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'pivot' => ['user_id' => $user->id, 'permission_id' => $p->id],
                ];
            });

        // Для супер-админа гарантируем наличие ключевых legacy-прав в ответе,
        // даже если они ещё не внесены в таблицу permissions (меню фронта
        // завязано на slug 'manage-users').
        if ($user->isSuperAdmin() && !$permissions->contains('slug', 'manage-users')) {
            $permissions->prepend([
                'id' => 0,
                'name' => 'Управление пользователями',
                'slug' => 'manage-users',
                'pivot' => ['user_id' => $user->id, 'permission_id' => 0],
            ]);
        }

        $user->setRelation('permissions', \App\Models\Permission::hydrate(
            $permissions->where('id', '>', 0)->all()
        ));

        $response = [
            'user' => $user,
            'token' => $token, //->plainTextToken

            'permissions' => $permissions,
            'roles' => $user->roles->pluck('rolename'),
        ];
        return response()->json($response, 200);
    }

    /**
     * Актуальный профиль текущего пользователя (источник истины для фронта).
     * GET /api/v1/me — фронт вызывает при старте приложения и после
     * изменения прав, чтобы синхронизировать state с БД.
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $user->loadMissing(['permissions', 'roles.permissions']);

        // RBAC: единый источник истины — полный набор прав пользователя
        // (прямые + через роли + legacy-алиасы). Супер-администратору
        // выдаём весь каталог из config/permissions.php вместе с алиасами,
        // иначе у «Администратора» без явных записей в permissions_users
        // список прав пустой и боковое меню на фронте фильтруется до нуля.
        // Таблица permissions в существующих инсталляциях содержит только
        // legacy-записи (manage-users, create-tasks, manage-course): миграции
        // каталога и unique-индексов ещё не прогонялись. Поэтому сравниваем
        // slug'и с учётом алиасов на PHP — как в login(), без whereIn по БД.
        $slugs = collect($user->permissionSlugs());
        if ($user->isSuperAdmin()) {
            $aliasMap = \App\Support\PermissionCatalog::legacyAliases();
            $slugs = collect(array_keys(config('permissions.permissions', [])))
                ->merge($slugs)
                ->merge(collect($aliasMap)->flatten())
                ->unique();
        }

        $wanted = $slugs->flip();
        $permissions = Permission::query()
            ->get(['id', 'name', 'slug'])
            ->filter(fn (Permission $p) => $wanted->has((string) $p->slug))
            ->values()
            ->map(fn (Permission $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'permissions' => $permissions,
                // Эффективный набор прав, а не только строки таблицы.
                // Именно его сравнивает фронт (Auth/hasPermission) при
                // фильтрации меню и route guards. Раньше здесь отдавались
                // лишь записи, найденные в permissions — пока каталог не
                // синхронизирован (permissions:sync), права вроде users.view
                // в ответе отсутствовали, и меню у администратора пустело.
                'permission_slugs' => $slugs->values()->all(),
                'roles' => $user->roles->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->rolename,
                    'slug' => $r->slug,
                ]),
                'is_super_admin' => $user->isSuperAdmin(),
            ],
            'error' => null,
            'meta' => null,
        ]);
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
        // Свой пароль — можно; чужой — только с правом users.update.
        if ((int) $id !== (int) $request->user()->id
            && !$request->user()->hasPermission('users.update')) {
            abort(403, 'Недостаточно прав для смены пароля другого пользователя');
        }

        $request->validate(['password' => 'required|string|min:6']);

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
        $user->permissions()->sync($request->input('permission_id', []));
        // json(), а не response(): иначе ответ уходит без конверта
        return response()->json($user->fresh()->load('permissions'), 201);
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
