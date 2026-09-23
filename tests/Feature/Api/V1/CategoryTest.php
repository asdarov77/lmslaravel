<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Создаем администратора для тестов
        $this->admin = \App\Models\User::factory()->create([
            'role' => 'Администратор',
        ]);
        
        $this->token = $this->admin->createToken('test-token')->plainTextToken;
    }

    /** @test */
    public function can_get_categories_list()
    {
        Category::factory()->count(3)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
            
        // Проверяем, что данные возвращены (массив категорий)
        $categories = $response->json('data');
        $this->assertCount(3, $categories);
    }

    /** @test */
    public function can_get_single_category()
    {
        $category = Category::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $category->id);
    }

    /** @test */
    public function can_create_category()
    {
        $data = [
            'title' => 'Тестовая категория',
            'code' => 'TEST_CAT',
            'description' => 'Описание тестовой категории',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/categories', $data);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Тестовая категория')
            ->assertJsonPath('data.code', 'TEST_CAT');

        $this->assertDatabaseHas('categories', [
            'title' => 'Тестовая категория',
            'code' => 'TEST_CAT',
        ]);
    }

    /** @test */
    public function can_update_category()
    {
        $category = Category::factory()->create();

        $data = [
            'title' => 'Обновленная категория',
            'code' => 'UPDATED_CAT',
            'description' => 'Обновленное описание',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/v1/categories/{$category->id}", $data);

        // Контроллер возвращает 201 статус
        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Обновленная категория')
            ->assertJsonPath('data.code', 'UPDATED_CAT');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'title' => 'Обновленная категория',
            'code' => 'UPDATED_CAT',
        ]);
    }

    /** @test */
    public function can_delete_category()
    {
        $category = Category::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    /** @test */
    public function cannot_create_category_without_title()
    {
        $data = [
            'code' => 'NO_TITLE_CAT',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/categories', $data);

        $response->assertStatus(422);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_categories()
    {
        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(401);
    }
}
