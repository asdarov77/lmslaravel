<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Permission;
use App\Models\User;
use App\Support\PermissionScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Отдельный раздел управления правами: кто что может назначать.
 *
 * Регрессы, которые закрывает файл:
 *
 *  1. Права синхронизировались без проверки области полномочий —
 *     любой, у кого было users.view, мог выдать себе users.delete.
 *  2. Синхронизация шла ещё и через PATCH /api/user/{id} и POST
 *     /api/v1/users, то есть требовалось users.update/users.create,
 *     а не users.permissions.
 *  3. Никакой валидации входных данных: произвольные значения
 *     permission_id уезжали прямо в pivot-таблицу.
 */
class PermissionsManagementTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    private Group $group;

    private Group $otherGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = Group::factory()->create(['groupname' => 'Группа А']);
        $this->otherGroup = Group::factory()->create(['groupname' => 'Группа Б']);

        // Каталог прав: минимальный набор, на котором проверяем правила.
        config([
            'permissions.permissions' => [
                'users.view' => ['name' => 'Просмотр пользователей', 'group' => 'users'],
                'users.delete' => ['name' => 'Удаление пользователей', 'group' => 'users'],
                'users.permissions' => ['name' => 'Назначение прав', 'group' => 'users'],
                'courses.manage' => ['name' => 'Управление курсами', 'group' => 'courses'],
                'system.maintenance' => ['name' => 'Обслуживание', 'group' => 'system'],
            ],
            'permissions.protected_slugs' => ['system.maintenance'],
        ]);

        foreach (config('permissions.permissions') as $slug => $meta) {
            Permission::create(['name' => $meta['name'], 'slug' => $slug]);
        }
        // Конфиг проверяется на каждом тесте, а каталог в БД общий для
        // всего набора — RefreshDatabase пересоздаёт его сам.
    }

    private function grant(User $user, array $slugs): void
    {
        $user->permissions()->sync(Permission::whereIn('slug', $slugs)->pluck('id')->all());
        $user->forgetPermissionCache();
    }

    /**
     * Инструктор, под которым выполняется запрос.
     *
     * Через asUser(), а не User::factory()->create(): иначе в тесте
     * не оказывается заголовка Authorization и любой запрос уходит
     * в 401 вместо проверяемого 403/201.
     */
    private function instructor(array $attrs = []): User
    {
        $user = $this->asUser([
            'role' => 'Инструктор',
            'group_id' => $this->group->id,
        ] + $attrs);

        $this->grant($user, ['users.view', 'courses.manage']);

        return $user->fresh();
    }

    /** @test */
    public function администратор_видит_полный_каталог_и_всех_пользователей(): void
    {
        $admin = $this->admin();
        // Создаём заранее: пользователь должен существовать на момент запроса.
        $fromOtherGroup = User::factory()->create(['group_id' => $this->otherGroup->id]);

        $catalog = $this->getJson('/api/permissions/catalog')->assertStatus(200)->json('data');

        $slugs = collect($catalog)->flatMap(fn ($group) => $group['permissions'])->pluck('slug');
        $this->assertCount(5, $slugs);
        // Администратору всё выдаваемо, иначе он не настроит систему.
        foreach (collect($catalog)->flatMap(fn ($g) => $g['permissions']) as $permission) {
            $this->assertTrue($permission['assignable'], "{$permission['slug']} должно быть доступно администратору");
        }

        $manageable = $this->getJson('/api/user/manageable')->assertStatus(200)->json('data');
        $ids = collect($manageable)->pluck('id');

        $this->assertTrue($ids->contains($admin->id), 'администратор входит в свой же список');
        $this->assertTrue(
            $ids->contains($fromOtherGroup->id),
            'администратору доступны все группы'
        );
    }

    /** @test */
    public function каталог_сгруппирован_и_содержит_id_из_базы(): void
    {
        $this->admin();

        $catalog = $this->getJson('/api/permissions/catalog')->assertStatus(200)->json('data');

        $groups = collect($catalog)->pluck('name')->all();
        $this->assertContains('Пользователи', $groups);
        $this->assertContains('Курсы и категории', $groups);

        $flat = collect($catalog)->flatMap(fn ($group) => $group['permissions']);
        $this->assertTrue($flat->every(fn ($p) => $p['id'] !== null), 'у каждого права должен быть id для сохранения');
        $this->assertSame(
            Permission::where('slug', 'users.view')->value('id'),
            $flat->firstWhere('slug', 'users.view')['id']
        );
    }

    /** @test */
    public function инструктор_видит_только_свой_набор_прав(): void
    {
        $this->instructor();

        $catalog = $this->getJson('/api/permissions/catalog')->assertStatus(200)->json('data');

        $flat = collect($catalog)->flatMap(fn ($group) => $group['permissions'])->keyBy('slug');

        $this->assertTrue($flat['users.view']['assignable']);
        $this->assertTrue($flat['courses.manage']['assignable']);
        // Права, которых у инструктора нет, выдавать нельзя.
        $this->assertFalse($flat['users.delete']['assignable']);
        // Права о самой системе прав — всегда только администратору.
        $this->assertFalse($flat['users.permissions']['assignable']);
        $this->assertFalse($flat['system.maintenance']['assignable']);
    }

    /** @test */
    public function инструктор_видит_только_пользователей_своей_группы(): void
    {
        $instructor = $this->instructor();

        $mine = User::factory()->create(['role' => 'Обучаемый', 'group_id' => $this->group->id]);
        $foreign = User::factory()->create(['role' => 'Обучаемый', 'group_id' => $this->otherGroup->id]);
        $boss = User::factory()->create(['role' => 'Администратор', 'group_id' => $this->group->id]);

        $manageable = collect(
            $this->getJson('/api/user/manageable')->assertStatus(200)->json('data')
        )->pluck('id');

        $this->assertTrue($manageable->contains($mine->id));
        $this->assertFalse($manageable->contains($foreign->id), 'чужая группа не должна попадать в список');
        $this->assertFalse($manageable->contains($boss->id), 'администратор не должен попадать в список инструктора');
        $this->assertTrue($manageable->contains($instructor->id));
    }

    /** @test */
    public function инструктор_не_может_выдать_право_которого_нет_у_него(): void
    {
        $this->instructor();
        $target = User::factory()->create(['role' => 'Обучаемый', 'group_id' => $this->group->id]);

        $deleteId = Permission::where('slug', 'users.delete')->value('id');

        $this->putJson("/api/user/chperm/{$target->id}", ['permission_id' => [$deleteId]])
            ->assertStatus(403);

        $this->assertCount(0, $target->fresh()->permissions);
    }

    /** @test */
    public function инструктор_не_может_менять_права_администратора(): void
    {
        $this->instructor();
        $boss = User::factory()->create(['role' => 'Администратор', 'group_id' => $this->group->id]);
        $viewId = Permission::where('slug', 'users.view')->value('id');

        $this->putJson("/api/user/chperm/{$boss->id}", ['permission_id' => [$viewId]])
            ->assertStatus(403);
    }

    /** @test */
    public function инструктор_не_может_менять_права_чужой_группы(): void
    {
        $this->instructor();
        $foreign = User::factory()->create(['role' => 'Обучаемый', 'group_id' => $this->otherGroup->id]);
        $viewId = Permission::where('slug', 'users.view')->value('id');

        $this->putJson("/api/user/chperm/{$foreign->id}", ['permission_id' => [$viewId]])
            ->assertStatus(403);
    }

    /** @test */
    public function инструктор_назначает_права_своей_группе(): void
    {
        $this->instructor();
        $target = User::factory()->create(['role' => 'Обучаемый', 'group_id' => $this->group->id]);
        $ids = Permission::whereIn('slug', ['users.view', 'courses.manage'])->pluck('id')->all();

        $this->putJson("/api/user/chperm/{$target->id}", ['permission_id' => $ids])
            ->assertStatus(201);

        $this->assertEqualsCanonicalizing(
            ['users.view', 'courses.manage'],
            $target->fresh()->permissions->pluck('slug')->all()
        );
    }

    /** @test */
    public function администратор_назначает_любые_права_включая_системные(): void
    {
        $this->admin();
        $target = User::factory()->create(['role' => 'Обучаемый', 'group_id' => $this->group->id]);
        $ids = Permission::pluck('id')->all();

        $this->putJson("/api/user/chperm/{$target->id}", ['permission_id' => $ids])
            ->assertStatus(201);

        $this->assertCount(5, $target->fresh()->permissions);
    }

    /** @test */
    public function права_не_проходят_без_проверки_входных_данных(): void
    {
        $this->admin();
        $target = User::factory()->create(['role' => 'Обучаемый', 'group_id' => $this->group->id]);

        // Несуществующее право в bigint уезжает в БД и роняет соединение.
        $this->putJson("/api/user/chperm/{$target->id}", ['permission_id' => [999999]])
            ->assertStatus(422);

        // Отсутствующее поле — тоже ошибка, а не «снести все права».
        $this->putJson("/api/user/chperm/{$target->id}", [])
            ->assertStatus(422);

        // Не массив.
        $this->putJson("/api/user/chperm/{$target->id}", ['permission_id' => 'all'])
            ->assertStatus(422);
    }

    /** @test */
    public function пустой_набор_прав_разрешён_и_снимает_все(): void
    {
        $this->admin();
        $target = User::factory()->create(['role' => 'Обучаемый', 'group_id' => $this->group->id]);
        $this->grant($target, ['users.view']);

        $this->putJson("/api/user/chperm/{$target->id}", ['permission_id' => []])
            ->assertStatus(201);

        $this->assertCount(0, $target->fresh()->permissions);
    }

    /** @test */
    public function права_нельзя_менять_через_редактирование_пользователя(): void
    {
        // Обход, который закрыт: PATCH /api/user/{id} требовал лишь
        // users.update и молча синхронизировал права из тела запроса.
        $this->admin();
        $target = User::factory()->create(['role' => 'Обучаемый', 'group_id' => $this->group->id]);
        $this->grant($target, ['users.view']);

        $deleteId = Permission::where('slug', 'users.delete')->value('id');

        $this->patchJson("/api/user/{$target->id}", [
            'fio' => 'Новое Имя',
            'permission_id' => [$deleteId],
        ])->assertStatus(200);

        $this->assertSame(
            ['users.view'],
            $target->fresh()->permissions->pluck('slug')->all(),
            'PATCH не должен менять права'
        );
    }

    /** @test */
    public function обучаемому_раздел_недоступен(): void
    {
        $this->asUser(['role' => 'Обучаемый']);

        $this->getJson('/api/permissions/catalog')->assertStatus(403);
        $this->getJson('/api/user/manageable')->assertStatus(403);
    }

    /** @test */
    public function без_авторизации_раздел_недоступен(): void
    {
        $this->getJson('/api/permissions/catalog')->assertStatus(401);
        $this->getJson('/api/user/manageable')->assertStatus(401);
    }

    /** @test */
    public function каталог_совпадает_с_backend_каталогом(): void
    {
        // Расхождение конфига и БД — источник тихих поломок: право
        // есть в интерфейсе, но отсутствует в permissions.
        $this->assertSame(
            array_keys((array) config('permissions.permissions')),
            Permission::pluck('slug')->all()
        );
    }

    /** @test */
    public function assignable_slugs_для_инструктора_исключают_системные(): void
    {
        $instructor = $this->instructor();

        $assignable = PermissionScope::assignableSlugs($instructor);

        $this->assertIsArray($assignable);
        $this->assertContains('users.view', $assignable);
        $this->assertNotContains('users.permissions', $assignable);
        $this->assertNotContains('system.maintenance', $assignable);
        $this->assertNull(PermissionScope::assignableSlugs($this->admin()), 'администратору без ограничений');
    }
}