<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Регистрация: роль нельзя выдать себе самому.
 *
 * Найдено на живой базе во время работы над доступом обучаемого:
 * POST /api/register — публичный маршрут ($this->middleware("auth:sanctum")
 * ->except(['login', 'register'])), а роль бралась из тела запроса без
 * проверки:
 *
 *     $user->role = $request->role;
 *
 * То есть запрос
 *     {"fio": "X", "password": "...", "password_confirmation": "...",
 *      "role": "Администратор"}
 * давал новому пользователю все 26 прав каталога и is_super_admin = true.
 * Это повышение привилегий из публичного эндпоинта.
 *
 * Вторая половина того же бага: если роль не передавали, она сохранялась
 * как NULL, и permissionSlugs() возвращал пустой массив — новый человек
 * получал приложение вообще без прав (пустое меню, 403 на всех страницах).
 */
class RegistrationRoleTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    private const PASSWORD = 'secret123';

    public function test_anonymous_cannot_self_assign_admin_role(): void
    {
        $response = $this->postJson('/api/register', [
            'fio' => 'Сам себе администратор',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'role' => 'Администратор',
        ]);

        $response->assertCreated();

        $user = User::where('fio', 'Сам себе администратор')->firstOrFail();

        $this->assertNotSame(
            'Администратор',
            $user->role,
            'публичная регистрация не может выдать роль администратора'
        );
        $this->assertFalse($user->isSuperAdmin());
        $this->assertFalse($user->hasPermission('users.permissions'));
        $this->assertFalse($user->hasPermission('users.create'));
    }

    public function test_anonymous_cannot_self_assign_instructor_role(): void
    {
        $this->postJson('/api/register', [
            'fio' => 'Сам себе инструктор',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'role' => 'Инструктор',
        ])->assertCreated();

        $user = User::where('fio', 'Сам себе инструктор')->firstOrFail();

        $this->assertFalse($user->hasPermission('courses.manage'));
        $this->assertFalse($user->hasPermission('groups.manage'));
    }

    public function test_public_registration_gets_working_trainee_role(): void
    {
        // Регресс на «аккаунт без прав»: роль не передавали — она была
        // NULL, permissionSlugs() пустой, у человека пустое меню.
        $this->postJson('/api/register', [
            'fio' => 'Обычный новичок',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ])->assertCreated();

        $user = User::where('fio', 'Обычный новичок')->firstOrFail();

        $this->assertNotNull($user->role, 'роль должна проставляться по умолчанию');
        $this->assertTrue($user->isTrainee());

        // И права чтения на месте — иначе учебный материал недоступен.
        $this->assertTrue($user->hasPermission('courses.view'));
        $this->assertTrue($user->hasPermission('content.view'));
        $this->assertTrue($user->hasPermission('exams.take'));
    }

    public function test_admin_can_still_create_user_with_any_known_role(): void
    {
        // Сценарий «Новый пользователь» в меню администратора не должен
        // сломаться: роль назначать ему по-прежнему можно.
        $this->asUser(['role' => 'Администратор']);

        $response = $this->postJson('/api/register', [
            'fio' => 'Новый инструктор',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'role' => 'Инструктор',
        ]);

        $response->assertCreated();

        $user = User::where('fio', 'Новый инструктор')->firstOrFail();

        $this->assertTrue($user->isInstructor());
        $this->assertTrue($user->hasPermission('courses.manage'));
    }

    public function test_unknown_role_is_rejected_instead_of_silently_stored(): void
    {
        $this->asUser(['role' => 'Администратор']);

        $this->postJson('/api/register', [
            'fio' => 'С несуществующей ролью',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'role' => 'Суперадминистратор',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['fio' => 'С несуществующей ролью']);
    }

    public function test_self_registration_cannot_join_arbitrary_group(): void
    {
        // Группа определяет учебный план: обучаемый видит ровно те курсы,
        // на которые записана его группа. Если group_id можно было задать
        // при публичной регистрации, человек сам подписывался бы на
        // материалы любой группы.
        $group = \App\Models\Group::factory()->create();

        $this->postJson('/api/register', [
            'fio' => 'Сам себе группа',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'group_id' => $group->id,
        ])->assertCreated();

        $user = User::where('fio', 'Сам себе группа')->firstOrFail();

        $this->assertNull(
            $user->group_id,
            'публичная регистрация не должна давать доступ к чужой группе'
        );

        // Пустая группа означает пустой учебный план — это видно из
        // скоупа CourseVisibility: назначений нет, курсов не видно.
        $this->assertSame(0, \App\Support\CourseVisibility::enrolledQuery($user)->count());
    }

    public function test_admin_can_still_assign_group(): void
    {
        // Сценарий методиста не должен сломаться: он создаёт обучаемого
        // сразу в нужной группе.
        $group = \App\Models\Group::factory()->create();
        $this->asUser(['role' => 'Администратор']);

        $this->postJson('/api/register', [
            'fio' => 'Обучаемый в группе',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'role' => 'Обучаемый',
            'group_id' => $group->id,
        ])->assertCreated();

        $user = User::where('fio', 'Обучаемый в группе')->firstOrFail();

        $this->assertSame($group->id, $user->group_id);
    }
}
