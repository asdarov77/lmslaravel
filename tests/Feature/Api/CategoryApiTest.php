<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AuthenticatesApi;
use Tests\TestCase;

/**
 * Регрессии редактирования категории.
 *
 * Главный баг: UpdateCategory.vue отправлял на сервер весь объект
 * state.category, где name — это appended-алиас, уже загруженный при
 * первом GET. CategoryController::normalizeName() приоритет отдавал name,
 * поэтому только что отредактированное title молча затиралось старым
 * значением алиаса: PUT отвечал 200, но в БД оставалось старое название.
 */
class CategoryApiTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_update_with_both_title_and_name_prefers_title()
    {
        $this->admin();
        $category = Category::factory()->create(['title' => 'Старое']);

        $this->putJson("/api/categories/{$category->id}", [
            'title' => 'Новое',
            'name' => 'Старое',
        ])->assertStatus(200);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'title' => 'Новое',
        ]);
    }

    public function test_update_accepts_name_alias_for_api_v1_clients()
    {
        $this->admin();
        $category = Category::factory()->create(['title' => 'Старое']);

        $this->putJson("/api/categories/{$category->id}", ['name' => 'Через алиас'])
             ->assertStatus(200);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'title' => 'Через алиас',
        ]);
    }

    public function test_update_can_change_title_and_description_together()
    {
        $this->admin();
        $category = Category::factory()->create([
            'title' => 'Летчик',
            'description' => 'курсы для летчика',
        ]);

        $this->putJson("/api/categories/{$category->id}", [
            'title' => 'Борт-инженер',
            'description' => 'курсы для борт-инженера',
        ])->assertStatus(200);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'title' => 'Борт-инженер',
            'description' => 'курсы для борт-инженера',
        ]);
    }

    public function test_response_exposes_name_alias_matching_saved_title()
    {
        $this->admin();
        $category = Category::factory()->create(['title' => 'Летчик']);

        $response = $this->getJson("/api/categories/{$category->id}")
                         ->assertStatus(200);

        // Фронт берёт name из ответа и кладёт его в round-trip модель,
        // поэтому алиас обязан совпадать с реально сохранённым title.
        $this->assertSame('Летчик', $response->json('data.title'));
        $this->assertSame($response->json('data.title'), $response->json('data.name'));
    }
}
