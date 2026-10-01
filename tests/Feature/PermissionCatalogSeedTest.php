<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Каталог прав должен наполняться при обычной установке.
 *
 * Регрессия, которую закрывает тест: PermissionSeeder создавал ровно три
 * legacy-права (manage-users / create-tasks / manage-course). RoleSeeder
 * назначает ролям права по slug'ам из config('permissions.role_matrix'),
 * поэтому `content.manage`, `courses.manage`, `users.manage`,
 * `system.maintenance` и остальные права в БД отсутствовали,
 * resolvePermissionSlugs() возвращал [], и после `migrate:fresh --seed`
 * система оставалась без прав. Меню и кнопки, скрываемые по правам,
 * не отрисовывались.
 */
class PermissionCatalogSeedTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function сидер_прав_наполняет_бд_полным_каталогом_из_конфига(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $catalog = array_keys((array) config('permissions.permissions', []));

        $this->assertNotEmpty($catalog, 'Каталог прав в конфиге не должен быть пуст');

        foreach ($catalog as $slug) {
            $this->assertDatabaseHas('permissions', ['slug' => $slug]);
        }
    }

    /** @test */
    public function сидер_прав_идемпотентен(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $count = Permission::count();

        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->assertSame($count, Permission::count());
    }

    /** @test */
    public function сидер_прав_сохраняет_права_которых_нет_в_каталоге(): void
    {
        // Legacy-право может быть привязано к пользователю через
        // permissions_users; удалять его нельзя.
        Permission::create(['name' => 'Старое право', 'slug' => 'legacy-perm']);

        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->assertDatabaseHas('permissions', ['slug' => 'legacy-perm']);
    }

    /** @test */
    public function после_полного_seeding_у_системных_ролей_есть_права_из_матрицы(): void
    {
        // Именно этот сценарий даёт рабочую базу после migrate:fresh --seed.
        $this->seed(DatabaseSeeder::class);

        $matrix = (array) config('permissions.role_matrix', []);

        foreach (['instructor', 'trainee'] as $slug) {
            $role = Role::where('slug', $slug)->firstOrFail();
            $roleName = $role->rolename;
            $expected = Permission::whereIn('slug', (array) ($matrix[$roleName] ?? []))->count();

            $this->assertSame(
                $expected,
                $role->permissions()->count(),
                "У роли «{$roleName}» должно быть {$expected} прав"
            );
            $this->assertGreaterThan(0, $expected, "Для роли «{$roleName}» матрица прав пуста");
        }

        $admin = Role::where('slug', 'admin')->firstOrFail();
        $this->assertSame(Permission::count(), $admin->permissions()->count());
    }

    /** @test */
    public function после_полного_seeding_право_очистки_базы_существует(): void
    {
        // POST /api/clear-database защищён permission:system.maintenance.
        // Без этого права кнопка очистки недоступна даже администратору,
        // который проходит проверку только по фолбэку users.role.
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('permissions', ['slug' => 'system.maintenance']);
    }

    /** @test */
    public function полный_seeding_не_ломает_связи_пользователей(): void
    {
        $this->seed(DatabaseSeeder::class);

        // DatabaseSeeder вставляет связи permissions_users жёстко
        // (user_id = 1, permission_id = 1/2) — они должны остаться валидными.
        $orphans = DB::table('permissions_users as pu')
            ->leftJoin('permissions as p', 'p.id', '=', 'pu.permission_id')
            ->whereNull('p.id')
            ->count();

        $this->assertSame(0, $orphans, 'permissions_users ссылается на несуществующие права');
    }

    /** @test */
    public function сидер_прав_не_сбрасывает_связи_ролей(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $before = DB::table('permissions_roles')->count();
        $this->assertGreaterThan(0, $before);

        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->assertSame($before, DB::table('permissions_roles')->count());
    }
}