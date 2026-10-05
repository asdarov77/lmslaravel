<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Контракт ролей: один канонический формат вместо строковых сравнений.
 *
 * Проблема, которую закрывает файл. Роль хранится в ДВУХ местах:
 * строковая колонка users.role и связь role_user. UI назначает роль
 * через AuthController::chroll, который синхронизирует ТОЛЬКО role_user
 * и колонку users.role не трогает. При этом:
 *
 *  - User::isAdmin()/isTrainee() читали только колонку -> пользователь с
 *    ролью из role_user считался «без роли»;
 *  - HasRolesAndPermissions::matrixPermissionSlugs() тоже читала только
 *    колонку -> такой пользователь не получал базовых прав из role_matrix;
 *  - Home.vue искал компонент по строке user.role -> показывал пустой экран;
 *  - login отдавал roles как список названий, а /api/v1/me как массив
 *    объектов -> фронт разбирался с этим в двух местах по-разному.
 */
class RoleContractTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    private function role(string $slug, ?string $name = null): Role
    {
        return Role::factory()->create([
            'slug' => $slug,
            'rolename' => $name ?? $slug,
        ]);
    }

    public function test_role_from_pivot_is_recognised_without_role_column(): void
    {
        // Ровно то, что делает chroll: связь есть, колонка пуста.
        $user = User::factory()->create(['role' => null]);
        $user->roles()->attach($this->role('instructor'));

        $this->assertTrue($user->fresh()->isInstructor(), 'роль из role_user распознана');
        $this->assertFalse($user->fresh()->isAdmin());
        $this->assertFalse($user->fresh()->isTrainee());
    }

    public function test_role_column_is_recognised(): void
    {
        $this->assertTrue(User::factory()->create(['role' => 'Обучаемый'])->isTrainee());
        $this->assertTrue(User::factory()->create(['role' => 'trainee'])->isTrainee());
        $this->assertTrue(User::factory()->create(['role' => 'admin'])->isAdmin());
        $this->assertTrue(User::factory()->create(['role' => 'Инструктор'])->isInstructor());
    }

    public function test_pivot_role_wins_over_stale_column(): void
    {
        // Колонка осталась от прежнего назначения, а роль уже сменили
        // через chroll. Источником истины служит role_user.
        $user = User::factory()->create(['role' => 'Обучаемый']);
        $user->roles()->attach($this->role('admin'));

        $fresh = $user->fresh();
        $this->assertTrue($fresh->isAdmin());
        // Колонка устарела, поэтому оба флага истинны — это осознанно:
        // приоритет у связи, а не «исключение», чтобы не потерять роль.
        $this->assertContains('trainee', $fresh->roleSlugs());
        $this->assertContains('admin', $fresh->roleSlugs());
    }

    public function test_role_name_without_slug_is_matched_by_alias(): void
    {
        // Старая запись в roles: slug пустой, есть только название.
        $user = User::factory()->create(['role' => null]);
        $user->roles()->attach($this->role('', 'Обучаемый'));

        $this->assertTrue($user->fresh()->isTrainee());
    }

    public function test_pivot_admin_is_super_admin_without_explicit_permissions(): void
    {
        // Главный регресс. Назначение роли через chroll пишет только
        // role_user. Раньше isSuperAdmin() смотрел лишь на колонку role,
        // поэтому администратор из UI получал isSuperAdmin() === false и
        // пустой набор прав: боковое меню пустело, /api/v1/courses давал
        // 403, а Gate::before его не пропускал.
        $user = User::factory()->create(['role' => null]);
        $user->roles()->attach($this->role('admin'));

        $fresh = $user->fresh();

        $this->assertTrue($fresh->isSuperAdmin());
        $this->assertTrue($fresh->hasPermission('users.view'));
        $this->assertTrue($fresh->hasPermission('любое.право.которого.нет.в.каталоге'));
    }

    public function test_pivot_trainee_receives_permissions_of_its_role(): void
    {
        // Штатный путь: chroll -> role_user -> permissions_roles.
        // Права приходят через связь роли, а не из role_matrix (матрица
        // для таких записей отключена намеренно, чтобы не обходить
        // явный отзыв права у роли).
        $role = $this->role('trainee');
        $permissions = collect(['courses.view', 'exams.take'])->map(
            fn (string $slug) => Permission::create(['name' => $slug, 'slug' => $slug])
        );
        $role->permissions()->sync($permissions->pluck('id'));

        $user = User::factory()->create(['role' => null]);
        $user->roles()->attach($role);

        $fresh = $user->fresh();
        $this->assertTrue($fresh->isTrainee());
        $this->assertTrue($fresh->hasPermission('courses.view'));
        $this->assertTrue($fresh->hasPermission('exams.take'));
        $this->assertFalse($fresh->hasPermission('users.manage'));
    }

    public function test_unknown_role_is_preserved_not_dropped(): void
    {
        // Пустой экран в Home.vue был следствием того, что неопознанное
        // значение исчезало. Сохраняем его, чтобы UI мог показать.
        $user = User::factory()->create(['role' => 'Методист']);

        $this->assertSame(['Методист'], $user->roleSlugs());
        $this->assertFalse($user->isTrainee());
        $this->assertFalse($user->isAdmin());
    }

    public function test_role_payloads_are_uniform(): void
    {
        $user = User::factory()->create(['role' => null]);
        $role = $this->role('trainee', 'Обучаемый');
        $user->roles()->attach($role);

        $payload = $user->fresh()->rolePayloads();

        $this->assertCount(1, $payload);
        $this->assertSame('trainee', $payload[0]['slug']);
        $this->assertSame('Обучаемый', $payload[0]['name']);
        $this->assertSame($role->id, $payload[0]['id']);
    }

    public function test_me_returns_role_slugs(): void
    {
        $user = User::factory()->create(['role' => null]);
        $user->roles()->attach($this->role('instructor', 'Инструктор'));
        $this->asExistingUser($user);

        $data = $this->getJson('/api/v1/me')->assertOk()->json('data');

        $this->assertSame(['instructor'], $data['role_slugs']);
        $this->assertSame('instructor', $data['roles'][0]['slug']);
    }

    public function test_login_and_me_return_same_role_format(): void
    {
        // Формат ролей в login и /me должен совпадать, иначе фронт
        // получает ['Обучаемый'] в одном ответе и {slug: ...} в другом.
        $user = User::factory()->create([
            'role' => null,
            'fio' => 'RoleContractTester',
            'password' => Hash::make('secret123'),
        ]);
        $user->roles()->attach($this->role('trainee', 'Обучаемый'));

        // login идёт по fio, а не по email, и ответ оборачивается в
        // envelope middleware ApiResponseEnvelope.
        $login = $this->postJson('/api/login', [
            'fio' => 'RoleContractTester',
            'password' => 'secret123',
        ])->assertOk()->json('data');

        $this->assertSame(['trainee'], $login['role_slugs']);
        $this->assertIsArray($login['roles'][0]);
        $this->assertArrayHasKey('slug', $login['roles'][0]);
        $this->assertSame('Обучаемый', $login['roles'][0]['name']);
    }

    public function test_logout_clears_role_state(): void
    {
        $user = User::factory()->create(['role' => 'Администратор']);
        $this->asExistingUser($user);

        $this->assertTrue($user->isAdmin());

        // POST /api/logout не объявлен: v1-вариант — POST, корневой — GET.
        $this->postJson('/api/v1/logout')->assertOk()->assertJsonPath('success', true);

        $this->app['auth']->forgetGuards();
        $this->assertNull(auth()->user());
    }

    // ---------------------------------------------------- назначение ролей

    public function test_chroll_is_reachable_and_assigns_role(): void
    {
        // Маршрут был закомментирован, а страница UserChrole.vue
        // продолжала его вызывать: назначить роль было нечем.
        $instructor = $this->role('instructor');
        $user = User::factory()->create(['role' => 'Обучаемый']);
        $admin = $this->admin();

        $this->putJson("/api/user/chroll/{$user->id}", ['role_id' => [$instructor->id]])
            ->assertOk();

        $this->assertDatabaseHas('role_user', [
            'user_id' => $user->id,
            'role_id' => $instructor->id,
        ]);

        $this->asExistingUser($admin);
        $this->getJson('/api/v1/me')->assertOk();
    }

    public function test_chroll_rejects_self_escalation(): void
    {
        // Инструктор с users.permissions мог назначить себе роль
        // администратора. chroll это блокирует.
        $adminRole = $this->role('admin');
        $actor = User::factory()->create(['role' => 'Инструктор']);
        $permission = Permission::create(['name' => 'users.permissions', 'slug' => 'users.permissions']);
        $actor->givePermissionsTo($permission->slug);
        $this->asExistingUser($actor);

        $this->putJson("/api/user/chroll/{$actor->id}", ['role_id' => [$adminRole->id]])
            ->assertStatus(403);

        $this->assertFalse($actor->fresh()->isAdmin());
    }

    public function test_chroll_requires_permission(): void
    {
        $user = User::factory()->create(['role' => 'Обучаемый']);
        $role = $this->role('instructor');
        $this->asUser(['role' => 'Инструктор']);

        $this->putJson("/api/user/chroll/{$user->id}", ['role_id' => [$role->id]])
            ->assertStatus(403);

        $this->assertDatabaseMissing('role_user', ['user_id' => $user->id]);
    }

    public function test_chroll_rejects_unknown_role_id(): void
    {
        $user = User::factory()->create(['role' => 'Обучаемый']);
        $this->admin();

        // Несуществующий id в sync() молча записался бы в role_user.
        $this->putJson("/api/user/chroll/{$user->id}", ['role_id' => [999999]])
            ->assertStatus(422);

        $this->assertDatabaseMissing('role_user', ['user_id' => $user->id]);
    }

    public function test_chroll_requires_role_id_key(): void
    {
        $user = User::factory()->create(['role' => 'Обучаемый']);
        $this->admin();

        $this->putJson("/api/user/chroll/{$user->id}", [])
            ->assertStatus(422);
    }

    public function test_chroll_allows_clearing_roles(): void
    {
        // Пустой список — это снятие ролей, а не ошибка.
        $user = User::factory()->create(['role' => null]);
        $user->roles()->attach($this->role('instructor'));
        $this->admin();

        $this->putJson("/api/user/chroll/{$user->id}", ['role_id' => []])
            ->assertOk()
            ->assertJsonPath('data.role_slugs', []);

        $this->assertDatabaseMissing('role_user', ['user_id' => $user->id]);
    }
}
