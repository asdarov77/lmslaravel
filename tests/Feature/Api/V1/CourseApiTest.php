<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\Course;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseApiTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $instructorUser;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['role' => 'admin']);
        $this->instructorUser = User::factory()->create(['role' => 'instructor']);
        $this->category = Category::factory()->create();
    }

    public function test_can_get_courses_list(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        Course::factory()->count(3)->create(['category_id' => $this->category->id]);

        $response = $this->getJson('/api/v1/courses');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         '*' => ['id', 'name', 'description', 'category_id']
                     ]
                 ]);
    }

    public function test_can_create_course(): void
    {
        Sanctum::actingAs($this->adminUser);

        $courseData = [
            'name' => 'Тестовый курс',
            'description' => 'Описание тестового курса',
            'category_id' => $this->category->id,
            'status' => 'active'
        ];

        $response = $this->postJson('/api/v1/courses', $courseData);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'name' => 'Тестовый курс'
                     ]
                 ]);
    }

    public function test_can_update_course(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $course = Course::factory()->create(['category_id' => $this->category->id]);

        $updateData = [
            'name' => 'Обновленный курс',
            'description' => 'Новое описание',
            'status' => 'draft'
        ];

        $response = $this->putJson("/api/v1/courses/{$course->id}", $updateData);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'name' => 'Обновленный курс'
                     ]
                 ]);
    }

    public function test_can_delete_course(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $course = Course::factory()->create();

        $response = $this->deleteJson("/api/v1/courses/{$course->id}");

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);
        
        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    public function test_instructor_can_access_courses(): void
    {
        Sanctum::actingAs($this->instructorUser);

        $response = $this->getJson('/api/v1/courses');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_courses(): void
    {
        $response = $this->getJson('/api/v1/courses');

        $response->assertStatus(401);
    }
}
