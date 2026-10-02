<?php

namespace Tests\Feature\Api;

use App\Models\Group2learning;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AuthenticatesApi;
use Tests\TestCase;

/**
 * Ресурсы learning, permissions и role большей частью смонтированы как
 * пустые заглушки.
 *
 * Тесты фиксируют фактическое поведение, чтобы:
 *  - появление auth-защиты или реализация методов ломало тесты
 *    и заставляло осознанно обновить контракт;
 *  - регресс «пустой 200 вместо данных» не прошёл молча.
 *
 * ВАЖНО: /api/learning изначально вообще не имел middleware. Теперь чтение
 * требует авторизации, а запись — права users.courses, поэтому тесты
 * вызывают admin(), а не анонимные запросы.
 */
class StubbedResourcesTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    // ------------------------------------------------------- ГРУППОВОЕ ОБУЧЕНИЕ
    //
    // Контракт изменён: /api/learning больше не публичен. Чтение требует
    // авторизации, запись — права users.courses (или его legacy-алиаса
    // create-tasks). Раньше ресурс был смонтирован вообще без middleware.

    public function test_learning_index_requires_authentication()
    {
        Group2learning::factory()->count(2)->create();

        $this->getJson('/api/learning')->assertStatus(401);
    }

    public function test_learning_index_is_readable_for_authorized_user()
    {
        Group2learning::factory()->count(2)->create();
        $this->admin();

        $response = $this->getJson('/api/learning');

        $response->assertStatus(200);
        $this->assertIsArray($response->json('data'));
        $this->assertCount(2, $response->json('data'));
    }

    public function test_learning_index_is_empty_array_when_no_records()
    {
        $this->admin();

        $this->getJson('/api/learning')->assertStatus(200);
        $this->assertSame([], $this->getJson('/api/learning')->json('data'));
    }

    /**
     * Раньше здесь стояло test_learning_store_is_a_stub_and_creates_nothing:
     * тест ЗАКРЕПЛЯЛ поведение пустой заглушки store(), которая отвечала
     * 200, ничего не записывая. Клиент получал «успех», а учебный план
     * оставался пустым — молчаливая потеря данных.
     *
     * store() теперь делегирует проверенной записи AuthController@
     * group2learning, поэтому маршрут действительно создаёт запись.
     */
    public function test_learning_store_creates_a_record()
    {
        $this->admin();

        $group = \App\Models\Group::factory()->create();
        $course = \App\Models\Course::factory()->create();

        $response = $this->postJson('/api/learning', [
            'group_id'        => $group->id,
            'entries'         => [['course_id' => $course->id, 'parent_id' => null]],
            'typeOfLesson'    => 'lecture',
            'study_from'      => now()->toDateString(),
            'study_to'        => now()->addWeek()->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('group2learnings', [
            'group_id'  => $group->id,
            'course_id' => $course->id,
        ]);
    }

    /** Неполный контракт (без entries и дат) должен отклоняться, а не молча игнорироваться. */
    public function test_learning_store_validates_payload()
    {
        $this->admin();

        $this->postJson('/api/learning', [
            'group_id'     => 1,
            'typeOfLesson' => 'lecture',
        ])->assertStatus(422);

        $this->assertDatabaseCount('group2learnings', 0);
    }

    public function test_learning_store_rejects_guest()
    {
        $this->postJson('/api/learning', [
            'group_id'     => 1,
            'typeOfLesson' => 'lecture',
        ])->assertStatus(401);
    }

    /**
     * Раньше show() был заглушкой `Group2learning::find($id);` без return:
     * на несуществующий id отвечали 200 с пустым телом вместо 404, то
     * есть «запись есть, вот она» — с данными null.
     */
    public function test_learning_show_returns_404_for_missing_record()
    {
        $this->admin();

        $this->getJson('/api/learning/999999')->assertStatus(404);
    }

    public function test_learning_show_returns_the_record()
    {
        $this->admin();

        $group = \App\Models\Group::factory()->create();
        $course = \App\Models\Course::factory()->create();
        $row = \App\Models\Group2learning::factory()->create([
            'group_id' => $group->id,
            'course_id' => $course->id,
        ]);

        $this->getJson('/api/learning/'.$row->id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $row->id)
            ->assertJsonPath('data.course_id', $course->id);
    }

    /** Находка: edit() — заглушка, а маршрута /learning/{id}/edit нет → 404. */
    public function test_learning_edit_route_does_not_exist()
    {
        $this->admin();

        $this->getJson('/api/learning/1/edit')->assertStatus(404);
    }

    public function test_learning_update_changes_fields()
    {
        $this->admin();
        $learning = Group2learning::factory()->create(['typeOfLesson' => 'old']);

        $this->patchJson("/api/learning/{$learning->id}", [
            'typeOfLesson' => 'new',
        ])->assertStatus(200);

        $this->assertDatabaseHas('group2learnings', [
            'id'           => $learning->id,
            'typeOfLesson' => 'new',
            'teacher'      => $learning->teacher,
        ]);
    }

    public function test_learning_update_returns_404_for_missing()
    {
        $this->admin();

        $this->patchJson('/api/learning/999999', ['typeOfLesson' => 'x'])->assertStatus(404);
    }

    public function test_learning_destroy_removes_record()
    {
        $this->admin();
        $learning = Group2learning::factory()->create();

        $this->deleteJson("/api/learning/{$learning->id}")->assertStatus(200);
        $this->assertDatabaseMissing('group2learnings', ['id' => $learning->id]);
    }

    public function test_learning_destroy_returns_404_for_missing()
    {
        $this->admin();

        $this->deleteJson('/api/learning/999999')->assertStatus(404);
    }
    // ------------------------------------------------------------- ПРАВА
    //
    // PermissionController реализован полностью, поэтому тесты ниже
    // проверяют реальный контракт, а не «заглушку».

    public function test_permissions_index_requires_authentication()
    {
        Permission::factory()->count(2)->create();

        $this->getJson('/api/permissions')->assertStatus(401);
    }

    public function test_permissions_index_returns_array()
    {
        $this->admin();
        Permission::factory()->count(2)->create();

        $response = $this->getJson('/api/permissions');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_permissions_store_creates_record()
    {
        $this->admin();

        $this->postJson('/api/permissions', ['name' => 'Новое право', 'slug' => 'new.right'])
             ->assertStatus(201);

        $this->assertDatabaseHas('permissions', ['slug' => 'new.right']);
    }

    public function test_permissions_store_rejects_duplicate_slug()
    {
        $this->admin();
        Permission::factory()->create(['slug' => 'duplicated']);

        $this->postJson('/api/permissions', ['name' => 'Дубль', 'slug' => 'duplicated'])
             ->assertStatus(422);
    }

    public function test_permissions_show_returns_record()
    {
        $this->admin();
        $permission = Permission::factory()->create(['slug' => 'shown.right']);

        $response = $this->getJson("/api/permissions/{$permission->id}");

        $response->assertStatus(200);
        $this->assertSame('shown.right', $response->json('data.slug'));
    }

    public function test_permissions_show_returns_404_for_missing()
    {
        $this->admin();

        $this->getJson('/api/permissions/999999')->assertStatus(404);
    }

    public function test_permissions_update_changes_name()
    {
        $this->admin();
        $permission = Permission::factory()->create(['name' => 'old', 'slug' => 'updatable.right']);

        $this->putJson("/api/permissions/{$permission->id}", ['name' => 'new'])
             ->assertStatus(200);

        $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'name' => 'new']);
    }

    /** Системные права нельзя переименовать: на них завязаны middleware и алиасы. */
    public function test_permissions_update_rejects_protected_slug()
    {
        $this->admin();
        $permission = Permission::factory()->create([
            'name' => 'Исходное имя',
            'slug' => 'users.view',
        ]);

        $this->putJson("/api/permissions/{$permission->id}", ['name' => 'Взлом'])
             ->assertStatus(403);

        $this->assertDatabaseHas('permissions', [
            'id'   => $permission->id,
            'name' => 'Исходное имя',
        ]);
    }

    public function test_permissions_destroy_removes_record()
    {
        $this->admin();
        $permission = Permission::factory()->create(['slug' => 'removable.right']);

        $this->deleteJson("/api/permissions/{$permission->id}")->assertStatus(200);

        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
    }

    public function test_permissions_destroy_rejects_protected_slug()
    {
        $this->admin();
        $permission = Permission::factory()->create(['slug' => 'users.view']);

        $this->deleteJson("/api/permissions/{$permission->id}")->assertStatus(403);

        $this->assertDatabaseHas('permissions', ['id' => $permission->id]);
    }

    public function test_permissions_writes_reject_user_without_admin_rights()
    {
        $role = Role::factory()->create(['rolename' => 'Обучаемый', 'slug' => 'student']);
        $user = $this->asUser(['role' => 'Обучаемый']);
        $user->roles()->attach($role);

        $this->postJson('/api/permissions', ['name' => 'Своё право'])->assertStatus(403);
        $this->deleteJson('/api/permissions/1')->assertStatus(403);
    }

    // -------------------------------------------------------------- РОЛИ

    public function test_roles_index_requires_authentication()
    {
        Role::factory()->count(2)->create();

        $this->getJson('/api/role')->assertStatus(401);
    }

    public function test_roles_index_returns_array()
    {
        $this->admin();
        Role::factory()->count(2)->create();

        $response = $this->getJson('/api/role');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_roles_store_creates_record()
    {
        $this->admin();

        $this->postJson('/api/role', ['rolename' => 'Инструктор'])->assertStatus(201);

        $this->assertDatabaseHas('roles', ['rolename' => 'Инструктор']);
    }

    public function test_roles_store_generates_slug_from_name()
    {
        $this->admin();

        $this->postJson('/api/role', ['rolename' => 'Наставник'])->assertStatus(201);

        $this->assertDatabaseHas('roles', ['rolename' => 'Наставник', 'slug' => 'nastavnik']);
    }

    public function test_roles_store_rejects_duplicate_slug()
    {
        $this->admin();
        Role::factory()->create(['slug' => 'instructor']);

        $this->postJson('/api/role', ['rolename' => 'Дубль', 'slug' => 'instructor'])
             ->assertStatus(422);
    }

    public function test_roles_show_returns_record()
    {
        $this->admin();
        $role = Role::factory()->create(['rolename' => 'Наставник']);

        $response = $this->getJson("/api/role/{$role->id}");

        $response->assertStatus(200);
        $this->assertSame('Наставник', trim((string) $response->json('data.rolename')));
    }

    public function test_roles_show_returns_404_for_missing()
    {
        $this->admin();

        $this->getJson('/api/role/999999')->assertStatus(404);
    }

    public function test_roles_update_changes_name()
    {
        $this->admin();
        $role = Role::factory()->create(['rolename' => 'Старая']);

        $this->putJson("/api/role/{$role->id}", ['rolename' => 'Новая'])->assertStatus(200);

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'rolename' => 'Новая']);
    }

    /** Роль из config('permissions.role_matrix') — системная, её не трогают. */
    public function test_roles_update_rejects_system_role()
    {
        $this->admin();
        $role = Role::factory()->create(['rolename' => 'Инструктор']);

        $this->putJson("/api/role/{$role->id}", ['rolename' => 'Взлом'])->assertStatus(403);

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'rolename' => 'Инструктор']);
    }

    public function test_roles_destroy_removes_record()
    {
        $this->admin();
        $role = Role::factory()->create();

        $this->deleteJson("/api/role/{$role->id}")->assertStatus(200);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_roles_destroy_rejects_system_role()
    {
        $this->admin();
        $role = Role::factory()->create(['rolename' => 'Обучаемый']);

        $this->deleteJson("/api/role/{$role->id}")->assertStatus(403);

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    /** Нельзя удалить роль, пока она назначена пользователям — иначе теряются права. */
    public function test_roles_destroy_rejects_role_with_users()
    {
        $this->admin();
        $role = Role::factory()->create();
        // Пользователя создаём напрямую через фабрику: asUser() перезаписал бы
        // заголовок Authorization и запрос ушёл бы от имени обучаемого.
        $user = User::factory()->create(['role' => 'Обучаемый']);
        $user->roles()->attach($role);

        $this->deleteJson("/api/role/{$role->id}")->assertStatus(409);

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_roles_writes_reject_user_without_admin_rights()
    {
        $role = Role::factory()->create(['rolename' => 'Обучаемый', 'slug' => 'student']);
        $user = $this->asUser(['role' => 'Обучаемый']);
        $user->roles()->attach($role);

        $this->postJson('/api/role', ['rolename' => 'Своя роль'])->assertStatus(403);
        $this->deleteJson('/api/role/1')->assertStatus(403);
    }

    // ------------------------------------------- ПРАВА ВСЕГДА ПУСТЫ ПРИ ЛОГИНЕ

    /**
     * Регресс: login использовал property_exists($user, 'permissions'),
     * а для magic-relation это всегда false — permissions приходили пустыми.
     *
     * Роль НЕ Администратор: суперадмин по контракту получает весь каталог
     * прав из config/permissions.php, и проверять точечное назначение
     * на нём бессмысленно.
     */
    public function test_login_returns_user_permissions_after_fix()
    {
        $user = User::factory()->create([
            'fio'      => 'СПравами',
            'role'     => 'Обучаемый',
            'password' => bcrypt('secret123'),
        ]);
        $permission = Permission::factory()->create(['name' => 'create-tasks', 'slug' => 'create-tasks']);
        $user->permissions()->attach($permission->id);

        $json = $this->postJson('/api/login', [
            'fio'      => 'СПравами',
            'password' => 'secret123',
        ])->json();

        $this->assertNotEmpty($json['data']['permissions']);
        $slugs = collect($json['data']['permissions'])->pluck('slug')->all();
        $this->assertContains('create-tasks', $slugs);
    }

    public function test_login_returns_empty_permissions_when_user_has_none()
    {
        User::factory()->create([
            'fio'      => 'БезПрав',
            'role'     => 'Обучаемый',
            'password' => bcrypt('secret123'),
        ]);

        $json = $this->postJson('/api/login', [
            'fio'      => 'БезПрав',
            'password' => 'secret123',
        ])->json();

        $this->assertSame([], $json['data']['permissions']);
    }

    /**
     * Контракт суперадмина.
     *
     * login() отдаёт в permissions только строки таблицы permissions, но
     * гарантированно добавляет legacy-ключ manage-users — на него завязано
     * боковое меню. Полный каталог прав приходит в GET /api/v1/me, который
     * фронт вызывает как источник истины.
     */
    public function test_login_gives_superadmin_legacy_manage_users_key()
    {
        User::factory()->create([
            'fio'      => 'Главный',
            'role'     => 'Администратор',
            'password' => bcrypt('secret123'),
        ]);

        $json = $this->postJson('/api/login', [
            'fio'      => 'Главный',
            'password' => 'secret123',
        ])->json();

        $slugs = collect($json['data']['permissions'])->pluck('slug')->all();

        $this->assertContains('manage-users', $slugs, 'меню завязано на этот legacy-slug');
    }

    public function test_me_returns_full_catalog_for_superadmin()
    {
        $this->admin();

        $json = $this->getJson('/api/v1/me')->json();

        $this->assertArrayHasKey('permission_slugs', $json['data']);

        $slugs = $json['data']['permission_slugs'];
        foreach (array_keys(config('permissions.permissions', [])) as $slug) {
            $this->assertContains($slug, $slugs, "каталог не отдал право {$slug}");
        }
    }
}
