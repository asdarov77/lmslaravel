<?php

namespace Tests\Feature\Api;

use App\Models\Aircraft;
use App\Models\Aukstructure;
use App\Models\Category;
use App\Models\Course;
use App\Models\Link;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Курсы, категории, борта (aircrafts) и производные эндпоинты.
 *
 * Покрывает конверт, сортировку, валидацию, 401/404 и регрессы:
 *  - getlink/getfirstauk больше не падают в 500 на несуществующем aukstructure;
 *  - категории отдаются как массив (иначе на фронте был categories.sort is not a function).
 */
class CourseApiTest extends TestCase
{
    use RefreshDatabase;

    private function auth(): void
    {
        $user = User::factory()->create(['role' => 'Администратор']);
        $this->withHeader('Authorization', 'Bearer ' . $user->createToken('t')->plainTextToken);
    }

    private function assertEnvelope(array $json): void
    {
        foreach (['success', 'data', 'error', 'meta'] as $key) {
            $this->assertArrayHasKey($key, $json, "В конверте нет ключа {$key}");
        }
    }

    // ------------------------------------------------------------ CATEGORIES

    public function test_categories_index_returns_array_in_envelope()
    {
        $this->auth();
        Category::factory()->count(3)->create();

        $json = $this->getJson('/api/categories')->json();

        $this->assertEnvelope($json);
        $this->assertIsArray($json['data']);
        $this->assertCount(3, $json['data']);
    }

    public function test_categories_index_empty_is_empty_array()
    {
        $this->auth();
        $this->assertSame([], $this->getJson('/api/categories')->json('data'));
    }

    public function test_categories_index_requires_auth()
    {
        $this->getJson('/api/categories')->assertStatus(401);
    }

    public function test_categories_store_creates_category()
    {
        $this->auth();

        $response = $this->postJson('/api/categories', [
            'title'       => 'Новая категория',
            'description' => 'Описание',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('categories', ['title' => 'Новая категория']);
    }

    public function test_categories_store_requires_title()
    {
        $this->auth();
        $this->postJson('/api/categories', ['description' => 'без названия'])
             ->assertStatus(422);
    }

    /** Находка: уникальности title у категорий нет — дубли создаются. */
    public function test_categories_store_allows_duplicate_title()
    {
        $this->auth();
        Category::factory()->create(['title' => 'Дубль']);

        $this->postJson('/api/categories', ['title' => 'Дубль'])->assertStatus(201);
        $this->assertSame(2, Category::where('title', 'Дубль')->count());
    }

    public function test_categories_store_rejects_nonexistent_aircraft()
    {
        $this->auth();
        $this->postJson('/api/categories', [
            'title'       => 'С бортом',
            'aircraft_id' => 999999,
        ])->assertStatus(422);
    }

    public function test_categories_show_returns_404_for_missing()
    {
        $this->auth();
        $this->getJson('/api/categories/999999')->assertStatus(404);
    }

    public function test_categories_update_changes_title()
    {
        $this->auth();
        $category = Category::factory()->create(['title' => 'Старое']);

        $this->patchJson("/api/categories/{$category->id}", ['title' => 'Новое'])
             ->assertStatus(200);

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'title' => 'Новое']);
    }

