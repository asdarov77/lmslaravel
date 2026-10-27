<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AuthenticatesApi;
use Tests\TestCase;

/**
 * Инварианты полноценного RBAC:
 *
 *  1. Маршруты не должны регистрироваться дважды с испорченными путями
 *     (/api/v1/v1/me, /api/api/v1/login). Так было, потому что routes/api.php
 *     монтировался и под 'api', и под 'api/v1', а внутри файла есть
 *     собственная Route::prefix('v1')-группа.
 *
 *  2. Пользователь без Role-связи (legacy-установка, только строковая
 *     колонка `role`) получает базовые права из role_matrix.
 *
 *  3. Fallback матрицы НЕ применяется, когда права назначены явно, —
 *     иначе матрица обходила бы явный отзыв права.
 */
class RbacInvariantsTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    public function test_no_routes_are_registered_with_duplicated_version_prefixes(): void
    {
        $uris = collect(app('router')->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->all();

        $malformed = array_values(array_filter($uris, function (string $uri) {
            return str_contains($uri, 'v1/v1') || str_contains($uri, 'api/api');
        }));

        $this->assertSame([], $malformed, 'Найдены маршруты с дублированным префиксом версии');
    }

    public function test_api_routes_are_not_registered_twice(): void
    {
        $seen = [];
        $duplicates = [];

        foreach (app('router')->getRoutes() as $route) {
            $key = implode('|', $route->methods()).' '.$route->uri();
            if (str_starts_with($route->uri(), 'api')) {
                if (isset($seen[$key])) {
                    $duplicates[] = $key;
                }
                $seen[$key] = true;
            }
        }

        $this->assertSame([], array_values(array_unique($duplicates)), 'API-маршрут зарегистрирован дважды');
    }

    public function test_legacy_role_string_gets_baseline_permissions_from_matrix(): void
    {
        // Инструктор без Role-связи: только строковая колонка role.
        $user = User::factory()->create(['role' => 'instructor']);

        $this->assertSame([], $user->roles()->pluck('roles.id')->all(), 'в тесте роли назначаться не должны');
        $this->assertTrue($user->hasPermission('courses.view'), 'инструктор должен видеть каталог курсов по матрице');
        $this->assertTrue($user->hasPermission('content.view'));
    }

    public function test_matrix_resolves_english_and_russian_role_names_identically(): void
    {
        $english = User::factory()->create(['role' => 'instructor']);
        $russian = User::factory()->create(['role' => 'Инструктор']);

        $this->assertSame(
            $english->permissionSlugs(),
            $russian->permissionSlugs(),
            'английское и русское имя роли должны давать одинаковые права'
        );
    }

    public function test_matrix_fallback_does_not_override_explicitly_revoked_permission(): void
    {
        // Роль назначена явно, и у неё есть только courses.manage.
        $permission = Permission::firstOrCreate(['slug' => 'courses.manage'], ['name' => 'Управление курсами']);
        $other = Permission::firstOrCreate(['slug' => 'courses.view'], ['name' => 'Просмотр каталога курсов']);

        $role = Role::create(['rolename' => 'Инструктор', 'slug' => 'instructor']);
        $role->permissions()->sync([$permission->id]);

        $user = User::factory()->create(['role' => 'instructor']);
        $user->roles()->attach($role->id);

        $user->loadMissing(['permissions', 'roles.permissions']);

        $this->assertTrue($user->hasPermission('courses.manage'), 'явно выданное право должно работать');
        $this->assertFalse(
            $user->hasPermission('courses.view'),
            'матрица не должна возвращать права, отозванные у назначенной роли'
        );
    }

    public function test_direct_permission_assignment_supersedes_matrix(): void
    {
        $permission = Permission::firstOrCreate(['slug' => 'exams.take'], ['name' => 'Прохождение экзаменов']);

        $user = User::factory()->create(['role' => 'trainee']);
        $user->permissions()->sync([$permission->id]);
        $user->loadMissing(['permissions', 'roles.permissions']);

        $this->assertTrue($user->hasPermission('exams.take'));
        // content.view есть в матрице «Обучаемый», но роль не назначена —
        // прямое назначение отключает fallback матрицы целиком.
        $this->assertFalse($user->hasPermission('content.view'));
    }

    public function test_super_admin_bypasses_matrix_entirely(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertTrue($admin->isSuperAdmin());
        $this->assertTrue($admin->hasPermission('system.maintenance'));
        $this->assertTrue($admin->hasPermission('audit.view'));
    }

    public function test_role_created_from_matrix_gets_its_baseline_permissions(): void
    {
        // Каталог прав должен существовать в БД — его наполняет permissions:sync.
        foreach (config('permissions.permissions') as $slug => $meta) {
            Permission::firstOrCreate(['slug' => $slug], ['name' => $meta['name'] ?? $slug]);
        }

        $this->admin();
        $this->admin();
        $response = $this->postJson('/api/role', ['rolename' => 'Инструктор']);

        $response->assertStatus(201);

        $role = Role::where('rolename', 'Инструктор')->firstOrFail();
        $slugs = $role->permissions()->pluck('slug')->all();

        $this->assertContains('courses.view', $slugs, 'роль из матрицы должна получить права матрицы');
        $this->assertContains('content.view', $slugs);
    }

    public function test_explicit_permissions_override_matrix_baseline(): void
    {
        foreach (config('permissions.permissions') as $slug => $meta) {
            Permission::firstOrCreate(['slug' => $slug], ['name' => $meta['name'] ?? $slug]);
        }

        $only = Permission::where('slug', 'exams.take')->value('id');

        $this->admin();

        $response = $this->postJson('/api/role', [
            'rolename' => 'Инструктор',
            'permissions' => [$only],
        ]);

        $response->assertStatus(201);

        $role = Role::where('rolename', 'Инструктор')->firstOrFail();

        $this->assertSame(['exams.take'], $role->permissions()->pluck('slug')->all());
    }

    public function test_custom_role_gets_no_matrix_permissions(): void
    {
        $this->admin();
        $this->postJson('/api/role', ['rolename' => 'Методист'])->assertStatus(201);

        $role = Role::where('rolename', 'Методист')->firstOrFail();

        $this->assertSame(0, $role->permissions()->count(), 'кастомная роль не должна получать права матрицы');
    }
}
