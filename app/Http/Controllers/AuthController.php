<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use App\Models\User;

use App\Models\Group2learning;
use App\Models\Permission;
use App\Policies\UserPolicy;
use App\Support\PermissionScope;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Аутентификация и администрирование пользователей.
 *
 * Самый нагруженный контроллер проекта: логин, «кто я», выход, CRUD
 * пользователей, смена пароля, назначение ролей и прав, список тех, чьи права
 * актор вправе менять, и массовая запись групп на курсы.
 *
 * Решения, которые легко принять за ошибки:
 *
 *  - login возвращает Bearer-токен Sanctum вместе с НОРМАЛИЗОВАННЫМ набором прав
 *    и ролей (roles, role_slugs, permission_slugs). Фронт и не строит этот набор
 *    сам, и не получает рассинхрон: роль приходит из колонки users.role и из
 *    role_user одновременно.
 *  - Роль при регистрации назначается ПО ПРАВАМ АКТОРА, а не по телу запроса.
 *    POST /api/register объявлен вне auth:sanctum-конструктора, поэтому
 *    $request->user() там ВСЕГДА null, и актор берётся через auth('sanctum').
 *    Без users.create роль принудительно «Обучаемый», а group_id остаётся null:
 *    группа задаёт учебный план, подписываться на чужие материалы нельзя.
 *  - Роль пишется только через PUT /api/user/chroll/{id}. PATCH /api/user/{id}
 *    поле users.role больше не трогает — иначе роль можно было бы сменить в
 *    обход проверки PermissionScope.
 *  - chperm запрещает выдать право, которого нет у самого актора, и
 *    PermissionScope::ADMIN_ONLY_SLUGS (users.permissions, system.maintenance) —
 *    даже инструктору, который каким-то образом их получил. Управлять правами
 *    «вверх» нельзя ни по роли, ни по набору прав.
 *  - Массовая запись групп на курсы идёт в одной транзакции с insertGetId: при
 *    ошибке на середине не должен остаться курс, записанный без специальности.
 *
 * ВНИМАНИЕ: маршрут GET /api/user/{id}/edit указывает на метод editData,
 * которого в классе нет. Запрос вернёт 500, а не 404 — это известный долг.
 */
class AuthController extends Controller
{
    public function __construct()
    {

        $this->middleware("auth:sanctum")->except(['login', 'register']);
    }

