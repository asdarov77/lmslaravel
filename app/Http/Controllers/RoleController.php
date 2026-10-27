<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * CRUD ролей (/api/role).
 *
 * Роли, присутствующие в config('permissions.role_matrix'), считаются
 * системными: правка и удаление запрещены (403). При создании такой роли права
 * подставляются из матрицы через syncWithoutDetaching.
 *
 * Удаление роли, у которой есть пользователи, запрещено (409).
 */
class RoleController extends Controller
{
    /**
     * Роли, описанные в config('permissions.role_matrix'), являются
     * системными: от них зависят назначения прав и checks во frontend.
     * Переименование или удаление такой роли молча ломает RBAC,
     * поэтому возвращаем 403.
     */
    private function isSystemRole(Role|string $role): bool
    {
        $name = $role instanceof Role ? (string) $role->rolename : $role;

        return array_key_exists($name, (array) config('permissions.role_matrix', []));
    }

    public function index()
    {
        return Role::with('permissions:id,name,slug')->get();
    }

    public function store(Request $request)
    {
        $fields = $request->validate([
            'rolename' => 'required|string|max:255',
            'slug'     => 'nullable|string|max:255|unique:roles,slug',
            // Явный список прав. Необязателен: для роли из role_matrix
            // baseline подставляется автоматически.
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $fields['slug'] = $fields['slug'] ?? Str::slug($fields['rolename']);

        // permissions не входит в $fillable модели Role — права вешаются
        // только через связь permissions_roles, поэтому убираем из вставки.
        $explicitPermissionIds = $fields['permissions'] ?? null;
        unset($fields['permissions']);

        $role = Role::create($fields);

        // Baseline из матрицы для системных ролей. Без этого роль
        // «Инструктор», созданная через UI, оставалась без прав: матрица
        // объявляет ей courses.view/content.view, но связи permissions_roles
        // никто не заполнял, и инструктор получал 403 на каталоге курсов.
        $this->syncMatrixPermissions($role, $explicitPermissionIds);

        return response()->json($role->refresh(), 201);
    }

    /**
     * Назначает роли права.
     *
     * Приоритет:
     *  1. Явно переданный список permissions — используется как есть.
     *  2. Роль из config('permissions.role_matrix') — набор матрицы.
     *  3. Иначе роль остаётся без прав (обычная кастомная роль).
     *
     * Уже назначенные права не удаляются: sync только дополняет состав, чтобы
     * повторный вызов не отзывал права, выданные администратором вручную.
     */
    private function syncMatrixPermissions(Role $role, ?array $explicitIds): void
    {
        if (is_array($explicitIds)) {
            $role->permissions()->sync(array_values(array_unique($explicitIds)));
            return;
        }

        $matrix = config('permissions.role_matrix', []);

        // Матрица записана по-русски, а slug роли — по-английски,
        // поэтому сверяемся с User::ROLE_ALIASES (единая карта ролей).
        $candidates = array_filter(array_merge(
            [$role->rolename, $role->slug],
            (array) $role->name
        ), fn ($v) => is_string($v) && $v !== '');

        $matrixSlugs = $this->matchMatrixSlugs($matrix, $candidates);

        if ($matrixSlugs === null) {
            // Кастомная роль: прав матрицы у неё нет.
            return;
        }

        $ids = Permission::whereIn('slug', $matrixSlugs)->pluck('id')->all();

        if ($ids !== []) {
            // syncWithoutDetaching, а не sync: повторный вызов не должен
            // отзывать права, назначенные администратором вручную.
            $role->permissions()->syncWithoutDetaching($ids);
        }
    }

    /**
     * Возвращает набор прав матрицы для роли либо null, если роль в матрицу
     * не входит. Сравнение идёт по всем вариантам имени роли (User::ROLE_ALIASES)
     * и по самому названию из матрицы.
     *
     * @param  array<string, array<int,string>>  $matrix
     * @param  array<int,string>  $candidates
     * @return array<int,string>|null
     */
    private function matchMatrixSlugs(array $matrix, array $candidates): ?array
    {
        $aliasSets = array_map(
            fn (array $variants) => array_map('mb_strtolower', $variants),
            User::ROLE_ALIASES
        );

        foreach ($matrix as $roleName => $slugs) {
            $matrixName = mb_strtolower((string) $roleName);

            foreach ($candidates as $candidate) {
                $needle = mb_strtolower($candidate);

                if ($needle === $matrixName) {
                    return array_values((array) $slugs);
                }

                foreach ($aliasSets as $variants) {
                    if (in_array($needle, $variants, true)) {
                        return array_values((array) $slugs);
                    }
                }
            }
        }

        return null;
    }

    public function show($id)
    {
        return Role::with('permissions:id,name,slug')->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        if ($this->isSystemRole($role)) {
            abort(403, 'Системную роль нельзя изменить');
        }

        $fields = $request->validate([
            'rolename' => 'sometimes|required|string|max:255',
            'slug'     => 'sometimes|nullable|string|max:255|unique:roles,slug,' . $role->id,
        ]);

        $role->update($fields);

        return response()->json($role->refresh());
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        if ($this->isSystemRole($role)) {
            abort(403, 'Системную роль нельзя удалить');
        }

        // Роль с пользователями удалять нельзя: иначе теряются их права.
        if ($role->users()->exists()) {
            abort(409, 'Роль назначена пользователям — сначала переназначьте её');
        }

        $role->permissions()->detach();
        PermissionCatalog::flushCache();
        $role->delete();

        return response()->json(['success' => true]);
    }
}