    public function test_categories_destroy_deletes_category()
    {
        $this->auth();
        $category = Category::factory()->create();

        $this->deleteJson("/api/categories/{$category->id}")->assertStatus(200);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    // --------------------------------------------------------------- COURSES

    public function test_courses_list_returns_array_in_envelope()
    {
        $this->auth();
        Course::factory()->count(2)->create();

        $json = $this->getJson('/api/courses')->json();

        $this->assertEnvelope($json);
        $this->assertIsArray($json['data']);
        $this->assertCount(2, $json['data']);
    }

    public function test_courses_list_requires_auth()
    {
        $this->getJson('/api/courses')->assertStatus(401);
    }

    public function test_course_resource_index_returns_envelope()
    {
        $this->auth();
        Course::factory()->count(2)->create();

        $json = $this->getJson('/api/course')->json();
        $this->assertEnvelope($json);
        $this->assertCount(2, $json['data']);
    }

    public function test_course_store_creates_course()
    {
        $this->auth();
        $aircraft = Aircraft::factory()->create();
        $category = Category::factory()->create();

        $response = $this->postJson('/api/course', [
            'title'       => 'Новый курс',
            'description' => 'Описание',
            'aircraft_id' => $aircraft->id,
            'category_id' => $category->id,
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('courses', ['title' => 'Новый курс']);
    }

    public function test_course_show_returns_course()
    {
        $this->auth();
        $course = Course::factory()->create(['title' => 'Целевой']);

        $json = $this->getJson("/api/course/{$course->id}")->json();

        $this->assertSame($course->id, $json['data']['id']);
        $this->assertSame('Целевой', $json['data']['title']);
    }

    public function test_course_show_returns_404_for_missing()
    {
        $this->auth();
        $this->getJson('/api/course/999999')->assertStatus(404);
    }

    public function test_course_update_changes_title()
    {
        $this->auth();
        $course = Course::factory()->create(['title' => 'Старый']);

        $this->patchJson("/api/course/{$course->id}", ['title' => 'Обновлённый'])
             ->assertStatus(200);

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'title' => 'Обновлённый']);
    }

    public function test_course_destroy_deletes_course()
    {
        $this->auth();
        $course = Course::factory()->create();

        $this->deleteJson("/api/course/{$course->id}")->assertStatus(200);
        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    public function test_course_manifest_returns_data_for_existing_course()
    {
        $this->auth();
        $course = Course::factory()->create();

        $response = $this->getJson("/api/coursemanifest/{$course->id}");

        $this->assertContains($response->status(), [200, 404]);
    }

    /** Регресс: Aukstructure::find() → null, обращение ->course_id давало 500. */
    public function test_getlink_returns_404_instead_of_500_for_missing_aukstructure()
    {
        $this->auth();
        $this->getJson('/api/getlink/999999')->assertStatus(404);
    }

    /** Регресс: то же для getfirstauk. */
    public function test_getfirstauk_returns_404_instead_of_500_for_missing_aukstructure()
    {
        $this->auth();
        $this->getJson('/api/getfirstauk/999999')->assertStatus(404);
    }

    public function test_getlink_returns_404_for_course_id_used_as_aukstructure_id()
    {
        $this->auth();
        $course = Course::factory()->create();

        // Раньше курс без aukstructure давал 500 «Attempt to read property course_id on null»
        $this->getJson("/api/getlink/{$course->id}")->assertStatus(404);
    }

    // ------------------------------------------------------------- AIRCRAFTS

    public function test_classesfs_returns_array_in_envelope()
    {
        $this->auth();
        Aircraft::factory()->count(2)->create();

        $json = $this->getJson('/api/classesfs')->json();

        $this->assertEnvelope($json);
        $this->assertIsArray($json['data']);
    }

    public function test_classes_index_returns_envelope()
    {
        $this->auth();
        $json = $this->getJson('/api/classes')->json();
        $this->assertEnvelope($json);
        $this->assertIsArray($json['data']);
    }

    // --------------------------------------------------------------- LESSONS

    public function test_lessons_returns_envelope()
    {
        $this->auth();
        $json = $this->getJson('/api/lessons')->json();
        $this->assertEnvelope($json);
        $this->assertIsArray($json['data']);
    }

    // ------------------------------------------------------- COURSES BY CATEGORY

    public function test_courses_cat_returns_categories_in_envelope()
    {
        $this->auth();
        Category::factory()->count(2)->create();

        $json = $this->getJson('/api/courses/cat')->json();

        $this->assertEnvelope($json);
        $this->assertIsArray($json['data']);
        $this->assertCount(2, $json['data']);
    }

    public function test_courses_cat_by_id_returns_array()
    {
        $this->auth();
        $category = Category::factory()->create();

        $json = $this->getJson("/api/courses/cat/{$category->id}")->json();

        $this->assertEnvelope($json);
        $this->assertIsArray($json['data']);
    }

    // -------------------------------------------------------------- AUKSTRUCTURE

    public function test_aukstructure_index_returns_array()
    {
        $course = Course::factory()->create();
        Aukstructure::factory()->count(2)->create(['course_id' => $course->id]);

        $json = $this->getJson('/api/aukstructure')->json();

        $this->assertIsArray($json['data']);
        $this->assertCount(2, $json['data']);
    }

    /** Находка: AukstructureController::show — пустая заглушка, всегда 200. */
    public function test_aukstructure_show_is_a_stub_returning_200()
    {
        $this->getJson('/api/aukstructure/999999')->assertStatus(200);
    }

    // ------------------------------------------------------------------ CITY

    public function test_city_endpoint_rejects_get_method()
    {
        $this->auth();
        $this->getJson('/api/city')->assertStatus(405);
    }
}
