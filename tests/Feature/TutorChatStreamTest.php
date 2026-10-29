<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Group;
use App\Models\Group2learning;
use App\Models\User;
use App\Support\Tutor\TutorClient;
use App\Support\Tutor\TutorUnavailableException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * M3: чат-подсказчик TutorDock (POST /api/v1/tutor/chat, SSE).
 *
 * Контракт стрима зафиксирован здесь с обеих сторон: клиентский
 * парсер (tests/Unit/api.tutor-stream.spec.js) зеркалит формат,
 * который генерирует TutorController::chat. Если сервер сменит
 * формат кадра, а клиент — нет, док молча перестанет печатать
 * ответы; этот тест ловит такую рассинхронизацию на бэкенде.
 *
 * Движок подменён потоковым фейком: проверяется логика приложения
 * (права, доступ к курсу, envelope-ошибки, порядок кадров), а не
 * наличие локальной Ollama.
 */
class TutorChatStreamTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,mixed> параметры последнего streamGenerate */
    protected array $lastStreamArgs = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['tutor.enabled' => true]);
        $this->lastStreamArgs = [];

        $this->app->instance(TutorClient::class, new FakeStreamingTutorClient());
    }

    protected function tearDown(): void
    {
        UploadedFile::reset();

        parent::tearDown();
    }

    /** Фикстура «пользователь назначен на курс» — как в TutorApiTest. */
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
        $row->typeOfLesson = 'Лекция';
        $row->study_from = now()->subDay()->toDateString();
        $row->study_to = now()->addDays(14)->toDateString();
        $row->save();

        return $course;
    }

    private function trainee(): User
    {
        $user = User::factory()->create(['role' => 'Обучаемый']);
        $user->givePermissionsTo('tutor.use');
        $user->forgetPermissionCache();

        return $user->fresh();
    }

    /**
     * Отправка POST и «выполнение» отложенного стрима.
     *
     * Laravel при testable-запросе сериализует StreamedResponse сразу,
     * но наш фейк помечает поток «отложенным», поэтому тело становится
     * доступен только после sendContent() — так же, как в рантайме под
     * php-fpm: кадры пишутся в момент генерации, а не до ответа.
     */
    private function chatStream(User $user, array $payload)
    {
        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/v1/tutor/chat', $payload);

        if ($response->getStatusCode() === 200 && method_exists($response->baseResponse, 'sendContent')) {
            $response->baseResponse->sendContent();
        }

        return $response;
    }

    // --- Права и валидация -------------------------------------------

    public function test_anonymous_cannot_use_tutor_dock(): void
    {
        $this->postJson('/api/v1/tutor/chat', ['course_id' => 1, 'question' => 'q'])
            ->assertUnauthorized();
    }

    public function test_trainee_without_right_gets_403(): void
    {
        $role = \App\Models\Role::firstOrCreate(
            ['rolename' => 'Обучаемый (без прав)'], ['slug' => 'trainee-empty']
        );
        $user = User::factory()->create(['role' => 'Обучаемый']);
        $user->roles()->sync([$role->id]);
        $user->forgetPermissionCache();

        $this->actingAs($user->fresh(), 'sanctum')
            ->postJson('/api/v1/tutor/chat', ['course_id' => 1, 'question' => 'вопрос'])
            ->assertForbidden();
    }

    public function test_question_is_required_and_bounded(): void
    {
        $user = $this->trainee();
        $course = $this->enrolled($user);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tutor/chat', ['course_id' => $course->id])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tutor/chat', [
                'course_id' => $course->id,
                'question' => str_repeat('а', 601),
            ])
            ->assertStatus(422);
    }

    public function test_hint_level_whitelist(): void
    {
        $user = $this->trainee();
        $course = $this->enrolled($user);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tutor/chat', [
                'course_id' => $course->id,
                'question' => 'Почему?',
                'hint_level' => 'answer-please',
            ])
            ->assertStatus(422);
    }

    public function test_foreign_course_is_closed_even_by_direct_id(): void
    {
        $user = $this->trainee();
        $this->enrolled($user);

        $foreign = Course::factory()->create();

        // Граница та же, что у плеера: чужой курс не должен подмешивать
        // свои фрагменты в промпт даже при прямом id в запросе.
        $this->chatStream($user, ['course_id' => $foreign->id, 'question' => 'Тайна чужого курса'])
            ->assertForbidden();
    }

    // --- Контракт SSE -------------------------------------------------

    public function test_stream_frames_match_client_parser_contract(): void
    {
        $user = $this->trainee();
        $course = $this->enrolled($user);

        $_SESSION['fake_tokens'] = ['Держ', 'ись', ' протокола'];

        $response = $this->chatStream($user, [
            'course_id' => $course->id,
            'question' => 'Как читать SSE-кадры?',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');
        $response->assertHeader('X-Accel-Buffering', 'no');

        $body = $response->streamedContent();

        // Рамки ровно в том формате, который разбирает tutor.api.js:
        // event: meta → data {"t":…} по токенам → event: done.
        $this->assertStringStartsWith("event: meta\ndata: ", $body);
        $this->assertMatchesRegularExpression(
            '/"model":"[^"]+","grounded":false,"truncated":false/',
            $body,
            'meta обязан содержать модель, признак заземлённости и флаг усечения'
        );
        $this->assertStringContainsString("data: {\"t\":\"Держ\"}", $body);
        $this->assertStringContainsString("data: {\"t\":\"ись\"}", $body);
        $this->assertStringContainsString("data: {\"t\":\" протокола\"}", $body);
        $this->assertStringEndsWith("event: done\ndata: {\"truncated\":false}\n\n", $body);

        unset($_SESSION['fake_tokens']);
    }

    public function test_engine_failure_mid_stream_emits_error_event_not_broken_body(): void
    {
        $user = $this->trainee();
        $course = $this->enrolled($user);

        $_SESSION['fake_fail_midstream'] = true;

        $body = $this->chatStream($user, [
            'course_id' => $course->id,
            'question' => 'Упади посередине',
        ])->streamedContent();

        $this->assertStringContainsString("event: error\ndata: ", $body);
        $this->assertStringContainsString('движок оборвался', $body);
        // done после error быть не должно: клиент по error закрывает поток.
        $this->assertStringNotContainsString('event: done', $body);

        unset($_SESSION['fake_fail_midstream']);
    }

    public function test_empty_engine_answer_becomes_error_event(): void
    {
        $user = $this->trainee();
        $course = $this->enrolled($user);

        $_SESSION['fake_tokens'] = [];

        $body = $this->chatStream($user, [
            'course_id' => $course->id,
            'question' => 'Молчи совсем',
        ])->streamedContent();

        $this->assertStringContainsString("event: error\ndata: ", $body);
        $this->assertStringContainsString('Пустой ответ движка', $body);

        unset($_SESSION['fake_tokens']);
    }

    public function test_engine_down_before_stream_returns_envelope_503(): void
    {
        $user = $this->trainee();
        $course = $this->enrolled($user);

        $_SESSION['fake_health_down'] = true;

        // До первого байта стрима ошибка отдаётся envelope-ом: честнее,
        // чем оборванный SSE. Клиент читает engineState для подписи дока.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tutor/chat', ['course_id' => $course->id, 'question' => 'Есть кто?'])
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Нейродвижок недоступен')
            ->assertJsonPath('meta.engineState', 'connection refused');

        unset($_SESSION['fake_health_down']);
    }

    public function test_context_off_skips_material_lookup(): void
    {
        $user = $this->trainee();
        $course = $this->enrolled($user);

        $_SESSION['fake_tokens'] = ['Без контекста.'];

        $this->chatStream($user, [
            'course_id' => $course->id,
            'question' => 'Ответ без подсказки из текста',
            'context_off' => true,
        ])->assertStatus(200);

        // system-промпт не должен содержать фрагментов курса — при
        // context_off их просто не собираем (см. контроллер): сверяем
        // признаки контекста в промпте, переданном движку.
        $system = $this->app->make(TutorClient::class)->lastMessages[0]['content'] ?? '';
        $this->assertStringContainsString('КОНТЕКСТ ИЗ КУРСА НЕ ПЕРЕДАН', $system);

        unset($_SESSION['fake_tokens']);
    }

    // --- Rate limiting -------------------------------------------------

    public function test_chat_throttle_returns_envelope_with_retry_after(): void
    {
        $user = $this->trainee();
        $course = $this->enrolled($user);

        config(['tutor.chat_per_minute' => 2]);

        $_SESSION['fake_tokens'] = ['ок'];

        for ($i = 0; $i < 2; $i++) {
            $this->chatStream($user, ['course_id' => $course->id, 'question' => 'Вопрос '.$i])
                ->assertStatus(200);
        }

        $limited = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tutor/chat', ['course_id' => $course->id, 'question' => 'Лишний']);

        $limited->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Слишком много вопросов тьютору, подождите');

        // Клиент берёт кулдаун из meta.retryAfter (или заголовка): без
        // них док показывал бы заглушку вместо реальных секунд.
        $retryAfter = $limited->json('meta.retryAfter');
        $this->assertIsInt($retryAfter);
        $this->assertGreaterThanOrEqual(1, $retryAfter);
        $this->assertSame((string) $retryAfter, $limited->headers->get('Retry-After'));

        unset($_SESSION['fake_tokens']);
    }
}

/**
 * Потоковый фейк движка.
 *
 * health()/model() наследуются от родителя-фейка TrainerApiTest-подобной
 * схемы (available всегда, кроме флага сессии). streamGenerate читает
 * список токенов из $_SESSION — потому что контейнер пересоздаётся между
 * запросами одного теста, а инстанс-подмена живёт до следующего setUp.
 */
class FakeStreamingTutorClient extends TutorClient
{
    /** @var array<int,array{role:string,content:string}> */
    public array $lastMessages = [];

    public function health(): array
    {
        if (! empty($_SESSION['fake_health_down'])) {
            return ['available' => false, 'error' => 'connection refused'];
        }

        return [
            'available' => true,
            'models' => ['qwen3.5:9b-q4_K_M'],
            'model' => $this->model(),
            'model_present' => true,
        ];
    }

    public function streamGenerate(array $messages, callable $onToken, int $maxTokens = 500): void
    {
        $this->lastMessages = $messages;

        if (! empty($_SESSION['fake_fail_midstream'])) {
            $onToken('Начало');

            throw new TutorUnavailableException('движок оборвался');
        }

        foreach ((array) ($_SESSION['fake_tokens'] ?? ['ответ']) as $token) {
            $onToken((string) $token);
        }
    }

    public function generate(array $messages, int $maxTokens = 900): array
    {
        return ['json' => [], 'raw' => ''];
    }

    public function embed(array $input): ?array
    {
        return null;
    }

    public function embedAvailable(): bool
    {
        return false;
    }
}
