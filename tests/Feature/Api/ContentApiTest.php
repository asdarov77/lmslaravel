<?php

namespace Tests\Feature\Api;

use App\Models\Aukstructure;
use App\Models\Category;
use App\Models\Course;
use App\Models\Favorite;
use App\Models\GradeBoundary;
use App\Models\Question;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Вопросы с ответами, настройки, границы оценок, избранное.
 *
 * Эти ресурсы в API смонтированы без auth:sanctum — тесты фиксируют
 * фактическое поведение, чтобы регресс защиты не прошёл молча.
 */
class ContentApiTest extends TestCase
{
    use RefreshDatabase;

    private function auth(array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $this->withHeader('Authorization', 'Bearer ' . $user->createToken('t')->plainTextToken);
        return $user;
    }

    // -------------------------------------------------------------- QUESTIONS

    public function test_questions_index_returns_array()
    {
        $auk = Aukstructure::factory()->create();
        Question::factory()->create(['aukstructure_id' => $auk->id]);

        $json = $this->getJson('/api/questions')->json();

        $this->assertIsArray($json['data']);
        $this->assertCount(1, $json['data']);
    }

    public function test_questions_index_includes_aukstructure_title()
    {
        $auk = Aukstructure::factory()->create(['title' => 'Заголовок темы']);
        Question::factory()->create(['aukstructure_id' => $auk->id]);

        $json = $this->getJson('/api/questions')->json();

        $this->assertArrayHasKey('title', $json['data'][0]);
        $this->assertSame('Заголовок темы', $json['data'][0]['title']);
    }

    public function test_questions_store_creates_question_with_answers()
    {
        $this->postJson('/api/questions', [
            'category_id'     => Category::factory()->create()->id,
            'aukstructure_id' => Aukstructure::factory()->create()->id,
            'question_text'   => 'Что это?',
            'answers'         => [
                ['answer' => 'Да',  'is_correct' => true],
                ['answer' => 'Нет', 'is_correct' => false],
            ],
        ])->assertStatus(200);

        $this->assertDatabaseHas('questions', ['question_text' => 'Что это?']);
        $this->assertSame(2, Question::first()->answers()->count());
    }

    public function test_questions_show_returns_404_for_missing()
    {
        $this->getJson('/api/questions/999999')->assertStatus(404);
    }

    public function test_questions_update_changes_text()
    {
        $question = Question::factory()->create(['question_text' => 'Старый']);

        $this->putJson("/api/questions/{$question->id}", [
            'question_text'   => 'Новый',
            'category_id'     => $question->category_id,
            'aukstructure_id' => $question->aukstructure_id,
            'answers'         => [],
        ])->assertStatus(200);

        $this->assertDatabaseHas('questions', ['id' => $question->id, 'question_text' => 'Новый']);
    }

    /**
     * Находка: update() жёстко читает category_id/aukstructure_id —
     * если их не передать, бросается ErrorException (500).
     */
    public function test_questions_update_without_required_fields_returns_500()
    {
        $question = Question::factory()->create();

        $this->putJson("/api/questions/{$question->id}", ['question_text' => 'Новый'])
             ->assertStatus(500);
    }

    public function test_questions_update_returns_404_for_missing()
    {
        $this->putJson('/api/questions/999999', ['question_text' => 'X'])
             ->assertStatus(404);
    }

    public function test_questions_destroy_removes_question_and_answers()
    {
        $question = Question::factory()->create();
        $question->answers()->create(['answer' => 'Да', 'is_correct' => true]);
        $answerId = $question->answers()->first()->id;

        $this->deleteJson("/api/questions/{$question->id}")->assertStatus(200);

        $this->assertDatabaseMissing('questions', ['id' => $question->id]);
        $this->assertDatabaseMissing('answers', ['id' => $answerId]);
    }

    public function test_questions_destroy_returns_404_for_missing()
    {
        $this->deleteJson('/api/questions/999999')->assertStatus(404);
    }

    // --------------------------------------------------------------- SETTINGS

    public function test_settings_index_returns_array()
    {
        Setting::factory()->count(2)->create();

        $json = $this->getJson('/api/settings')->json();

        $this->assertIsArray($json['data']);
        $this->assertCount(2, $json['data']);
    }

    /**
     * Находка: apiResource(settings) требует store(), но в контроллере
     * есть только index() и update() — поэтому POST /api/settings даёт 500.
     * Обновление настроек идёт через PUT -> update().
     */
    public function test_settings_store_method_is_missing_returns_500()
    {
        $setting = Setting::factory()->create(['value' => 'old']);

        $this->postJson('/api/settings', [
            ['name' => $setting->name, 'value' => 'new'],
        ])->assertStatus(500);
    }

    /**
     * Находка: update() есть, но принимает массив и роут требует {setting},
     * поэтому PUT /api/settings (без id) → 405. Фактический вызов —
     * PUT /api/settings/{id} с массивом настроек.
     */
    public function test_settings_update_requires_id_in_url()
    {
        $this->putJson('/api/settings', [['name' => 'x', 'value' => 'y']])
             ->assertStatus(405);
    }

