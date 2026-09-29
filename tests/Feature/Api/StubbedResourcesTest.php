<?php

namespace Tests\Feature\Api;

use App\Models\Group2learning;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ресурсы learning, permissions и role смонтированы БЕЗ auth:sanctum
 * и большая часть методов — пустые заглушки.
 *
 * Тесты фиксируют фактическое поведение, чтобы:
 *  - появление auth-защиты или реализация методов ломало тесты
 *    и заставляло осознанно обновить контракт;
 *  - регресс «пустой 200 вместо данных» не прошёл молча.
 */
class StubbedResourcesTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------- ГРУППОВОЕ ОБУЧЕНИЕ

    public function test_learning_index_is_publicly_readable_without_token()
    {
        Group2learning::factory()->count(2)->create();

        $response = $this->getJson('/api/learning');

        $response->assertStatus(200);
        $this->assertIsArray($response->json('data'));
        $this->assertCount(2, $response->json('data'));
    }

    public function test_learning_index_is_empty_array_when_no_records()
    {
        $this->getJson('/api/learning')->assertStatus(200);
        $this->assertSame([], $this->getJson('/api/learning')->json('data'));
    }

    public function test_learning_store_is_a_stub_and_creates_nothing()
    {
        $response = $this->postJson('/api/learning', [
            'group_id'     => 1,
            'typeOfLesson' => 'lecture',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('group2learnings', 0);
    }

    public function test_learning_show_is_a_stub_always_200()
    {
        $this->getJson('/api/learning/999999')->assertStatus(200);
    }

    /** Находка: edit() — заглушка, а маршрута /learning/{id}/edit нет → 404. */
    public function test_learning_edit_route_does_not_exist()
    {
        $this->getJson('/api/learning/1/edit')->assertStatus(404);
    }

    public function test_learning_update_changes_fields()
    {
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
        $this->patchJson('/api/learning/999999', ['typeOfLesson' => 'x'])->assertStatus(404);
    }

    public function test_learning_destroy_removes_record()
    {
        $learning = Group2learning::factory()->create();

        $this->deleteJson("/api/learning/{$learning->id}")->assertStatus(200);
        $this->assertDatabaseMissing('group2learnings', ['id' => $learning->id]);
    }

    public function test_learning_destroy_returns_404_for_missing()
    {
        $this->deleteJson('/api/learning/999999')->assertStatus(404);
    }

    // ------------------------------------------------------------- ПРАВА

    public function test_permissions_index_is_publicly_readable_without_token()
    {
        Permission::factory()->count(2)->create();

        $response = $this->getJson('/api/permissions');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_permissions_store_is_a_stub_creates_nothing()
    {
        $this->postJson('/api/permissions', ['name' => 'create-tasks'])->assertStatus(200);
        $this->assertDatabaseCount('permissions', 0);
    }

    public function test_permissions_show_is_a_stub_always_200()
    {
        $this->getJson('/api/permissions/999999')->assertStatus(200);
    }

    public function test_permissions_update_is_a_stub_always_200()
    {
        $permission = Permission::factory()->create(['name' => 'old']);

        $this->putJson("/api/permissions/{$permission->id}", ['name' => 'new'])
             ->assertStatus(200);

        $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'name' => 'old']);
    }

    public function test_permissions_destroy_is_a_stub_keeps_record()
    {
        $permission = Permission::factory()->create();

        $this->deleteJson("/api/permissions/{$permission->id}")->assertStatus(200);
        $this->assertDatabaseHas('permissions', ['id' => $permission->id]);
    }

    // -------------------------------------------------------------- РОЛИ

    public function test_roles_index_is_publicly_readable_without_token()
    {
        Role::factory()->count(2)->create();

        $response = $this->getJson('/api/role');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_roles_store_is_a_stub_creates_nothing()
    {
        $this->postJson('/api/role', ['name' => 'Инструктор'])->assertStatus(200);
        $this->assertDatabaseCount('roles', 0);
    }

    public function test_roles_show_is_a_stub_always_200()
    {
        $this->getJson('/api/role/999999')->assertStatus(200);
    }

    public function test_roles_update_is_a_stub_keeps_record()
    {
        $role = Role::factory()->create(['rolename' => 'Старая']);

        $this->putJson("/api/role/{$role->id}", ['rolename' => 'Новая'])->assertStatus(200);

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'rolename' => 'Старая']);
    }

    public function test_roles_destroy_is_a_stub_keeps_record()
    {
        $role = Role::factory()->create();

        $this->deleteJson("/api/role/{$role->id}")->assertStatus(200);
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    // ------------------------------------------- ПРАВА ВСЕГДА ПУСТЫ ПРИ ЛОГИНЕ

    /**
     * Регресс: login использовал property_exists($user, 'permissions'),
     * а для magic-relation это всегда false — permissions приходили пустыми.
     */
    public function test_login_returns_user_permissions_after_fix()
    {
        $user = User::factory()->create([
            'fio'      => 'СПравами',
            'password' => bcrypt('secret123'),
        ]);
        $permission = Permission::factory()->create(['name' => 'create-tasks', 'slug' => 'create-tasks']);
        $user->permissions()->attach($permission->id);

        $json = $this->postJson('/api/login', [
            'fio'      => 'СПравами',
            'password' => 'secret123',
        ])->json();

        $this->assertNotEmpty($json['data']['permissions']);
        $this->assertSame('create-tasks', $json['data']['permissions'][0]['slug']);
    }

    public function test_login_returns_empty_permissions_when_user_has_none()
    {
        User::factory()->create([
            'fio'      => 'БезПрав',
            'password' => bcrypt('secret123'),
        ]);

        $json = $this->postJson('/api/login', [
            'fio'      => 'БезПрав',
            'password' => 'secret123',
        ])->json();

        $this->assertSame([], $json['data']['permissions']);
    }
}
