<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Group;
use App\Models\Group2learning;
use App\Models\Question;
use App\Models\Role;
use App\Models\TutorChunk;
use App\Models\TutorItem;
use App\Models\TutorMaterial;
use App\Models\TutorResponse;
use App\Models\TutorSession;
use App\Models\User;
use App\Support\Tutor\TutorClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Границы тренажёра.
 *
 * Проверяется не «модель что-то ответила», а то, что тренажёр обязан
 * НЕ делать и обязан не отдавать:
 *
 *  - не видеть чужие материалы (права tutor.use есть у всех);
 *  - не открывать чужую сессию;
 *  - не отдавать эталон и цитату без явного запроса;
 *  - не создавать exam_attempts — граница «тренажёр не влияет на
 *    аттестацию» обязана быть обеспечена кодом, а не договорённостью.
 *
 * Ответы движка подменены: тест обязан проверять логику приложения, а
 * не наличие локальной нейросети.
 */
class TutorApiTest extends TestCase
{
    use RefreshDatabase;

    /** Клиент с заранее заданными ответами вместо Ollama. */
    protected function setUp(): void
    {
        parent::setUp();

        config(['tutor.enabled' => true]);

        $this->app->instance(TutorClient::class, new FakeTutorClient());
    }

    /**
     * Группа, курс, специальность и назначение — фикстура «свой курс».
     *
     * Специальность обязательна: группа записывается на курс В РАМКАХ
     * своей специальности, и материал без category_id ей не принадлежит.
     * Раньше фикстура specialность не задавала, и после ужесточения
     * проверки видна не была ничем — тесты проверяли бы пустоту.
     */
    private function enrolled(User $user): Course
    {
        $group = Group::factory()->create();
        $course = Course::factory()->create();
        $category = Category::factory()->create();

        $course->categories()->sync([$category->id]);

        $user->forceFill(['group_id' => $group->id])->save();

        $row = Group2learning::firstOrNew([
            'group_id' => $group->id,
            'course_id' => $course->id,
            'category_id' => $category->id,
        ]);
        $row->group_id = $group->id;
        $row->course_id = $course->id;
        $row->category_id = $category->id;
        $row->typeOfLesson = 'Лекция';
        $row->study_from = now()->subDay()->toDateString();
        $row->study_to = now()->addDays(14)->toDateString();
        $row->save();

        return $course;
    }

    private function materialFor(Course $course): TutorMaterial
    {
        $material = TutorMaterial::factory()->create([
            'course_id' => $course->id,
            'category_id' => $course->categories()->first()?->id,
            'status' => TutorMaterial::STATUS_INDEXED,
            'chunks_count' => 1,
        ]);

        TutorChunk::factory()->create(['material_id' => $material->id]);

        return $material;
    }

    private function trainee(): User
    {
        $user = User::factory()->create(['role' => 'Обучаемый']);
        $user->givePermissionsTo('tutor.use');
        $user->forgetPermissionCache();

        return $user->fresh();
    }

    // --- Права и границы видимости -----------------------------------

    /**
     * Пользователь без Role-связи и без прямых прав.
     *
     * Именно так выглядит «нет права»: роль пустая, поэтому fallback на
     * role_matrix из config НЕ срабатывает. Иначе тест проверял бы не
     * RBAC, а подстановку прав из конфига — любой «Обучаемый» получил бы
     * tutor.use из матрицы, и проверка была бы бессмысленной.
     */
    private function withoutRights(): User
    {
        $role = Role::firstOrCreate(['rolename' => 'Обучаемый (без прав)'], ['slug' => 'trainee-empty']);

        $user = User::factory()->create(['role' => 'Обучаемый']);
        $user->roles()->sync([$role->id]);
        $user->forgetPermissionCache();

        return $user->fresh();
    }

    public function test_trainee_without_right_gets_403(): void
    {
        $user = $this->withoutRights();

        // Права нет: пункт меню скрыт, но прямой запрос всё равно должен
        // быть закрыт.
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/tutor/materials')
            ->assertForbidden();
    }

    public function test_anonymous_is_rejected(): void
    {
        $this->getJson('/api/v1/tutor/materials')->assertUnauthorized();
    }

