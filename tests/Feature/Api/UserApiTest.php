<?php

namespace Tests\Feature\Api;

use App\Models\Group;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Аутентификация и пользователи.
 *
 * Покрывает login/logout, регистрацию, список/просмотр/обновление/удаление,
 * смену пароля и смену прав, включая все найденные регрессии:
 *  - group_id-объект из v-combobox → был 500, теперь 422;
 *  - все поля профиля реально сохраняются;
 *  - роль администратора фильтрует список пользователей.
 */
class UserApiTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $token = $user->createToken('t')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);
        return $user;
    }

    private function admin(): User
    {
        return $this->asUser(['role' => 'Администратор']);
    }

    // ----------------------------------------------------------------- LOGIN

    public function test_login_success_returns_token_user_and_permissions()
    {
        $user = User::factory()->create([
            'fio'      => 'Тестовый',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'fio'      => 'Тестовый',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success', 'data' => ['token', 'permissions'],
                     'data' => ['user' => ['id', 'fio']],
                     'error', 'meta',
                 ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertSame($user->id, $response->json('data.user.id'));
    }

    public function test_login_v1_alias_works()
    {
        User::factory()->create([
            'fio'      => 'Алиас',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/v1/login', ['fio' => 'Алиас', 'password' => 'secret123'])
             ->assertStatus(200);
    }

    public function test_login_with_wrong_password_returns_401()
    {
        User::factory()->create([
            'fio'      => 'Тестовый',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/login', ['fio' => 'Тестовый', 'password' => 'wrong'])
             ->assertStatus(401);
    }

    public function test_login_with_unknown_fio_returns_401()
    {
        $this->postJson('/api/login', ['fio' => 'НетТакого', 'password' => 'x'])
             ->assertStatus(401);
    }

    public function test_login_requires_fio_and_password()
    {
        $this->postJson('/api/login', [])->assertStatus(422);
    }

    public function test_login_includes_permissions_when_present()
    {
        $user = User::factory()->create([
            'fio'      => 'СПравами',
            'password' => Hash::make('secret123'),
        ]);
        $permission = Permission::factory()->create();
        $user->permissions()->attach($permission->id);

        $json = $this->postJson('/api/login', [
            'fio'      => 'СПравами',
            'password' => 'secret123',
        ])->json();

        $this->assertNotEmpty($json['data']['permissions']);
    }

    // ---------------------------------------------------------------- LOGOUT

    public function test_logout_deletes_current_token()
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->postJson('/api/v1/logout')
             ->assertStatus(200);

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_logout_requires_auth()
    {
        $this->postJson('/api/v1/logout')->assertStatus(401);
    }

    /**
     * Проверяем, что строка токена удалена из БД.
     * Проверять последующий запрос с тем же токеном нельзя: в одном
     * тестовом методе auth-guard кэширует пользователя между запросами,
     * поэтому вернётся 200 даже при удалённом токене (проверено — в проде 401).
     */
    public function test_logout_removes_token_row_from_database()
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;
        $plain = explode('|', $token)[1];

        $this->assertDatabaseHas('personal_access_tokens', ['token' => hash('sha256', $plain)]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
             ->postJson('/api/v1/logout')->assertStatus(200);

        $this->assertDatabaseMissing('personal_access_tokens', ['token' => hash('sha256', $plain)]);
        $this->assertCount(0, $user->fresh()->tokens);
    }

    // -------------------------------------------------------------- REGISTER

    public function test_register_creates_user_with_hashed_password()
    {
        $response = $this->postJson('/api/register', [
            'fio'                  => 'Новичок',
            'password'             => 'secret123',
            'password_confirmation'=> 'secret123',
        ]);

        $response->assertStatus(201);

        $user = User::where('fio', 'Новичок')->firstOrFail();
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    public function test_register_requires_fio_and_password()
    {
        $this->postJson('/api/register', [])->assertStatus(422);
    }

    public function test_register_requires_confirmed_password()
    {
        $this->postJson('/api/register', [
            'fio'      => 'Раз',
            'password'=> 'secret123',
        ])->assertStatus(422);
    }

    public function test_register_with_numeric_group_id_works()
    {
        $group = Group::factory()->create();

        $this->postJson('/api/register', [
            'fio'                  => 'СГруппой',
            'password'             => 'secret123',
            'password_confirmation'=> 'secret123',
            'group_id'             => $group->id,
        ])->assertStatus(201);

        $this->assertDatabaseHas('users', ['fio' => 'СГруппой', 'group_id' => $group->id]);
    }

    /** Регресс: объект группы из v-combobox ронял register в 500. */
    public function test_register_with_object_group_id_returns_422_not_500()
    {
        $group = Group::factory()->create();

        $this->postJson('/api/register', [
            'fio'                  => 'ОбъектГруппы',
            'password'             => 'secret123',
            'password_confirmation'=> 'secret123',
            'group_id'             => ['id' => $group->id, 'groupname' => $group->groupname],
        ])->assertStatus(422);
    }

    public function test_register_with_nonexistent_group_id_returns_422()
    {
        $this->postJson('/api/register', [
            'fio'                  => 'НетГруппы',
            'password'             => 'secret123',
            'password_confirmation'=> 'secret123',
            'group_id'             => 999999,
        ])->assertStatus(422);
    }

    // ----------------------------------------------------------- USER LIST

    public function test_user_list_requires_auth()
    {
        $this->postJson('/api/user/list')->assertStatus(401);
    }

    public function test_admin_sees_all_users()
    {
        $this->admin();
        User::factory()->count(3)->create();

        $json = $this->postJson('/api/user/list')->json();

        $this->assertCount(4, $json['data']);
    }

    public function test_non_admin_sees_only_users_of_own_group()
    {
        $own = Group::factory()->create();
        $other = Group::factory()->create();

        $this->asUser(['role' => 'Обучаемый', 'group_id' => $own->id]);
        User::factory()->create(['group_id' => $other->id]);

        $json = $this->postJson('/api/user/list')->json();

        $this->assertCount(1, $json['data']);
        $this->assertSame($own->id, $json['data'][0]['group_id']);
    }

    public function test_user_list_items_expose_group_and_permissions()
    {
        $group = Group::factory()->create();
        $permission = Permission::factory()->create();

        $user = User::factory()->create(['group_id' => $group->id]);
        $user->permissions()->attach($permission->id);

        $this->admin();
        $json = $this->postJson('/api/user/list')->json();

        $found = collect($json['data'])->firstWhere('id', $user->id);
        $this->assertNotNull($found);
        $this->assertArrayHasKey('group', $found);
        $this->assertArrayHasKey('permissions', $found);
    }

    // ------------------------------------------------------------- USER GET

    public function test_get_user_returns_envelope_with_permissions()
    {
        $this->admin();
        $user = User::factory()->create();

        $json = $this->getJson("/api/user/list/{$user->id}")->json();

        $this->assertSame($user->id, $json['data']['id']);
        $this->assertArrayHasKey('permissions', $json['data']);
    }

    public function test_get_user_returns_404_for_missing()
    {
        $this->admin();
        $this->getJson('/api/user/list/999999')->assertStatus(404);
    }

    // ----------------------------------------------------------- USER PATCH

    public function test_patch_requires_auth()
    {
        $user = User::factory()->create();
        $this->patchJson("/api/user/{$user->id}", ['fio' => 'X'])->assertStatus(401);
    }

    /** Регресс: v-combobox отдавал объект группы → 500 «invalid syntax for bigint». */
    public function test_patch_with_object_group_id_returns_422_not_500()
    {
        $this->admin();
        $user = User::factory()->create();
        $group = Group::factory()->create();

        $this->patchJson("/api/user/{$user->id}", [
            'fio'      => 'Объект',
            'group_id'=> ['id' => $group->id, 'groupname' => $group->groupname],
        ])->assertStatus(422);
    }

    public function test_patch_with_numeric_group_id_saves_group()
    {
        $this->admin();
        $user = User::factory()->create(['group_id' => null]);
        $group = Group::factory()->create();

        $this->patchJson("/api/user/{$user->id}", [
            'fio'      => 'С Группой',
            'group_id'=> $group->id,
        ])->assertStatus(200);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'group_id' => $group->id]);
    }

    public function test_patch_can_clear_group()
    {
        $this->admin();
        $user = User::factory()->create(['group_id' => Group::factory()->create()->id]);

        $this->patchJson("/api/user/{$user->id}", [
            'fio'      => 'Без группы',
            'group_id'=> null,
        ])->assertStatus(200);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'group_id' => null]);
    }

    public function test_patch_saves_all_profile_fields()
    {
        $this->admin();
        $user = User::factory()->create();

        $this->patchJson("/api/user/{$user->id}", [
            'fio'           => 'Полное Имя',
            'role'          => 'Инструктор',
            'phonenumber'   => '+79990001122',
            'city'          => 'Москва',
            'country'       => 'РФ',
            'organization'  => 'ВК',
            'position'      => 'Инженер',
            'rank'          => 'майор',
            'spfere'        => 'Оборона',
            'specialization'=> 'БПЛА',
            'group_id'      => Group::factory()->create()->id,
        ])->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id'             => $user->id,
            'fio'            => 'Полное Имя',
            'role'           => 'Инструктор',
            'phonenumber'    => '+79990001122',
            'city'           => 'Москва',
            'country'        => 'РФ',
            'organization'   => 'ВК',
            'position'       => 'Инженер',
            'rank'           => 'майор',
            'spfere'         => 'Оборона',
            'specialization' => 'БПЛА',
        ]);
    }

    public function test_patch_returns_404_for_missing_user()
    {
        $this->admin();
        $this->patchJson('/api/user/999999', ['fio' => 'X'])->assertStatus(404);
    }

    // ---------------------------------------------------------- USER DELETE

    public function test_delete_removes_user()
    {
        $this->admin();
        $user = User::factory()->create();

        $this->deleteJson("/api/user/{$user->id}")->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_delete_returns_404_for_missing_user()
    {
        $this->admin();
        $this->deleteJson('/api/user/999999')->assertStatus(404);
    }

    /** id=1 защищён: удаление суперпользователя запрещено. */
    public function test_cannot_delete_user_with_id_1()
    {
        $super = User::factory()->create();
        $super->forceFill(['id' => 1])->save();

        $this->admin();
        $this->deleteJson('/api/user/1')->assertStatus(500);

        $this->assertDatabaseHas('users', ['id' => 1]);
    }

    // ------------------------------------------------------------- PASSWORD

    public function test_change_password_updates_hash()
    {
        $this->admin();
        $user = User::factory()->create(['password' => Hash::make('oldpass1')]);

        $this->putJson("/api/user/chpass/{$user->id}", ['password' => 'newpass123'])
             ->assertStatus(201);

        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));
    }

    public function test_change_password_requires_auth()
    {
        $user = User::factory()->create();
        $this->putJson("/api/user/chpass/{$user->id}", ['password' => 'newpass123'])
             ->assertStatus(401);
    }

    // ----------------------------------------------------------- PERMISSIONS

    public function test_chperm_syncs_permissions()
    {
        $this->admin();
        $user = User::factory()->create();
        $permissions = Permission::factory()->count(2)->create();

        $ids = $permissions->pluck('id')->all();

        $this->putJson("/api/user/chperm/{$user->id}", ['permission_id' => $ids])
             ->assertStatus(201);

        $this->assertEqualsCanonicalizing(
            $ids,
            $user->fresh()->permissions->pluck('id')->all()
        );
    }
}
