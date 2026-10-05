<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Group2learning;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Policies\Group2learningPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Область полномочий own/any: политики вместо сравнений в контроллерах.
 *
 * До появления политик решения принимались в разных местах и были
 * неполными:
 *
 *  - getUserList() определял видимость сравнением строк
 *    `Auth::user()->role == "Администратор"`, поэтому администратор с
 *    ролью из role_user видел только свою группу;
 *  - getUser($id) отдавал карточку любого пользователя тому, у кого
 *    есть users.view, — утечка профилей между группами;
 *  - destroy() защищал ровно одного пользователя числом `$id != 1`;
 *  - show() учебной записи висел только на auth:sanctum и отдавал чужой
 *    учебный план по перебору идентификатора, хотя index к тому моменту
 *    уже был ограничен своей группой;
 *  - chpass() содержал собственную проверку мимо области видимости.
 *
 * Матрица ролей здесь повторяет config/permissions.php: инструктор
 * имеет users.view (но не users.update/users.delete), обучаемый —
 * courses.view, content.view, exams.take.
 */
class RbacPolicyTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    private Group $own;

    private Group $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->own = Group::factory()->create();
        $this->other = Group::factory()->create();
    }

    /**
     * Выдать права, создав недостающие строки каталога.
     *
     * givePermissionsTo() ищет права по slug в таблице permissions и
     * при пустом результате МОЛЧА выходит: в тестовой базе после
     * RefreshDatabase каталога нет, поэтому «выдать users.view» молча
     * не выдавало ничего, и политика проверялась на пользователе вообще
     * без прав.
     */
    private function grant(User $user, string ...$slugs): User
    {
        foreach ($slugs as $slug) {
            Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
        }

        $user->givePermissionsTo(...$slugs);
        $user->forgetPermissionCache();

        return $user->fresh();
    }

    /** Суперадминистратор: роль admin. */
    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'Администратор', 'group_id' => null]);
    }

    /** Инструктор своей группы: users.view, как в role_matrix. */
    private function instructor(?Group $group = null): User
    {
        $user = User::factory()->create([
            'role' => 'Инструктор',
            'group_id' => ($group ?? $this->own)->id,
        ]);
        return $this->grant($user, 'users.view');
    }

    private function trainee(?Group $group = null): User
    {
        return User::factory()->create([
            'role' => 'Обучаемый',
            'group_id' => ($group ?? $this->own)->id,
        ]);
    }

    // ------------------------------------------------------- UserPolicy::scopeQuery

    public function test_scope_query_returns_everything_for_super_admin(): void
    {
        $this->trainee($this->own);
        $this->trainee($this->other);

        $admin = $this->superAdmin();
        $rows = UserPolicy::scopeQuery($admin)->get();

        // Администратор с ролью из колонки видит обе группы и себя.
        $this->assertCount(3, $rows);
        $this->assertContains($admin->id, $rows->pluck('id')->all());
    }

    public function test_scope_query_returns_everything_for_admin_role_from_pivot(): void
    {
        // Регресс на сравнение строк: роль «Администратор» живёт только
        // в role_user, колонка users.role пуста. Раньше такой
        // администратор получал список своей группы — то есть пустой.
        $admin = User::factory()->create(['role' => null]);
        $admin->roles()->attach(Role::factory()->create([
            'slug' => 'admin',
            'rolename' => 'Администратор',
        ])->id);

        $this->trainee($this->own);
        $this->trainee($this->other);

        $rows = UserPolicy::scopeQuery($admin->fresh())->get();

        $this->assertCount(3, $rows);
    }

    public function test_scope_query_limits_instructor_to_own_group(): void
    {
        $mine = $this->trainee($this->own);
        $theirs = $this->trainee($this->other);
        $instructor = $this->instructor();

        $rows = UserPolicy::scopeQuery($instructor)->get();

        // Своя группа плюс собственная запись: иначе инструктор не
        // видел бы собственный профиль в списке своей группы.
        $this->assertEqualsCanonicalizing(
            [$mine->id, $instructor->id],
            $rows->pluck('id')->all()
        );
        $this->assertNotContains($theirs->id, $rows->pluck('id')->all());
    }

    public function test_scope_query_shows_own_record_even_without_group(): void
    {
        // Пустая группа — это «нет своей части», а не «вся система»:
        // видно себя и тех, у кого группа не проставлена.
        $actor = User::factory()->create(['role' => 'Инструктор', 'group_id' => null]);
        $this->grant($actor, 'users.view');

        $orphan = User::factory()->create(['role' => 'Обучаемый', 'group_id' => null]);
        $this->trainee($this->other);

        $ids = UserPolicy::scopeQuery($actor)->pluck('id')->all();

        $this->assertContains($actor->id, $ids);
        $this->assertContains($orphan->id, $ids);
        $this->assertNotContains(
            User::where('group_id', $this->other->id)->first()->id,
            $ids
        );
    }

    // --------------------------------------------------------- UserPolicy: view

    public function test_anyone_can_view_own_profile(): void
    {
        $actor = $this->trainee();

        $this->assertTrue(app(UserPolicy::class)->view($actor, $actor));
    }

    public function test_instructor_cannot_view_user_of_another_group(): void
    {
        $actor = $this->instructor();
        $victim = $this->trainee($this->other);

        $this->assertFalse(app(UserPolicy::class)->view($actor, $victim));
    }

    public function test_super_admin_can_view_anyone(): void
    {
        $actor = $this->superAdmin();

        $this->assertTrue(app(UserPolicy::class)->view($actor, $this->trainee($this->other)));
    }

    public function test_user_endpoint_rejects_cross_group_read(): void
    {
        // GET /api/user/list/{id} отдавал ЛЮБОГО пользователя тому, у кого
        // есть users.view: инструктор группы А открывал сотрудника
        // группы Б по угаданному идентификатору.
        $actor = $this->instructor();
        $victim = $this->trainee($this->other);
        $this->asExistingUser($actor);

        $this->getJson("/api/user/list/{$victim->id}")->assertStatus(403);
    }

    public function test_user_endpoint_allows_own_group_read(): void
    {
        $actor = $this->instructor();
        $colleague = $this->trainee();
        $this->asExistingUser($actor);

        $this->getJson("/api/user/list/{$colleague->id}")->assertStatus(200);
    }

    // -------------------------------------------------------- UserPolicy: update

    public function test_instructor_without_users_update_cannot_edit_even_own_group(): void
    {
        // У инструктора в role_matrix есть users.view, но не
        // users.update: смотреть состав группы можно, править — нет.
        $actor = $this->instructor();
        $target = $this->trainee();

        $this->assertFalse(app(UserPolicy::class)->update($actor, $target));
    }

    public function test_users_update_allows_editing_own_group(): void
    {
        $actor = $this->instructor();
        $this->grant($actor, 'users.update');

        $this->assertTrue(app(UserPolicy::class)->update($actor, $this->trainee()));
        $this->assertFalse(app(UserPolicy::class)->update($actor, $this->trainee($this->other)));
    }

    public function test_instructor_cannot_edit_admin_even_with_users_update(): void
    {
        $actor = $this->instructor();
        $this->grant($actor, 'users.update');
        $admin = $this->superAdmin();

        $this->assertFalse(app(UserPolicy::class)->update($actor, $admin));
        $this->assertTrue(app(UserPolicy::class)->update($this->superAdmin(), $admin));
    }

    public function test_patch_user_of_another_group_is_forbidden(): void
    {
        $actor = $this->instructor();
        $this->grant($actor, 'users.update');
        $victim = $this->trainee($this->other);
        $this->asExistingUser($actor);

        $this->patchJson("/api/user/{$victim->id}", [
            'fio' => 'Взломан',
            'group_id' => $victim->group_id,
        ])->assertStatus(403);

        $this->assertDatabaseHas('users', ['id' => $victim->id, 'fio' => $victim->fio]);
    }

    // -------------------------------------------------- UserPolicy: changePassword

    public function test_instructor_cannot_change_password_in_another_group(): void
    {
        // Проверка жила прямо в chpass() и область видимости обходила:
        // своего пользователя — можно, чужого — по наличию users.update,
        // без учёта группы.
        $actor = $this->instructor();
        $this->grant($actor, 'users.update');
        $victim = $this->trainee($this->other);
        $this->asExistingUser($actor);

        $this->putJson("/api/user/chpass/{$victim->id}", [
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertStatus(403);
    }

    public function test_user_can_change_own_password(): void
    {
        $actor = $this->trainee();
        $this->asExistingUser($actor);

        // chpass отвечает 201 — так возвращает сама операция смены пароля.
        $this->putJson("/api/user/chpass/{$actor->id}", [
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertStatus(201);
    }

    // ------------------------------------------------------- UserPolicy: delete

    public function test_delete_requires_scope_and_rejects_self(): void
    {
        $policy = app(UserPolicy::class);

        $admin = $this->superAdmin();
        $self = $admin;
        $this->assertFalse($policy->delete($admin, $self), 'себя удалять нельзя');

        $instructor = $this->instructor();
        $instructor = $this->grant($instructor, 'users.delete');
        $this->assertTrue($policy->delete($instructor, $this->trainee()));
        $this->assertFalse($policy->delete($instructor, $this->trainee($this->other)));
        $this->assertFalse($policy->delete($instructor, $this->superAdmin()));
    }

    // ---------------------------------------------------- UserPolicy: assignRole

    public function test_role_assignment_respects_group_and_admin(): void
    {
        $policy = app(UserPolicy::class);

        $instructor = $this->instructor();
        $this->assertFalse(
            $policy->assignRole($instructor, $this->trainee()),
            'инструктору без users.permissions назначать роли нельзя'
        );

        $instructor = $this->grant($instructor, 'users.permissions');
        $this->assertTrue($policy->assignRole($instructor, $this->trainee()));
        $this->assertFalse($policy->assignRole($instructor, $this->trainee($this->other)));
        $this->assertFalse($policy->assignRole($instructor, $instructor));
        $this->assertFalse($policy->assignRole($instructor, $this->superAdmin()));

        $admin = $this->superAdmin();
        $this->assertTrue($policy->assignRole($admin, $this->instructor()));
    }

    public function test_chroll_cannot_move_user_to_another_group(): void
    {
        $actor = $this->superAdmin();
        $target = $this->trainee($this->other);
        $role = Role::factory()->create(['slug' => 'instructor', 'rolename' => 'Инструктор']);
        $this->asExistingUser($actor);

        $this->putJson("/api/user/chroll/{$target->id}", ['role_id' => [$role->id]])
            ->assertOk();

        $this->assertTrue($target->fresh()->isInstructor());
        // Группа не меняется вместе с ролью — это отдельная операция.
        $this->assertSame($this->other->id, $target->fresh()->group_id);
    }

    // -------------------------------------------------- Group2learningPolicy: view

    private function learning(?Group $group = null): Group2learning
    {
        return Group2learning::factory()->create([
            'group_id' => ($group ?? $this->own)->id,
        ]);
    }

    public function test_trainee_sees_own_group_learning(): void
    {
        $actor = $this->trainee();
        $record = $this->learning($this->own);

        $this->assertTrue(app(Group2learningPolicy::class)->view($actor, $record));
        $this->assertFalse(
            app(Group2learningPolicy::class)->view($actor, $this->learning($this->other))
        );
    }

    public function test_methodologist_sees_all_groups(): void
    {
        $actor = $this->instructor();
        $this->grant($actor, 'users.courses');

        $this->assertTrue(
            app(Group2learningPolicy::class)->view($actor, $this->learning($this->other))
        );
    }

    public function test_learning_show_rejects_other_group(): void
    {
        // SHOW висел только на auth:sanctum: любой вошедший читал чужой
        // учебный план простым перебором id, хотя index был уже scoped.
        $record = $this->learning($this->other);
        $this->asExistingUser($this->trainee());

        $this->getJson("/api/learning/{$record->id}")->assertStatus(403);
    }

    public function test_learning_show_allows_own_group(): void
    {
        $record = $this->learning($this->own);
        $this->asExistingUser($this->trainee());

        $this->getJson("/api/learning/{$record->id}")->assertStatus(200);
    }

    public function test_learning_scope_query_helper_matches_policy(): void
    {
        $mine = $this->learning($this->own);
        $this->learning($this->other);

        $ids = Group2learningPolicy::scopeQuery($this->trainee(), Group2learning::query())
            ->pluck('id')
            ->all();

        $this->assertSame([$mine->id], $ids);
    }

    public function test_learning_scope_query_returns_nothing_without_group(): void
    {
        $this->learning($this->own);
        $actor = User::factory()->create(['role' => 'Обучаемый', 'group_id' => null]);

        $ids = Group2learningPolicy::scopeQuery($actor, Group2learning::query())->count();

        $this->assertSame(0, $ids);
    }

    public function test_learning_update_requires_write_permission(): void
    {
        $policy = app(Group2learningPolicy::class);
        $record = $this->learning($this->own);

        $viewer = $this->trainee();
        $this->assertFalse($policy->update($viewer, $record));

        $methodologist = $this->instructor();
        $methodologist = $this->grant($methodologist, 'users.courses');
        $this->assertTrue($policy->update($methodologist, $record));
    }

    // --------------------------------------------------------------- Gate-ability

    public function test_gate_abilities_are_registered(): void
    {
        $actor = $this->instructor();
        $target = $this->trainee();

        // Способности с аргументом записи: own/any различаются по имени.
        $admin = $this->superAdmin();

        $this->assertTrue($admin->can('change-password', $target));
        $this->assertTrue($admin->can('assign-role', $target));
        $this->assertTrue($admin->can('manage-permissions', $target));

        // Инструктор с users.view правит права только внутри своей
        // группы и только теми слагами, которые есть у него самого
        // (это ограничение PermissionScope). Поэтому own-group — да,
        // чужая группа и администратор — нет.
        $this->assertTrue($actor->can('manage-permissions', $target));
        $this->assertFalse($actor->can('manage-permissions', $this->trainee($this->other)));
        $this->assertFalse($actor->can('assign-role', $target));
    }
}