    public function test_trainee_sees_only_own_course_material(): void
    {
        $user = $this->trainee();
        $mine = $this->materialFor($this->enrolled($user));

        $foreignCourse = Course::factory()->create();
        $this->materialFor($foreignCourse);

        $ids = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/tutor/materials')
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$mine->id], $ids);
    }

    public function test_material_of_foreign_course_cannot_be_opened(): void
    {
        $user = $this->trainee();
        $this->enrolled($user);

        $foreignCourse = Course::factory()->create();
        $foreign = $this->materialFor($foreignCourse);

        // Права tutor.use достаточно — ограничение в назначении курса
        // группе, а не в самом факте права.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tutor/sessions', ['material_id' => $foreign->id])
            ->assertForbidden();
    }

    public function test_admin_with_tutor_use_sees_own_materials(): void
    {
        $user = User::factory()->create(['role' => 'Обучаемый']);
        $user->givePermissionsTo('tutor.use');
        $user->forgetPermissionCache();
        $user = $user->fresh();

        $this->materialFor($this->enrolled($user));

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/tutor/materials')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_foreign_session_is_forbidden(): void
    {
        $user = $this->trainee();
        $material = $this->materialFor($this->enrolled($user));

        $owner = $user;
        $session = TutorSession::factory()->create([
            'user_id' => $owner->id,
            'material_id' => $material->id,
        ]);

        $other = $this->trainee();
        $this->enrolled($other);

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/tutor/sessions/{$session->id}/next-question")
            ->assertForbidden();
    }

    public function test_admin_material_index_requires_tutor_manage(): void
    {
        $user = $this->trainee();
        $course = $this->enrolled($user);

        // tutor.use не даёт индексации: это отдельное действие.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tutor/admin/materials/index', ['course_id' => $course->id])
            ->assertForbidden();
    }

    // --- Граница с экзаменом -----------------------------------------

    public function test_tutor_never_creates_exam_attempts(): void
    {
        $user = $this->trainee();
        $material = $this->materialFor($this->enrolled($user));

        $session = TutorSession::factory()->create([
            'user_id' => $user->id,
            'material_id' => $material->id,
        ]);

        $item = TutorItem::factory()->create([
            'session_id' => $session->id,
            'chunk_id' => $material->chunks()->first()->id,
        ]);

        $attemptsBefore = ExamAttempt::count();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/tutor/items/{$item->id}/answer", ['answer' => 'Более 100 А'])
            ->assertCreated();

        $this->assertSame($attemptsBefore, ExamAttempt::count(), 'тренажёр не должен трогать экзамен');
        $this->assertSame(1, TutorResponse::count(), 'ответ сохраняется только в таблице тренажёра');
    }

    public function test_tutor_does_not_create_questions_in_bank(): void
    {
        $user = $this->trainee();
        $material = $this->materialFor($this->enrolled($user));

        $session = TutorSession::factory()->create([
            'user_id' => $user->id,
            'material_id' => $material->id,
        ]);

        $item = TutorItem::factory()->create([
            'session_id' => $session->id,
            'chunk_id' => $material->chunks()->first()->id,
        ]);

        $questionsBefore = Question::count();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/tutor/items/{$item->id}/answer", ['answer' => 'Более 100 А'])
            ->assertCreated();

        $this->assertSame($questionsBefore, Question::count(), 'вопросы тренажёра не попадают в банк');
    }

    public function test_stats_are_separate_from_exams(): void
    {
        $user = $this->trainee();
        $material = $this->materialFor($this->enrolled($user));

        $session = TutorSession::factory()->create([
            'user_id' => $user->id,
            'material_id' => $material->id,
        ]);

        $item = TutorItem::factory()->create([
            'session_id' => $session->id,
            'chunk_id' => $material->chunks()->first()->id,
        ]);

        // Попытка экзамена должна НЕ попасть в статистику тренажёра.
        $exam = Exam::factory()->create();
        ExamAttempt::factory()->create(['exam_id' => $exam->id, 'user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/tutor/items/{$item->id}/answer", ['answer' => 'Более 100 А'])
            ->assertCreated();

        $stats = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/tutor/stats')
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $stats['answers'], 'в статистику идёт только тренажёр');
        $this->assertSame(100, $stats['percent']);
    }

    // --- Эталон не утекает -------------------------------------------

    public function test_reference_is_hidden_until_requested(): void
    {
        $user = $this->trainee();
        $material = $this->materialFor($this->enrolled($user));

        $session = TutorSession::factory()->create([
            'user_id' => $user->id,
            'material_id' => $material->id,
        ]);

        $item = TutorItem::factory()->create([
            'session_id' => $session->id,
            'chunk_id' => $material->chunks()->first()->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/tutor/items/{$item->id}/answer", ['answer' => 'Более 100 А'])
            ->assertCreated()
            ->json('data');

        // Иначе правильный ответ едет в том же ответе, что и вопрос, и
        // тренажёр проверяется автоматически без единого ответа.
        $this->assertArrayNotHasKey('reference_answer', $response);
        $this->assertArrayNotHasKey('source_quote', $response);

        $withReference = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/tutor/items/{$item->id}/answer", [
                'answer' => 'Более 100 А',
                'show_reference' => true,
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame($item->reference_answer, $withReference['reference_answer']);
    }

    public function test_player_array_never_contains_reference(): void
    {
        $item = TutorItem::factory()->create();

        $payload = $item->toPlayerArray();

        $this->assertArrayNotHasKey('reference_answer', $payload);
        $this->assertArrayNotHasKey('source_quote', $payload);
        $this->assertArrayNotHasKey('backcheck_passed', $payload);
    }

    // --- Пути вопросов -------------------------------------------------

    public function test_question_is_not_served_without_grade(): void
    {
        $user = $this->trainee();
        $material = $this->materialFor($this->enrolled($user));

        $session = TutorSession::factory()->create([
            'user_id' => $user->id,
            'material_id' => $material->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/tutor/sessions/{$session->id}/next-question")
            ->assertOk()
            // Сервер обязан честно сказать «вопросы кончились», а не
            // вернуть пустой item: иначе фронт рисует карточку без
            // вопроса и считает это обычным состоянием.
            ->assertJsonPath('data', null)
            ->assertJsonPath('meta.exhausted', true);

        $this->assertNull($response->json('data'));
    }

    public function test_wrong_choice_is_marked_wrong(): void
    {
        $user = $this->trainee();
        $material = $this->materialFor($this->enrolled($user));

        $session = TutorSession::factory()->create([
            'user_id' => $user->id,
            'material_id' => $material->id,
        ]);

        $item = TutorItem::factory()->create([
            'session_id' => $session->id,
            'chunk_id' => $material->chunks()->first()->id,
            'options' => ['Верно', 'Неверно'],
            'reference_answer' => 'Верно',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/tutor/items/{$item->id}/answer", ['answer' => 'Неверно'])
            ->assertCreated();

        $this->assertSame(TutorResponse::VERDICT_WRONG, $response->json('data.verdict'));
        $this->assertSame(0.0, (float) $response->json('data.score'));
    }

    public function test_empty_answer_is_ungraded_not_wrong(): void
    {
        $user = $this->trainee();
        $material = $this->materialFor($this->enrolled($user));

        $session = TutorSession::factory()->create([
            'user_id' => $user->id,
            'material_id' => $material->id,
        ]);

        $item = TutorItem::factory()->create([
            'session_id' => $session->id,
            'chunk_id' => $material->chunks()->first()->id,
        ]);

        // Пустой ответ — это «не ответил», а не «не знает»: в статистике
        // разные вещи смешиваться не должны.
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/tutor/items/{$item->id}/answer", ['answer' => ''])
            ->assertCreated();

        $this->assertSame(TutorResponse::VERDICT_UNGRADED, $response->json('data.verdict'));
    }

    public function test_material_without_text_is_reported(): void
    {
        $user = $this->trainee();
        $course = $this->enrolled($user);

        $material = TutorMaterial::factory()->empty()->create([
            'course_id' => $course->id,
            'category_id' => $course->categories()->first()?->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tutor/sessions', ['material_id' => $material->id])
            ->assertStatus(409)
            ->assertJsonPath('error.message', 'По материалу нечего спрашивать: в нём нет текста');
    }
}

/**
 * Клиент с заранее заданными ответами.
 *
 * Ни одного обращения к Ollama: тесты проверяют правила приложения, а не
 * наличие локальной модели. Ответ модели здесь заведомо корректный —
 * бракованные вопросы проверяются отдельно, в TutorGroundingTest.
 */
class FakeTutorClient extends TutorClient
{
    public function health(): array
    {
        return [
            'available' => true,
            'models' => ['qwen3.5:9b-q4_K_M'],
            'model' => $this->model(),
            'model_present' => true,
        ];
    }

    public function generate(array $messages, int $maxTokens = 900): array
    {
        // Ответ на back-check: поддержка подтверждена.
        if ($this->isBackcheck($messages)) {
            return ['json' => ['support' => 'Предохранитель ПП-5 срабатывает при перегрузке по току более 100 А.'], 'raw' => ''];
        }

        $chunk = $this->chunkFrom($messages);

        $json = [
            'questions' => [
                [
                    'qtype' => 'mcq',
                    'question' => 'При каком значении тока срабатывает предохранитель ПП-5?',
                    'options' => ['Более 100 А', 'Более 50 А', 'Более 200 А', 'Не срабатывает'],
                    'reference_answer' => 'Более 100 А',
                    'source_quote' => 'Предохранитель ПП-5 срабатывает при перегрузке по току более 100 А.',
                ],
            ],
        ];

        return ['json' => $json, 'raw' => json_encode($json, JSON_UNESCAPED_UNICODE)];
    }

    public function embed(array $input): ?array
    {
        return null;
    }

    public function embedAvailable(): bool
    {
        return false;
    }

    private function isBackcheck(array $messages): bool
    {
        $system = $messages[0]['content'] ?? '';

        return str_contains($system, 'проверяющий');
    }

    private function chunkFrom(array $messages): string
    {
        $user = $messages[1]['content'] ?? '';

        if (preg_match('/---\n(.*?)\n---/s', $user, $m)) {
            return trim($m[1]);
        }

        return '';
    }
}