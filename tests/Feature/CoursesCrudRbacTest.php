<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\Category;
use App\Models\Course;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Полный CRUD /api/v1/courses + RBAC (студент / преподаватель / администратор).
 *
 * Покрывает то, чего не касались существующие CourseTest/CatalogTest:
 *  - middleware `permission:courses.view|manage,content.manage` на каждом глаголе;
 *  - courseRules(): name/title алиасы, duration min:0, closes_at after_or_equal,
 *    exists:aircrafts / exists:categories;
 *  - фактические изменения в PostgreSQL после store/update/destroy;
 *  - сброс кэша дерева плеера при update/destroy (регрессия «битого кадра»);
 *  - whereNumber('course'): строковый id не доходит до контроллера.
 *
 * @see \App\Http\Controllers\CourseController
 */
class CoursesCrudRbacTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticatesApi;

    /* ─────────────────────────── фикстуры ─────────────────────────── */

    /** Выдать пользователю право напрямую (permissions_users). */
    private function grant(User $user, string ...$slugs): void
    {
        foreach ($slugs as $slug) {
            $perm = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            DB::table('permissions_users')->insertOrIgnore([
                'user_id' => $user->id,
                'permission_id' => $perm->id,
            ]);
        }

        // Сбрасываем in-memory кэш slug'ов traits, если пользователь уже загружен.
        $user->unsetRelation('permissions');
    }

    /** Преподаватель (Инструктор) с courses.manage — пишет каталог. */
    private function manager(): User
    {
        $user = User::factory()->create(['role' => 'Инструктор']);
        $this->grant($user, 'courses.manage');

        return $user;
    }

    /** Наблюдатель: только чтение каталога. */
    private function viewer(): User
    {
        $user = User::factory()->create(['role' => 'Инструктор']);
        $this->grant($user, 'courses.view');

        return $user;
    }

    /** Обучаемый без прав каталога. */
    private function trainee(): User
    {
        return User::factory()->create(['role' => 'Обучаемый']);
    }

    /* ═════════════════ CREATE: POST /api/v1/courses ═════════════════ */

    public function test_anonymous_cannot_create_course(): void
    {
        $this->postJson('/api/v1/courses', ['title' => 'X'])->assertStatus(401);
        $this->assertSame(0, Course::count());
    }

    public function test_trainee_without_permission_is_forbidden_and_nothing_saved(): void
    {
        $this->asExistingUser($this->trainee());

        $this->postJson('/api/v1/courses', ['title' => 'Курс студента'])->assertStatus(403);

        // Граничный случай: отказ на middleware — в БД не должно быть записи
        // (валидация/сохранение не выполняются вовсе).
        $this->assertSame(0, Course::count());
    }

    public function test_viewer_role_cannot_write_only_manage_allows_store(): void
    {
        $this->asExistingUser($this->viewer());
        $this->postJson('/api/v1/courses', ['title' => 'Только view'])->assertStatus(403);

        $this->asExistingUser($this->manager());
        $this->postJson('/api/v1/courses', ['title' => 'Есть manage'])->assertCreated();

        $this->assertSame(1, Course::count());
    }

    public function test_superadmin_bypasses_permission_middleware(): void
    {
        // CheckUserPermission: isSuperAdmin() → next() без проверки slug'ов.
        $this->admin();

        $this->postJson('/api/v1/courses', ['title' => 'Админский курс'])->assertCreated();
        $this->assertDatabaseHas('courses', ['title' => 'Админский курс']);
    }

    public function test_store_persists_defaults_and_attributes_in_postgres(): void
    {
        $this->asExistingUser($this->manager());
        $category = Category::factory()->create();
        $aircraft = Aircraft::factory()->create();

        $response = $this->postJson('/api/v1/courses', [
            'title' => 'Наземная подготовка',
            'description' => 'Описание курса',
            'duration' => 40,
            'category_id' => $category->id,
            'aircraft_id' => $aircraft->id,
            'opens_at' => '2026-11-01 08:00:00',
            'closes_at' => '2026-12-01 08:00:00',
        ])->assertCreated();

        $id = $response->json('id');
        $row = DB::table('courses')->where('id', $id)->first();
        $this->assertSame('Наземная подготовка', $row->title);
        $this->assertSame(40, (int) $row->duration);
        // Дефолты контроллера: visible ?? true, status ?? 'active'.
        $this->assertTrue((bool) $row->visible);
        $this->assertSame('active', $row->status);
        // Синхронизация pivot-таблицы categories.
        $this->assertDatabaseHas('category_course', [
            'course_id' => $id,
            'category_id' => $category->id,
        ]);
    }

    public function test_name_is_alias_of_title_on_store(): void
    {
        $this->asExistingUser($this->manager());

        $this->postJson('/api/v1/courses', ['name' => 'Через name'])
            ->assertCreated()
            ->assertJsonPath('title', 'Через name');

        $this->assertDatabaseHas('courses', ['title' => 'Через name']);
    }

    /* ──────────────── валидация входящих запросов (store) ──────────────── */

    public function test_store_requires_title_or_name(): void
    {
        $this->asExistingUser($this->manager());

        // Пустое тело / пустые поля — required_without друг для друга.
        $this->postJson('/api/v1/courses', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'name']);

        $this->postJson('/api/v1/courses', ['title' => '', 'name' => ''])
            ->assertStatus(422);

        $this->assertSame(0, Course::count());
    }

    public function test_store_rejects_negative_duration_and_non_integer(): void
    {
        $this->asExistingUser($this->manager());

        $this->postJson('/api/v1/courses', ['title' => 'T', 'duration' => -5])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['duration']);

        $this->postJson('/api/v1/courses', ['title' => 'T', 'duration' => 'abc'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['duration']);
    }

    public function test_store_rejects_closing_before_opening(): void
    {
        $this->asExistingUser($this->manager());

        $this->postJson('/api/v1/courses', [
            'title' => 'Окно записи',
            'opens_at' => '2026-12-01 00:00:00',
            'closes_at' => '2026-11-01 00:00:00',
        ])->assertStatus(422)->assertJsonValidationErrors(['closes_at']);

        // after_or_equal: одинаковые даты допустимы.
        $this->postJson('/api/v1/courses', [
            'title' => 'Однодневный',
            'opens_at' => '2026-11-01 00:00:00',
            'closes_at' => '2026-11-01 00:00:00',
        ])->assertCreated();
    }

    public function test_store_rejects_forged_foreign_ids(): void
    {
        // Поддельные ID: exists-правила должны ловить несуществующие FK,
        // иначе повисшие ссылки позже роняют show() с eager-load.
        $this->asExistingUser($this->manager());

        $this->postJson('/api/v1/courses', ['title' => 'T', 'aircraft_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['aircraft_id']);

        $this->postJson('/api/v1/courses', ['title' => 'T', 'category_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_store_rejects_bad_types_for_boolean_and_date(): void
    {
        $this->asExistingUser($this->manager());

        $this->postJson('/api/v1/courses', ['title' => 'T', 'visible' => 'not-a-bool'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['visible']);

        $this->postJson('/api/v1/courses', ['title' => 'T', 'opens_at' => 'завтра'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['opens_at']);
    }

    /* ═════════════════ READ: GET index/show ═════════════════ */

    public function test_show_requires_view_permission_and_returns_course(): void
    {
        $course = Course::factory()->create(['title' => 'Читаемый']);

        $this->asExistingUser($this->trainee());
        $this->getJson("/api/v1/courses/{$course->id}")->assertStatus(403);

        $this->asExistingUser($this->viewer());
        $this->getJson("/api/v1/courses/{$course->id}")
            ->assertOk()
            ->assertJsonPath('title', 'Читаемый');
    }

    public function test_show_nonexistent_course_returns_404_not_500(): void
    {
        $this->asExistingUser($this->manager());
        $this->getJson('/api/v1/courses/999999')->assertStatus(404);
    }

    public function test_show_rejects_non_numeric_id_at_routing_layer(): void
    {
        // whereNumber('course'): SQL-инъекция/мусор в URL отсекается роутером.
        $this->asExistingUser($this->manager());
        $this->getJson('/api/v1/courses/1%20OR%201=1')->assertStatus(404);
        $this->getJson('/api/v1/courses/abc')->assertStatus(404);
    }

    public function test_index_lists_courses_for_viewer_and_denies_trainee(): void
    {
        Course::factory()->count(3)->create();

        $this->asExistingUser($this->viewer());
        $this->getJson('/api/v1/courses')->assertOk();

        $this->asExistingUser($this->trainee());
        $this->getJson('/api/v1/courses')->assertStatus(403);
    }

    /* ═════════════════ UPDATE: PUT/PATCH /api/v1/courses/{id} ═════════════════ */

    public function test_update_changes_row_and_does_not_touch_unsent_columns(): void
    {
        $this->asExistingUser($this->manager());
        $course = Course::factory()->create(['title' => 'Старое имя', 'duration' => 10]);

        // PATCH partial: прислали только title — duration обязан остаться.
        $this->patchJson("/api/v1/courses/{$course->id}", ['title' => 'Новое имя'])
            ->assertOk()
            ->assertJsonPath('title', 'Новое имя');

        $fresh = DB::table('courses')->where('id', $course->id)->first();
        $this->assertSame('Новое имя', $fresh->title);
        $this->assertSame(10, (int) $fresh->duration);
    }

    public function test_update_validates_same_rules_as_store(): void
    {
        $this->asExistingUser($this->manager());
        $course = Course::factory()->create();

        $this->putJson("/api/v1/courses/{$course->id}", ['duration' => -1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['duration']);

        $this->patchJson("/api/v1/courses/{$course->id}", ['aircraft_id' => 424242])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['aircraft_id']);

        // Неудачная валидация ничего не изменила в БД.
        $this->assertSame($course->title, DB::table('courses')->where('id', $course->id)->value('title'));
    }

    public function test_trainee_cannot_update_course_of_others(): void
    {
        $course = Course::factory()->create();
        $this->asExistingUser($this->trainee());

        $this->patchJson("/api/v1/courses/{$course->id}", ['title' => 'Взлом'])
            ->assertStatus(403);

        $this->assertNotSame('Взлом', DB::table('courses')->where('id', $course->id)->value('title'));
    }

    public function test_update_resets_player_tree_cache(): void
    {
        // Регрессия: без invalidateTreeCache плеер до TTL отдавал content_url
        // со старым path → «битый кадр» 404 после переноса материалов курса.
        $this->asExistingUser($this->manager());
        $course = Course::factory()->create();

        // Прогреваем кэш дерева чтением плеера.
        $this->getJson("/api/course/{$course->id}/player")->assertOk();
        $cacheKey = 'course-player-tree-' . $course->id;
        // Ключ может различаться по префиксу — проверяем поведение, а не ключ:
        // после обновления пути повторное чтение плеера обязано отдать новый path.
        $before = $this->getJson("/api/course/{$course->id}/player")->json();

        $this->patchJson("/api/v1/courses/{$course->id}", ['path' => 'новый-путь'])
            ->assertOk();

        $after = $this->getJson("/api/course/{$course->id}/player")->json();
        $this->assertNotSame(
            json_encode($before['data']['nodes'] ?? null),
            json_encode($after['data']['nodes'] ?? null) . '',
            'После смены path кэш дерева должен быть сброшен (content_url пересобран).'
        );
    }

    /* ═════════════════ DELETE: destroy ═════════════════ */

    public function test_destroy_removes_row_and_relations(): void
    {
        $this->asExistingUser($this->manager());
        $course = Course::factory()->create();
        $category = Category::factory()->create();
        $course->categories()->sync([$category->id]);

        $this->deleteJson("/api/v1/courses/{$course->id}")->assertOk();

        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
        // Pivot обязан очиститься (FK cascade или явный detach) — иначе
        // getCatCoursesId покажет мёртвый курс.
        $this->assertDatabaseMissing('category_course', ['course_id' => $course->id]);
    }

    public function test_trainee_cannot_delete_and_row_survives(): void
    {
        $course = Course::factory()->create();
        $this->asExistingUser($this->trainee());

        $this->deleteJson("/api/v1/courses/{$course->id}")->assertStatus(403);
        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }

    public function test_destroy_twice_second_call_404(): void
    {
        $this->asExistingUser($this->manager());
        $course = Course::factory()->create();

        $this->deleteJson("/api/v1/courses/{$course->id}")->assertOk();
        $this->deleteJson("/api/v1/courses/{$course->id}")->assertStatus(404);
    }

    /* ──────────────── контент-менеджер как альтернативное право ──────────────── */

    public function test_content_manage_grants_write_like_courses_manage(): void
    {
        // routes/api.php: запись разрешена courses.manage ИЛИ content.manage.
        $user = User::factory()->create(['role' => 'Инструктор']);
        $this->grant($user, 'content.manage');
        $this->asExistingUser($user);

        $this->postJson('/api/v1/courses', ['title' => 'Через content.manage'])->assertCreated();
    }

    public function test_permission_via_role_assignment_works(): void
    {
        // Право через роль (permissions_roles), а не прямое назначение:
        // hasPermission обязан учитывать оба источника.
        $role = \App\Models\Role::factory()->create();
        $perm = Permission::firstOrCreate(['slug' => 'courses.manage'], ['name' => 'courses.manage']);
        $role->permissions()->attach($perm->id);

        $user = User::factory()->create(['role' => 'Инструктор']);
        $user->roles()->attach($role->id);

        $this->asExistingUser($user);
        $this->postJson('/api/v1/courses', ['title' => 'Через роль'])->assertCreated();
    }
}
