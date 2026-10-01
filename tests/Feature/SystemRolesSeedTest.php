<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Системные роли должны существовать сразу после установки.
 *
 * Регрессия: DatabaseSeeder НЕ вызывал RoleSeeder, поэтому на чистой базе
 * не было ни Администратора, ни Инструктора, ни Обучаемого, а
 * `artisan permissions:sync` рапортовал «роль не найдена — пропущена».
 * Сам RoleSeeder дополнительно был неидемпотентным (new Role()->save() с
 * русскими slug'ами) и не назначал прав.
 */
class SystemRolesSeedTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function seeder_создаёт_все_три_системные_роли(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertDatabaseHas('roles', ['slug' => 'admin', 'rolename' => 'Администратор']);
        $this->assertDatabaseHas('roles', ['slug' => 'instructor', 'rolename' => 'Инструктор']);
        $this->assertDatabaseHas('roles', ['slug' => 'trainee', 'rolename' => 'Обучаемый']);
    }

    /** @test */
    public function seeder_назначает_права_из_role_matrix(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->artisan('permissions:sync')->assertSuccessful();
        $this->seed(RoleSeeder::class);

        $instructor = Role::where('slug', 'instructor')->firstOrFail();
        $trainee = Role::where('slug', 'trainee')->firstOrFail();
        $admin = Role::where('slug', 'admin')->firstOrFail();

        $matrix = config('permissions.role_matrix');

        $expectedInstructor = Permission::whereIn('slug', $matrix['Инструктор'])->count();
        $expectedTrainee = Permission::whereIn('slug', $matrix['Обучаемый'])->count();

        $this->assertSame($expectedInstructor, $instructor->permissions()->count());
        $this->assertSame($expectedTrainee, $trainee->permissions()->count());

        // Администратор — суперпользователь, но получает весь каталог.
        $this->assertSame(Permission::count(), $admin->permissions()->count());
        $this->assertGreaterThan($expectedInstructor, $admin->permissions()->count());
    }

    /** @test */
    public function повторный_запуск_не_создаёт_дубликаты(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $rolesBefore = Role::count();
        $linksBefore = \Illuminate\Support\Facades\DB::table('permissions_roles')->count();

        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->assertSame($rolesBefore, Role::count());
        $this->assertSame(
            $linksBefore,
            \Illuminate\Support\Facades\DB::table('permissions_roles')->count()
        );
    }

    /** @test */
    public function seeder_переиспользует_legacy_роль_с_русским_slug(): void
    {
        // Так выглядела база, созданная старым RoleSeeder: slug = 'Инструктор'.
        $legacy = Role::create(['rolename' => 'Инструктор', 'slug' => 'Инструктор']);

        $this->seed(RoleSeeder::class);

        $this->assertSame(1, Role::where('rolename', 'Инструктор')->count());

        $migrated = Role::where('slug', 'instructor')->first();
        $this->assertNotNull($migrated);
        $this->assertSame($legacy->id, $migrated->id);
    }

    /** @test */
    public function database_seeder_создаёт_системные_роли(): void
    {
        // Полный DatabaseSeeder тянет фабрики и legacy-вставки, поэтому
        // проверяем именно порядок вызовов: RoleSeeder обязан быть в списке.
        $source = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertMatchesRegularExpression(
            '/PermissionSeeder::class,\s*RoleSeeder::class/s',
            $source,
            'RoleSeeder должен вызываться сразу после PermissionSeeder'
        );
    }

    /** @test */
    public function роли_из_конфига_совпадают_с_user_role_aliases(): void
    {
        $this->assertSame(
            array_keys(User::ROLE_ALIASES),
            array_keys((array) config('permissions.system_roles'))
        );
    }

    /** @test */
    public function sync_создаёт_отсутствующие_системные_роли(): void
    {
        $this->artisan('permissions:sync')->assertSuccessful();

        foreach (['admin', 'instructor', 'trainee'] as $slug) {
            $this->assertDatabaseHas('roles', ['slug' => $slug]);
        }
    }
}
