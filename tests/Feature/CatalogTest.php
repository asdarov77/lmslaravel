<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\Aukstructure;
use App\Models\Category;
use App\Models\Course;
use App\Models\Group;
use App\Models\Group2learning;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Витрина курсов и самостоятельная запись.
 *
 * Ключевое здесь — граница между ПРОСМОТРОМ и МАТЕРИАЛОМ. Витрина
 * показывает описания всех курсов (иначе записаться не на что), но
 * материал открыт только записанному или управляющему. Без этого
 * разделения появление витрины превращалось в обход: /api/course/{id}
 * и манифест висели на одном auth:sanctum, идентификаторы шли подряд,
 * и перебор открывал любой курс за секунды.
 */
class CatalogTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    private Course $course;

    private Course $other;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::factory()->create(['title' => 'Специальность каталога']);

        // Самолёт обязателен: /api/courses скрывает курсы без aircraft_id
        // (иначе в URL материала получался пустой сегмент). Витрина
        // такого фильтра не делает, но для сравнения обоих списков
        // фикстура должна быть одинаковой.
        $aircraft = Aircraft::factory()->create(['title' => 'Тестовый самолёт']);

        $this->course = Course::factory()->create([
            'title' => 'Курс для витрины',
            'aircraft_id' => $aircraft->id,
        ]);
        $this->other = Course::factory()->create([
            'title' => 'Другой курс',
            'aircraft_id' => $aircraft->id,
        ]);

        foreach ([$this->course, $this->other] as $course) {
            $course->categories()->attach($category->id);
            Aukstructure::factory()->count(2)->create(['course_id' => $course->id]);
        }

        $this->group = Group::factory()->create(['groupname' => 'Группа витрины']);
    }

    private function grant(User $user, string ...$slugs): User
    {
        foreach ($slugs as $slug) {
            Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
        }

        $user->givePermissionsTo(...$slugs);
        $user->forgetPermissionCache();

        return $user->fresh();
    }

    private function trainee(?Group $group = null): User
    {
        return User::factory()->create([
            'role' => 'Обучаемый',
            'group_id' => ($group ?? $this->group)->id,
        ]);
    }

    private function enrol(User $user, Course $course): void
    {
        Group2learning::factory()->create([
            'group_id' => $user->group_id,
            'course_id' => $course->id,
        ]);
    }

    // ------------------------------------------------------------ витрина

    public function test_catalog_requires_authentication(): void
    {
        $this->getJson('/api/catalog')->assertUnauthorized();
    }

    public function test_catalog_shows_all_courses_unlike_the_learning_plan(): void
    {
        // Витрина и учебный план отвечают на разные вопросы: здесь —
        // «что доступно», там — «что уже назначено». Смешивать их нельзя.
        $user = $this->trainee();
        $this->enrol($user, $this->course);
        $this->asExistingUser($user);

        $data = $this->getJson('/api/catalog')->assertOk()->json('data');

        $this->assertCount(2, $data['items'], 'витрина показывает все курсы');
        $this->assertSame(2, $data['meta']['all']);
        $this->assertSame(1, $data['meta']['enrolled']);

        $plan = $this->getJson('/api/courses')->assertOk()->json('data');
        $this->assertCount(1, $plan, 'в учебном плане только записанное');
    }

    public function test_catalog_marks_enrollment_state(): void
    {
        $user = $this->trainee();
        $this->enrol($user, $this->course);
        $this->asExistingUser($user);

        $items = collect($this->getJson('/api/catalog')->json('data.items'))
            ->keyBy('id');

        $this->assertTrue($items[$this->course->id]['enrolled']);
        $this->assertFalse($items[$this->other->id]['enrolled']);
    }

    public function test_catalog_does_not_expose_content_paths(): void
    {
        // Витрина — описание товара. Ссылки на материал в ней означали бы,
        // что каталог отдаёт всё, что нужно для чтения курса.
        $this->asExistingUser($this->trainee());

        $item = collect($this->getJson('/api/catalog')->json('data.items'))
            ->firstWhere('id', $this->course->id);

        $this->assertSame('Курс для витрины', $item['title']);
        $this->assertArrayNotHasKey('path', $item);
        $this->assertArrayNotHasKey('links', $item);

        $encoded = json_encode($item, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('manifest', $encoded);
    }

    public function test_catalog_search_and_filters(): void
    {
        $this->asExistingUser($this->trainee());

        // Поиск регистронезависим: в PostgreSQL LIKE чувствителен.
        $found = $this->getJson('/api/catalog?q='.urlencode('для витрины'))->json('data.items');
        $this->assertCount(1, $found);

        $none = $this->getJson('/api/catalog?q='.urlencode('нет такого'))->json('data.items');
        $this->assertSame([], $none);

        $categoryId = $this->course->categories->first()->id;
        $byCategory = $this->getJson('/api/catalog?category_id='.$categoryId)->json('data.items');
        $this->assertCount(2, $byCategory, 'обе карточки в этой специальности');

        // Короткий запрос отклоняется, иначе «а» выгружал бы каталог.
        $this->getJson('/api/catalog?q=а')->assertStatus(422);
    }

    public function test_catalog_mine_filter_returns_only_enrolled(): void
    {
        $user = $this->trainee();
        $this->enrol($user, $this->course);
        $this->asExistingUser($user);

        $items = $this->getJson('/api/catalog?mine=1')->assertOk()->json('data.items');

        $this->assertCount(1, $items);
        $this->assertSame($this->course->id, $items[0]['id']);
    }

    // -------------------------------------------------------------- запись

    public function test_enrolment_creates_record_for_own_group(): void
    {
        $user = $this->trainee();
        $this->asExistingUser($user);

        $this->postJson("/api/catalog/{$this->course->id}/enroll")
            ->assertStatus(201)
            ->assertJsonPath('data.enrolled', true)
            ->assertJsonPath('data.changed', true);

        $this->assertDatabaseHas('group2learnings', [
            'group_id' => $this->group->id,
            'course_id' => $this->course->id,
        ]);
    }

    public function test_enrolment_is_idempotent(): void
    {
        $user = $this->trainee();
        $this->enrol($user, $this->course);
        $this->asExistingUser($user);

        $this->postJson("/api/catalog/{$this->course->id}/enroll")
            ->assertOk()
            ->assertJsonPath('data.changed', false);

        $this->assertSame(
            1,
            Group2learning::where('group_id', $this->group->id)
                ->where('course_id', $this->course->id)
                ->count(),
            'повторная запись не плодит дубли'
        );
    }

    public function test_enrolment_requires_group(): void
    {
        // Пользователь без группы записаться не может: записи просто
        // некуда положить.
        $this->asExistingUser(User::factory()->create(['role' => 'Обучаемый', 'group_id' => null]));

        $this->postJson("/api/catalog/{$this->course->id}/enroll")->assertStatus(422);
    }

    public function test_enrolment_touches_only_own_group(): void
    {
        $other = Group::factory()->create(['groupname' => 'Чужая группа']);
        $colleague = User::factory()->create(['role' => 'Обучаемый', 'group_id' => $other->id]);
        $this->enrol($colleague, $this->course);

        $user = $this->trainee();
        $this->asExistingUser($user);

        $this->deleteJson("/api/catalog/{$this->course->id}/enroll")->assertOk();

        $this->assertDatabaseMissing('group2learnings', [
            'group_id' => $this->group->id,
            'course_id' => $this->course->id,
        ]);
        // Запись соседа по курсу осталась: отписка не должна снимать
        // чужую.
        $this->assertDatabaseHas('group2learnings', [
            'group_id' => $other->id,
            'course_id' => $this->course->id,
        ]);
    }

    public function test_manager_does_not_need_enrolment(): void
    {
        $manager = User::factory()->create(['role' => 'Инструктор', 'group_id' => $this->group->id]);
        $this->grant($manager, 'courses.view', 'courses.manage');
        $this->asExistingUser($manager);

        $this->postJson("/api/catalog/{$this->course->id}/enroll")->assertStatus(422);
        $this->assertDatabaseMissing('group2learnings', [
            'group_id' => $this->group->id,
            'course_id' => $this->course->id,
        ]);
    }

    public function test_enrolment_requires_authentication(): void
    {
        $this->postJson("/api/catalog/{$this->course->id}/enroll")->assertUnauthorized();
    }

    // ------------------------------------------- материал: только записанному

    public function test_material_is_closed_before_enrolment(): void
    {
        $this->asExistingUser($this->trainee());

        // Регресс: /api/course/{id} и манифест висели на одном
        // auth:sanctum, и любой вошедший читал курс по идентификатору.
        $this->getJson("/api/course/{$this->course->id}")->assertStatus(403);
        $this->getJson("/api/coursemanifest/{$this->course->id}")->assertStatus(403);
    }

    public function test_material_opens_after_enrolment(): void
    {
        $user = $this->trainee();
        $this->enrol($user, $this->course);
        $this->asExistingUser($user);

        $this->getJson("/api/course/{$this->course->id}")->assertOk();
        $this->getJson("/api/coursemanifest/{$this->course->id}")->assertOk();
    }

    public function test_manager_opens_material_without_enrolment(): void
    {
        $manager = User::factory()->create(['role' => 'Инструктор', 'group_id' => $this->group->id]);
        $this->grant($manager, 'courses.manage');
        $this->asExistingUser($manager);

        $this->getJson("/api/course/{$this->course->id}")->assertOk();
    }

    public function test_material_requires_authentication(): void
    {
        // Раньше эти маршруты вообще не требовали входа: метаданные
        // любого курса были доступны публично.
        $this->getJson("/api/course/{$this->course->id}")->assertUnauthorized();
        $this->getJson("/api/coursemanifest/{$this->course->id}")->assertUnauthorized();
    }

    public function test_enrolment_of_other_group_does_not_unlock_material(): void
    {
        // Ключевая проверка на границу: сосед записался на курс, а мой
        // пользователь — нет, и материал ему по-прежнему закрыт.
        $other = Group::factory()->create(['groupname' => 'Чужая группа']);
        $colleague = User::factory()->create(['role' => 'Обучаемый', 'group_id' => $other->id]);
        $this->enrol($colleague, $this->course);

        $this->asExistingUser($this->trainee());

        $this->getJson("/api/course/{$this->course->id}")->assertStatus(403);
    }
}
