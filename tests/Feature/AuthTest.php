<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_success_returns_token_and_user()
    {
        $user = User::factory()->create([
            'fio' => 'Tester',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'fio' => 'Tester',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['success','data'=>['token','user'=>['id','fio']],'error','meta']);
    }

    public function test_login_fails_with_wrong_password()
    {
        $user = User::factory()->create([
            'fio' => 'Tester2',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'fio' => 'Tester2',
            'password' => 'bad',
        ]);

        $response->assertStatus(401)
                 ->assertJson(['success'=>false]);
    }

    /** PATCH /api/user/{id} закрыт auth:sanctum — нужен реальный токен. */
    private function authHeaders(): array
    {
        $admin = User::factory()->create();
        $token = $admin->createToken('test')->plainTextToken;
        return ['Authorization' => 'Bearer ' . $token];
    }

    /**
     * Регресс: v-combobox в поле «Группа» отдавал объект {id, groupname},
     * который уезжал в bigint и ронял PATCH /api/user/{id} в 500.
     */
    public function test_update_user_rejects_object_group_id_with_422_not_500()
    {
        $user = User::factory()->create();
        $group = \App\Models\Group::first() ?? \App\Models\Group::factory()->create();

        $response = $this->patchJson("/api/user/{$user->id}", [
            'fio'     => 'Тест',
            'group_id'=> ['id' => $group->id, 'groupname' => $group->groupname],
        ], $this->authHeaders());

        // Валидация обёрнута в конверт {success,data,error,meta},
        // поэтому errors лежит внутри data, а assertJsonValidationErrors не сработает.
        $response->assertStatus(422)
                 ->assertJson(['success' => false])
                 ->assertJsonStructure(['success', 'data' => ['message', 'errors']]);
    }

    public function test_update_user_accepts_numeric_group_id_and_saves_all_fields()
    {
        $user = User::factory()->create();
        $group = \App\Models\Group::first() ?? \App\Models\Group::factory()->create();

        $response = $this->patchJson("/api/user/{$user->id}", [
            'fio'           => 'Проверка Сохранения',
            'city'          => 'Москва',
            'position'      => 'Инженер',
            'rank'          => 'майор',
            'specialization'=> 'БПЛА',
            'spfere'        => 'Оборона',
            'organization'  => 'ВК',
            'country'       => 'РФ',
            'group_id'      => $group->id,
        ], $this->authHeaders());

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id'             => $user->id,
            'fio'            => 'Проверка Сохранения',
            'city'           => 'Москва',
            'position'       => 'Инженер',
            'group_id'       => $group->id,
        ]);
    }

    /** Регресс: /api/register падал в 500 на group_id-объекте. */
    public function test_register_rejects_object_group_id_with_422_not_500()
    {
        $group = \App\Models\Group::first() ?? \App\Models\Group::factory()->create();

        $response = $this->postJson('/api/register', [
            'fio'                  => 'РегОбъект',
            'password'             => 'secret123',
            'password_confirmation'=> 'secret123',
            'group_id'             => ['id' => $group->id, 'groupname' => $group->groupname],
        ]);

        // Валидация обёрнута в конверт {success,data,error,meta},
        // поэтому errors лежит внутри data, а assertJsonValidationErrors не сработает.
        $response->assertStatus(422)
                 ->assertJson(['success' => false])
                 ->assertJsonStructure(['success', 'data' => ['message', 'errors']]);
    }

    public function test_register_accepts_numeric_group_id()
    {
        $group = \App\Models\Group::first() ?? \App\Models\Group::factory()->create();

        // Запрос от имени того, кто имеет право назначать группу.
        // Публичной саморегистрации группа недоступна (см.
        // RegistrationRoleTest) — здесь проверяется форма запроса:
        // числовой group_id принимается и сохраняется, а не роняет в 500.
        $actor = \App\Models\User::factory()->create(['role' => 'Администратор']);
        $token = $actor->createToken('t')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token);

        $response = $this->postJson('/api/register', [
            'fio'                  => 'РегЧисло',
            'password'             => 'secret123',
            'password_confirmation'=> 'secret123',
            'role'                 => 'Обучаемый',
            'group_id'             => $group->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['fio' => 'РегЧисло', 'group_id' => $group->id]);
    }
}


