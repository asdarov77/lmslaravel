<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Category;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FullApiTest extends TestCase
{
    use RefreshDatabase;

    protected ?string $token = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаем администратора для тестов
        $this->admin = User::factory()->create([
            'fio' => 'Тестовый Админ',
            'password' => bcrypt('123'),
            'role' => 'Администратор',
            'group_id' => null,
        ]);

        // Получаем токен
        $response = $this->postJson('/api/v1/login', [
            'fio' => 'Тестовый Админ',
            'password' => '123',
        ]);

        if ($response->status() === 200) {
            $responseData = $response->json();
            // Токен находится в responseData['data']['token']
            $this->token = $responseData['data']['token'] ?? null;
        }
    }

    /** @test */
    public function test_authentication_flow()
    {
        // Login - используем тот же логин что и в setUp
        $response = $this->postJson('/api/v1/login', [
            'fio' => 'Тестовый Админ',
            'password' => '123',
        ]);

        $response->assertStatus(200);
        
        // Проверяем структуру ответа (используется обертка {success, data, ...})
        $responseData = $response->json();
        $this->assertEquals(true, $responseData['success'], 'Login should return success=true');
        $this->assertArrayHasKey('data', $responseData, 'Response should have data wrapper');
        $this->assertArrayHasKey('token', $responseData['data'], 'Data should contain token');
        
        $token = $responseData['data']['token'];

        // Get User
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                         ->getJson('/api/v1/user');

        $response->assertStatus(200);
        $userData = $response->json();
        $this->assertEquals('Тестовый Админ', $userData['data']['fio'] ?? $userData['fio']);

        // Logout
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                         ->postJson('/api/v1/logout');

        $response->assertStatus(200);
    }

    /** @test */
    public function test_categories_crud()
    {
        $headers = ['Authorization' => 'Bearer ' . $this->token];

        // 1. Create Category
        $categoryData = [
            'name' => 'Тестовая Категория',
            'code' => 'TEST_CAT_01',
            'description' => 'Описание для теста',
        ];

        $response = $this->withHeaders($headers)
                         ->postJson('/api/v1/categories', $categoryData);

        $response->assertStatus(201)
                 ->assertJson([
                     'name' => 'Тестовая Категория',
                     'code' => 'TEST_CAT_01',
                 ]);

        $categoryId = $response->json('id');

        // 2. Read Category
        $response = $this->withHeaders($headers)
                         ->getJson("/api/v1/categories/{$categoryId}");

        $response->assertStatus(200)
                 ->assertJson(['id' => $categoryId]);

        // 3. Update Category
        $updateData = [
            'name' => 'Обновленная Категория',
            'code' => 'TEST_CAT_02',
        ];

        $response = $this->withHeaders($headers)
                         ->putJson("/api/v1/categories/{$categoryId}", $updateData);

        $response->assertStatus(200)
                 ->assertJson([
                     'name' => 'Обновленная Категория',
                     'code' => 'TEST_CAT_02',
                 ]);

        // 4. Delete Category
        $response = $this->withHeaders($headers)
                         ->deleteJson("/api/v1/categories/{$categoryId}");

        $response->assertStatus(200);

        // Verify deletion
        $this->assertDatabaseMissing('categories', ['id' => $categoryId]);
    }

    /** @test */
    public function test_users_crud()
    {
        $headers = ['Authorization' => 'Bearer ' . $this->token];

        // 1. Create User
        $userData = [
            'fio' => 'Новый Сотрудник',
            'password' => 'password123',
            'role' => 'Инструктор',
            'group_id' => null,
        ];

        $response = $this->withHeaders($headers)
                         ->postJson('/api/v1/users', $userData);

        $response->assertStatus(201)
                 ->assertJson(['fio' => 'Новый Сотрудник']);

        $userId = $response->json('id');

        // 2. Read User
        $response = $this->withHeaders($headers)
                         ->getJson("/api/v1/users/{$userId}");

        $response->assertStatus(200)
                 ->assertJson(['id' => $userId]);

        // 3. Update User
        $updateData = [
            'fio' => 'Обновленный Сотрудник',
            'role' => 'Обучаемый',
        ];

        $response = $this->withHeaders($headers)
                         ->putJson("/api/v1/users/{$userId}", $updateData);

        $response->assertStatus(200)
                 ->assertJson(['fio' => 'Обновленный Сотрудник']);

        // 4. Delete User
        $response = $this->withHeaders($headers)
                         ->deleteJson("/api/v1/users/{$userId}");

        $response->assertStatus(200);
        
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    /** @test */
    public function test_courses_crud()
    {
        $headers = ['Authorization' => 'Bearer ' . $this->token];

        // Создадим категорию для курса
        $category = Category::factory()->create();

        // 1. Create Course
        $courseData = [
            'title' => 'Тестовый Курс',
            'category_id' => $category->id,
            'description' => 'Полное описание курса',
            'duration' => 40,
        ];

        $response = $this->withHeaders($headers)
                         ->postJson('/api/v1/courses', $courseData);

        $response->assertStatus(201)
                 ->assertJson(['title' => 'Тестовый Курс']);

        $courseId = $response->json('id');

        // 2. Read Course
        $response = $this->withHeaders($headers)
                         ->getJson("/api/v1/courses/{$courseId}");

        $response->assertStatus(200)
                 ->assertJson(['id' => $courseId]);

        // 3. Update Course
        $updateData = [
            'title' => 'Обновленный Курс',
            'duration' => 60,
        ];

        $response = $this->withHeaders($headers)
                         ->putJson("/api/v1/courses/{$courseId}", $updateData);

        $response->assertStatus(200)
                 ->assertJson(['title' => 'Обновленный Курс', 'duration' => 60]);

        // 4. Delete Course
        $response = $this->withHeaders($headers)
                         ->deleteJson("/api/v1/courses/{$courseId}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('courses', ['id' => $courseId]);
    }

    /** @test */
    public function test_unauthorized_access()
    {
        // Попытка доступа без токена
        $response = $this->getJson('/api/v1/users');
        $response->assertStatus(401);

        // Попытка создания без токена
        $response = $this->postJson('/api/v1/categories', ['name' => 'Hack']);
        $response->assertStatus(401);
    }
}
