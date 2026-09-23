<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['role' => 'admin']);
    }

    public function test_can_get_categories_list(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        Category::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         '*' => ['id', 'name', 'code']
                     ]
                 ]);
    }

    public function test_can_create_category(): void
    {
        Sanctum::actingAs($this->adminUser);

        $categoryData = [
            'name' => 'Тестовая категория',
            'code' => 'test_cat',
            'description' => 'Описание тестовой категории'
        ];

        $response = $this->postJson('/api/v1/categories', $categoryData);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'name' => 'Тестовая категория',
                         'code' => 'test_cat'
                     ]
                 ]);
    }

    public function test_can_update_category(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $category = Category::factory()->create();

        $updateData = [
            'name' => 'Обновленная категория',
            'code' => 'updated_code',
            'description' => 'Новое описание'
        ];

        $response = $this->putJson("/api/v1/categories/{$category->id}", $updateData);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'name' => 'Обновленная категория',
                         'code' => 'updated_code'
                     ]
                 ]);
    }

    public function test_can_delete_category(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $category = Category::factory()->create();

        $response = $this->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
        
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_unauthenticated_user_cannot_access_categories(): void
    {
        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(401);
    }
}