    /**
     * Регистрация / создание пользователя.
     *
     * Раньше роль приходила из тела запроса без всякой проверки:
     *   $user->role = $request->role;
     * А эндпоинт публичный (`except(['login', 'register'])`), поэтому любой
     * мог отправить {"role": "Администратор"} и получить все права каталога
     * вместе с is_super_admin. Проверено на живой базе: у такого
     * пользователя оказалось 26 прав из 26.
     *
     * Теперь роль назначается по правилам:
     *  - тот, кто имеет users.create (администратор, инструктор), вправе
     *    указать роль из известного списка и группу — это его рабочий
     *    сценарий «Новый пользователь»;
     *  - все остальные (публичная саморегистрация) получают роль
     *    «Обучаемый» и остаются без группы: группа определяет учебный
     *    план, и задать её самому себе значит подписаться на материалы
     *    чужой группы;
     *  - приходит неизвестная роль → 422, а не молчаливое сохранение
     *    мусора в колонке role.
     */
    public function register(Request $request)
    {
        $fields = $request->validate([
            'fio' => 'required|string',
            'password' => 'required|string|confirmed',
            // Без проверки объект/строка из v-combobox уезжает в bigint → 500
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'role' => ['nullable', 'string'],
        ]);

        // Роль назначается по правам актора. ВАЖНО: маршрут исключён из
        // auth:sanctum ($this->middleware(...)->except(['login','register'])),
        // поэтому $request->user() здесь ВСЕГДА null — даже с корректным
        // Bearer-токеном. Актор разрешается явно через guard, иначе
        // администратор не смог бы создать пользователя с ролью и всем
        // уходить в «Обучаемый».
        $actor = auth('sanctum')->user();

        $known = array_merge(
            ...array_values(User::ROLE_ALIASES)
        );

        $requested = is_string($fields['role'] ?? null) ? trim($fields['role']) : '';

        if ($requested !== '' && ! in_array($requested, $known, true)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => '422',
                    'message' => 'Неизвестная роль. Допустимые: '.implode(', ', $known),
                ],
                'meta' => null,
            ], 422);
        }

        // Право выдавать роль есть только у тех, кто создаёт пользователей.
        $mayAssignRole = $actor !== null
            && ($actor->isSuperAdmin() || $actor->hasPermission('users.create'));

        $role = $mayAssignRole && $requested !== '' ? $requested : 'Обучаемый';

        $user = User::create([
            'fio' => $fields['fio'],
            'password' => \Illuminate\Support\Facades\Hash::make($fields['password'])
        ]);
        $user->group_id = $mayAssignRole ? ($fields['group_id'] ?? null) : null;
        $user->role = $role;
        $user->save();

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
            // Единый формат ролей — тот же, что у GET /api/v1/me.
            // Раньше здесь был только список названий (pluck('rolename')):
            // фронт получал ['Обучаемый'] без slug и сравнивал строки,
            // тогда как /me отдавал объекты {id,name,slug}. Два разных
            // формата для одной сущности — источник «роль не найдена».
            'roles' => $user->rolePayloads(),
            'role_slugs' => $user->roleSlugs(),
            'is_super_admin' => $user->isSuperAdmin(),
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
                // rolePayloads() — общий сериализатор ролей, тот же, что
                // использует login(). Он учитывает обе таблицы (колонку
                // users.role и role_user), поэтому роль, назначенная через
                // chroll, видна и здесь.
                'roles' => $user->rolePayloads(),
                'role_slugs' => $user->roleSlugs(),
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
            'password' => \Illuminate\Support\Facades\Hash::make($fields['password']),
        ]));

        // Права новому пользователю назначаются через
        // PUT /api/user/chperm/{id} (users.permissions), а не через
        // создание пользователя (users.create): иначе создатель мог бы
        // раздать права без соответствующего права.

        return response()->json($user->fresh(), 201);
    }

    /**
     * Конкретный пользователь для API v1.
     */
    public function show($id)
    {
        return User::with('permissions')->findOrFail($id);
    }

    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Жёсткий запрет, а не политика. Gate::before пропускает
        // супер-администратора через ЛЮБУЮ проверку — включая те, где
        // отказ обязателен при любой роли. Удалить себя нельзя никому:
        // это закрывает себе последний вход в систему.
        if ((int) $user->id === (int) $request->user()->id) {
            abort(403, 'Нельзя удалить собственную учётную запись');
        }

        // Раньше единственная защита была `$id != 1` — магическое число:
        // любой другой администратор удалялся обычным users.delete,
        // а отказ возвращал 500 вместо 403. Теперь область видимости
        // решает UserPolicy::delete.
        $this->authorize('delete', $user);

        $user->delete();

        return response()->json(null, 200);
    }

    public function getUserList()
    {
        $actor = Auth::user();

        // Область видимости определяет UserPolicy::scopeQuery(), а не
        // сравнение строк. Раньше здесь было
        // `Auth::user()->role == "Администратор"`, из-за чего
        // администратор, которому роль назначили через role_user,
        // получал список только своей группы.
        return UserPolicy::scopeQuery($actor)->with(['group', 'permissions'])->get();
    }

    public function getUser($id)
    {
        $user = User::findOrFail($id);

        // Раньше карточка ЛЮБОГО пользователя отдавалась тому, у кого
        // есть users.view: инструктор группы А открывал сотрудника
        // группы Б по угаданному id. Теперь — своя запись либо
        // запись в своей группе.
        $this->authorize('view', $user);

        $user->loadMissing('permissions');

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

        // Область: инструктор правит свою группу, чужого администратора —
        // нет. Раньше маршрута users.update хватало, и инструктор мог
        // переписать ФИО и телефон сотрудника чужой группы.
        $this->authorize('update', $user);

        $user->fio = request('fio');
        // users.role здесь НЕ пишется. Поле было свободным текстом
        // (v-combobox в UserItemEdit позволял ввести что угодно), и его
        // значение — один из двух источников роли наряду с role_user.
        // Значит, любой, у кого есть users.update, мог вписать
        // «Администратор» и стать суперадмином в обход chroll, который
        // специально запрещает менять собственные роли.
        // Единственный писатель роли — PUT /api/user/chroll/{id}
        // (users.permissions), а из этой формы роль выводится только
        // для чтения.
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

        // Раньше здесь синхронизировались права, если в теле был
        // permission_id. Это был обход: маршрут защищён users.update,
        // и право выдавать права (users.permissions) при этом не
        // требовалось. Теперь права меняются только через
        // PUT /api/user/chperm/{id} (users.permissions) и через
        // отдельную страницу управления правами.

        return response()->json($user->fresh(), 200);
    }

    public function chpass(Request $request, $id)
    {
        $request->validate(['password' => 'required|string|min:6']);

        $user = User::findOrFail($id);

        // Свой пароль — можно; чужой — как изменение профиля, то есть в
        // пределах своей группы. Раньше проверка жила здесь и обходила
        // область видимости: инструктор с users.update менял бы пароль
        // сотруднику чужой группы.
        $this->authorize('changePassword', $user);

        $user->password = \Illuminate\Support\Facades\Hash::make(request('password'));
        $user->save();
        // json(), а не response(): иначе ответ уходит без конверта
        return response()->json($user->fresh(), 201);
    }

    public function chroll(Request $request, $id)
    {
        // Раньше у метода не было ни авторизации, ни валидации: маршрут был
        // закомментирован, поэтому страница назначения ролей просто
        // отдавала 404, а роль менялась только через свободную строку
        // users.role в PATCH /api/user/{id} (см. update()).
        //
        // Назначение роли = эскалация прав, поэтому:
        //  1) своих ролей актор не меняет — иначе инструктор с
        //     users.permissions повысил бы себя до администратора;
        //  2) роли должны существовать, иначе sync() молча вешал бы
        //     несуществующий id в role_user;
        //  3) пустой список ролей допустим: это снятие ролей.
        $data = $request->validate([
            'role_id' => ['present', 'array'],
            'role_id.*' => ['integer', 'exists:roles,id'],
        ], [], ['role_id' => 'роли']);

        $user = User::findOrFail($id);

        // Жёсткий запрет: свои роли не меняет никто, включая
        // супер-администратора, — иначе можно выйти из системы, оставив
        // себе роль без прав. Gate::before такой запрет не проверит.
        if ((int) $user->id === (int) $request->user()->id) {
            abort(403, 'Нельзя менять собственные роли');
        }

        // Политика закрывает выход за пределы своей группы.
        $this->authorize('assignRole', $user);

        $user->roles()->sync($data['role_id']);

        $user->unsetRelation('roles')->unsetRelation('permissions');
        $user->loadMissing(['roles.permissions', 'permissions']);
        $user->forgetPermissionCache();

        return response()->json([
            'user' => $user->fresh(),
            'roles' => $user->rolePayloads(),
            'role_slugs' => $user->roleSlugs(),
        ], 200);
    }
    public function chperm(Request $request, $id)
    {
        $actor = Auth::user();
        $user = User::findOrFail($id);

        // Кому актор вообще вправе назначать права. Инструктор не
        // дотянется до администратора и до чужой группы; проверка
        // обязана быть на сервере, а не только в интерфейсе.
        if (! PermissionScope::canManageUser($actor, $user)) {
            abort(403, 'Недостаточно прав для изменения прав этого пользователя');
        }

        $validated = $request->validate([
            'permission_id' => ['present', 'array'],
            'permission_id.*' => ['integer', 'exists:permissions,id'],
        ]);

        $requested = Permission::whereIn('id', $validated['permission_id'])->get();

        // Актор не может выдать право, которого не имеет сам: иначе
        // инструктор с users.view выдал бы users.delete и обошёл
        // ограничение, которое здесь же и проверяется.
        $partition = PermissionScope::partition($actor, $requested->pluck('slug')->all());

        if ($partition['denied'] !== []) {
            abort(403, 'Нельзя назначить права, которых нет у вас: '.implode(', ', $partition['denied']));
        }

        $user->permissions()->sync($requested->pluck('id')->all());
        $user->forgetPermissionCache();

        // json(), а не response(): иначе ответ уходит без конверта
        return response()->json($user->fresh()->load('permissions'), 201);
    }

    /**
     * Пользователи, чьи права актор вправе менять.
     *
     * Для отдельной страницы управления правами. Отличается от
     * /api/user/list тем, что отдаёт ровно то, что актор способен
     * отредактировать: администратору — всех, инструктору — только
     * свою группу и без администраторов.
     */
    public function manageableUsers()
    {
        $actor = Auth::user();
        abort_unless(PermissionScope::canOpen($actor), 403, 'Недостаточно прав для управления правами');

        $query = User::query()->with('permissions:id,name,slug')->orderBy('id');

        if (! $actor->isSuperAdmin()) {
            // Кто угодно, кроме администраторов, и только своя группа.
            $query->whereNotIn('role', User::ROLE_ALIASES['admin'])
                ->where('group_id', $actor->group_id);
        }

        return $query->get()->map(fn (User $user) => [
            'id' => $user->id,
            'fio' => $user->fio,
            'role' => $user->role,
            'group_id' => $user->group_id,
            'is_admin' => $user->isAdmin(),
            'permissions' => $user->permissions->map->only(['id', 'slug', 'name'])->values(),
        ]);
    }

