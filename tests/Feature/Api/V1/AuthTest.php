<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_fio(): void
    {
        // Создаем тестового пользователя
        $user = User::factory()->create([
            'fio' => 'Тестовый Пользователь',
            'password' => bcrypt('password123'),
        ]);

        // Попытка входа
        $response = $this->postJson('/api/v1/login', [
            'fio' => 'Тестовый Пользователь',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'fio',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'fio' => 'Тестовый Пользователь',
                    ],
                ],
            ]);

        // Проверяем, что токен создан
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        $user = User::factory()->create([
            'fio' => 'Тестовый Пользователь',
            'password' => bcrypt('correct_password'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'fio' => 'Тестовый Пользователь',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'неверный логин или пароль',
                ],
            ]);
    }

    public function test_user_cannot_login_with_nonexistent_fio(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'fio' => 'Несуществующий Пользователь',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'неверный логин или пароль',
                ],
            ]);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token->plainTextToken,
        ])->postJson('/api/v1/logout');

        $response->assertStatus(200)
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.message', 'Успешный выход из системы');

        // Проверяем, что токен удален
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/v1/logout');

        $response->assertStatus(401);
    }

    public function test_get_authenticated_user_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token->plainTextToken,
        ])->getJson('/api/v1/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'fio',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $user->id,
                    'fio' => $user->fio,
                ],
            ]);
    }
}