    public function test_settings_update_changes_value()
    {
        $setting = Setting::factory()->create(['value' => 'old']);

        $this->putJson("/api/settings/{$setting->id}", [
            ['name' => $setting->name, 'value' => 'new'],
        ])->assertStatus(200);

        $this->assertDatabaseHas('settings', ['name' => $setting->name, 'value' => 'new']);
    }

    public function test_settings_update_ignores_unknown_name()
    {
        $this->putJson('/api/settings/1', [
            ['name' => 'нетакого', 'value' => 'x'],
        ])->assertStatus(200);

        $this->assertDatabaseMissing('settings', ['name' => 'нетакого']);
    }

    // --------------------------------------------------------- GRADE BOUNDARY

    public function test_grade_boundary_index_sorted_by_id()
    {
        GradeBoundary::factory()->count(3)->create();

        $json = $this->getJson('/api/grade-boundary')->json();
        $ids = collect($json['data'])->pluck('id')->all();
        $sorted = $ids;
        sort($sorted);

        $this->assertSame($sorted, $ids);
    }

    /**
     * store() обновляет запись по порядковому номеру в отсортированном списке.
     * Раньше был find($index + 1) — поиск по id, из-за чего при несовпадении
     * порядка возвращался null и прилетал 500.
     */
    public function test_grade_boundary_store_updates_by_ordinal_index()
    {
        GradeBoundary::factory()->count(2)->create();
        $second = GradeBoundary::orderBy('id')->skip(1)->first();

        $this->postJson('/api/grade-boundary', [
            'index' => 1,
            'value' => 77,
        ])->assertStatus(200);

        $this->assertDatabaseHas('grade_boundaries', ['id' => $second->id, 'boundary' => 77]);
    }

    /** Регресс: find($index+1) возвращал null → 500 «assign property on null». */
    public function test_grade_boundary_store_returns_404_for_out_of_range_index()
    {
        GradeBoundary::factory()->count(2)->create();

        $this->postJson('/api/grade-boundary', [
            'index' => 99,
            'value' => 77,
        ])->assertStatus(404);
    }

    // -------------------------------------------------------------- FAVORITES

    public function test_favorites_index_requires_auth()
    {
        $this->getJson('/api/favorites')->assertStatus(401);
    }

    public function test_favorites_add_creates_record_for_current_user()
    {
        $user = $this->auth();

        $this->postJson('/api/favorites/add', [
            'course_id' => 42,
            'title'     => 'Любимый курс',
        ])->assertStatus(200);

        $this->assertDatabaseHas('favorites', [
            'user_id'   => $user->id,
            'course_id' => 42,
        ]);
    }

    public function test_favorites_add_twice_returns_400()
    {
        $this->auth();
        $payload = ['course_id' => 42, 'title' => 'Дубль'];

        $this->postJson('/api/favorites/add', $payload)->assertStatus(200);
        $this->postJson('/api/favorites/add', $payload)->assertStatus(400);
    }

    public function test_favorites_index_returns_favorites_key()
    {
        $this->auth();
        Favorite::factory()->count(2)->create();

        $json = $this->getJson('/api/favorites')->json();

        $this->assertArrayHasKey('favorites', $json['data']);
        $this->assertCount(2, $json['data']['favorites']);
    }

    public function test_favorites_remove_deletes_record()
    {
        $user = $this->auth();
        Favorite::factory()->create([
            'user_id'   => $user->id,
            'course_id' => 42,
        ]);

        $this->deleteJson('/api/favorites/42')->assertStatus(200);

        $this->assertDatabaseMissing('favorites', [
            'user_id'   => $user->id,
            'course_id' => 42,
        ]);
    }

    public function test_favorites_remove_missing_returns_400()
    {
        $this->auth();
        $this->deleteJson('/api/favorites/999')->assertStatus(400);
    }

    /**
     * Регресс: страница вопросов шлёт пустые фильтры (?category_id=).
     * Валидация 'int' отвечала 422, хотя QuestionsController::index
     * сам делает array_filter($data) и рассчитан на их отбрасывание.
     */
    public function test_questions_index_accepts_empty_filters()
    {
        $this->auth();

        $this->getJson('/api/questions?category_id=')->assertStatus(200);
        $this->getJson('/api/questions?aukstructure_id=&category_id=')->assertStatus(200);
        $this->getJson('/api/questions')->assertStatus(200);
    }

    public function test_questions_index_still_rejects_non_numeric_filter()
    {
        $this->auth();

        $this->getJson('/api/questions?category_id=abc')->assertStatus(422);
    }

    /**
     * Регресс: нечисловой id в URL раньше доходил до БД
     * (Category::findOrFail('NaN')) и на PostgreSQL давал 500 с утечкой
     * SQLSTATE 22P02. Теперь маршруты ограничены whereNumber и отдают 404.
     */
    public function test_non_numeric_id_returns_404_not_500()
    {
        $this->auth();

        foreach (['categories', 'questions', 'course', 'groups', 'gift', 'settings'] as $resource) {
            $this->getJson("/api/{$resource}/NaN")->assertStatus(404);
        }
    }

    public function test_numeric_id_still_reaches_controller()
    {
        $this->auth();

        // Валидный числовой id обязан доходить до контроллера (404 «не найдено»
        // означает, что маршрут сматчился, а не был отсечён constraint'ом).
        $this->getJson('/api/categories/999999')->assertStatus(404);
    }
}
