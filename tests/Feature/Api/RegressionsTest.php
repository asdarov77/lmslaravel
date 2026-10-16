<?php

namespace Tests\Feature\Api;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AuthenticatesApi;
use Tests\TestCase;

/**
 * Регрессы к дефектам, найденным при сплошном обходе интерфейса.
 *
 * Каждый тест here закрывает конкретную поломку, а не «вообще доступ»:
 * без них те же ошибки возвращаются молча, потому что проявляются
 * только в браузере — через глобальный обработчик ответов SPA.
 */
class RegressionsTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    /*
     * 1. ОБУЧАЕМЫЙ ВЫЛЕТАЛ НА «В ДОСТУПЕ ОТКАЗАНО» ПРИ ПЕРЕЗАГРУЗКЕ.
     *
     * Фронт грузит настройки бренда один раз при старте приложения
     * (app.js → initWhiteLabel), а endpoint закрывался правом
     * white_label.manage. Общий обработчик httpClient на ЛЮБОЙ 403
     * уводит всё SPA на страницу «В доступе отказано», поэтому
     * обучаемый после F5 (или входа по прямой ссылке) терял
     * интерфейс целиком.
     *
     * Чтение бренда доступно любому вошедшему; ЗАПИСЬ остаётся за
     * правом — и это проверяется отдельно, ниже.
     */
    public function test_trainee_can_read_white_label(): void
    {
        $this->asUser(['role' => 'Обучаемый']);

        $this->getJson('/api/white-label')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['css_variables']]);
    }

    public function test_trainee_cannot_change_white_label(): void
    {
        $this->asUser(['role' => 'Обучаемый']);

        $this->putJson('/api/white-label', ['organization_name' => 'взлом'])
            ->assertForbidden();
    }

    /*
     * 2. ПРОФИЛЬ ПРЕПОДАВАТЕЛЯ ОТДАВАЛ 500.
     *
     * InstructorProfileController делал User::with(['avatar']), а
     * связи avatar у модели нет — это строковая колонка. Любой запрос
     * профиля падал с «Call to undefined relationship [avatar]».
     */
    public function test_instructor_profile_does_not_500(): void
    {
        $this->asUser(['role' => 'Инструктор']);

        $this->getJson('/api/instructors/' . $this->userIdOfLastRequest())
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'fio', 'courses', 'rating']]);
    }

    public function test_instructor_profile_unknown_id_is_404_not_500(): void
    {
        $this->asUser(['role' => 'Инструктор']);

        $this->getJson('/api/instructors/999999')->assertNotFound();
    }

    /*
     * 3. СОБЫТИЯ КАЛЕНДАРЯ: МАРШРУТОВ НЕ БЫЛО ВООБЩЕ.
     *
     * Методы store/destroy в контроллере написаны, но не подключены:
     * фронт звал POST /api/calendar/events и получал 404, а общий
     * обработчик уводил всё приложение на страницу «Страница не
     * найдена».
     */
    public function test_calendar_event_can_be_created_and_deleted(): void
    {
        $admin = $this->admin();

        $created = $this->postJson('/api/calendar/events', [
            'title' => 'Проверка регрессии',
            'start' => '2026-11-01',
            'end' => '2026-11-03',
        ])->assertCreated()->assertJsonPath('success', true);

        $id = $created->json('data.id');
        $this->assertNotNull($id, 'событие должно вернуть id');

        // Событие появилось в ленте.
        $this->getJson('/api/calendar')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Проверка регрессии']);

        $this->deleteJson('/api/calendar/events/' . $id)->assertNoContent();

        $this->getJson('/api/calendar')
            ->assertOk()
            ->assertJsonMissing(['title' => 'Проверка регрессии']);

        unset($admin);
    }

    public function test_trainee_cannot_create_calendar_event(): void
    {
        $this->asUser(['role' => 'Обучаемый']);

        $this->postJson('/api/calendar/events', [
            'title' => 'Не должно создаться',
            'start' => '2026-11-01',
        ])->assertForbidden();
    }

    /*
     * 4. ШКАЛЫ ОЦЕНОК ЧИТАЛСЯ ЛЮБЫМ ВОШЕДШИМ.
     *
     * GET /api/grade-boundary висел только на auth:sanctum, хотя сама
     * страница и соседние маршруты блока закрыты settings.manage.
     */
    public function test_grade_boundary_index_requires_settings_manage(): void
    {
        $this->asUser(['role' => 'Обучаемый']);

        $this->getJson('/api/grade-boundary')->assertForbidden();
    }

    public function test_grade_boundary_index_available_to_admin(): void
    {
        $this->admin();

        $this->getJson('/api/grade-boundary')->assertOk()->assertJsonStructure(['data']);
    }

    /*
     * 5. АВАТАР НЕЛЬЗЯ БЫЛО УБРАТЬ.
     *
     * Кнопка «Убрать фото» меняла только состояние Vuex, метода на
     * сервере не было, и после перезагрузки аватар возвращался.
     */
    public function test_avatar_can_be_removed(): void
    {
        $user = $this->asUser(['role' => 'Обучаемый', 'avatar' => 'avatars/1/x.png']);

        $this->assertSame('avatars/1/x.png', $user->fresh()->avatar);

        $this->deleteJson('/api/user/avatar')
            ->assertOk()
            ->assertJsonPath('data.avatar', null);

        $this->assertNull($user->fresh()->avatar, 'аватар должен быть стёрт из базы');
    }

    /*
     * 6. СПИСОК ПОЛЬЗОВАТЕЛЕЙ ФИЛЬТРУЕТСЯ ПО ИМЕНИ РОЛИ.
     *
     * Мастер экзамена звал /api/users?role=trainee — такого маршрута
     * не было (404 → страница ошибки). Фильтр переехал в
     * GET /api/v1/users.
     */
    public function test_v1_users_filters_by_role(): void
    {
        $this->admin();

        User::factory()->count(2)->create(['role' => 'Обучаемый']);
        User::factory()->count(3)->create(['role' => 'Инструктор']);

        $trainees = $this->getJson('/api/v1/users?role=trainee')->assertOk()->json('data');
        $instructors = $this->getJson('/api/v1/users?role=Инструктор')->assertOk()->json('data');

        $this->assertCount(2, $trainees);
        $this->assertCount(3, $instructors);
        foreach ($trainees as $u) {
            $this->assertSame('Обучаемый', $u['role']);
        }
    }

    public function test_v1_users_rejects_invalid_role_filter(): void
    {
        $this->admin();

        $this->getJson('/api/v1/users?role=' . str_repeat('a', 40))->assertStatus(422);
    }

    /*
     * 7. СОБЫТИЕ КАЛЕНДАРЯ ЧУЖОЙ ГРУППЫ НЕ СОЗДАЁТСЯ.
     *
     * Группа в теле запроса проверяется: иначе событие можно было бы
     * «прибить» к чужой группе, и оно появилось бы в её календаре.
     */
    public function test_calendar_event_group_is_checked(): void
    {
        $this->asUser(['role' => 'Обучаемый']);

        $other = Group::factory()->create();

        $this->postJson('/api/calendar/events', [
            'title' => 'Чужая группа',
            'start' => '2026-11-05',
            'group_id' => $other->id,
        ])->assertForbidden();
    }

    /**
     * Id пользователя, под которым выполнен последний запрос.
     */
    private function userIdOfLastRequest(): int
    {
        return (int) $this->getJson('/api/v1/me')
            ->assertOk()
            ->json('data.user.id');
    }
}