public function group2learning(Request $request)
    {
        // Регресс: валидации не было вообще. Пустой course_id приводил к
        // «foreach() argument must be of type array, null given» (500),
        // нечисловой id — к нарушению внешнего ключа (500), а незаполненные
        // category/teacher/typeOfLesson — к нарушению NOT NULL (500).
        // Пользователю это выглядело как «Сохранить ничего не делает».
        //
        // Запись идёт по «курс + модуль»: сам курс лежит в course_id,
        // конкретный модуль (aukstructure) — в parent_id. Раньше туда
        // клался id узла дерева, который с курсом ничего общего не имеет,
        // и учебный план потом открывал несуществующий курс.
        $validated = $request->validate([
            'group_id' => ['required', 'integer', 'exists:groups,id'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.course_id' => ['required', 'integer', 'exists:courses,id'],
            'entries.*.parent_id' => ['nullable', 'integer', 'exists:aukstructures,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'teacher' => ['nullable', 'string', 'max:255'],
            'typeOfLesson' => ['nullable', 'string', 'max:255'],
            'study_from' => ['required', 'date'],
            'study_to' => ['required', 'date', 'after_or_equal:study_from'],
            // Дедлайн не обязателен: у части записей его нет, и это
            // нормально. Но если задан — он не может быть раньше начала
            // периода, иначе срок сдачи оказывается позади.
            'deadline' => ['nullable', 'date', 'after_or_equal:study_from'],
        ]);

        $created = [];

        // Транзакция: раньше курсы могли записаться частично — несколько
        // успешных insert и одна ошибка на последнем оставляли группу
        // записанной на половину выбранных курсов.
        DB::transaction(function () use ($validated, &$created) {
            foreach ($validated['entries'] as $entry) {
                $created[] = DB::table('group2learnings')->insertGetId([
                    'group_id' => $validated['group_id'],
                    'course_id' => $entry['course_id'],
                    'parent_id' => $entry['parent_id'] ?? null,
                    'category_id' => $validated['category_id'] ?? null,
                    'teacher' => $validated['teacher'] ?? null,
                    'typeOfLesson' => $validated['typeOfLesson'] ?? null,
                    'study_from' => $validated['study_from'],
                    'study_to' => $validated['study_to'],
                    // Без ключа insert упал бы на NOT NULL у колонки
                    // без default; с явным null дедлайн остаётся пустым.
                    'deadline' => $validated['deadline'] ?? null,
                ]);
            }
        });

        // Отдаём созданные строки: раньше метод возвращал null, и вызывающий
        // код не мог ни показать результат, ни обновить список записей.
        return response()->json($created, 201);
    }

}
