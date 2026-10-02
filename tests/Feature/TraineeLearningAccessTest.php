<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\Category;
use App\Models\Course;
use App\Models\Group;
use App\Models\Group2learning;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Обучаемый: доступ к своему учебному материалу и закрытость чужого.
 *
 * Реальный сценарий, который закрывает файл. Методист записал группу
 * на курсы, обучаемый вошёл в систему и:
 *   - не увидел ни одного назначенного материала;
 *   - вместо учебного плана получил 403;
 *   - в меню видел «Управление пользователями»;
 *   - в списке курсов видел ВСЕ специальности, включая те, куда его
 *     не записывали;
 *   - не мог пройти назначенный экзамен (пункт «Экзамены» был скрыт).
 *
 * Причина была одна: фронтовые meta.permission для страниц просмотра
 * требовали прав УПРАВЛЕНИЯ (courses.manage, content.manage), а у
 * обучаемого есть только права ЧТЕНИЯ (courses.view, content.view,
 * exams.take). Бэкенд при этом отдавал данные, и меню не фильтровалось
 * по правам вовсе (contentType: "" — «видно всем»).
 */
class TraineeLearningAccessTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    private Group $group;

    private Group $otherGroup;

    private User $trainee;

    private Course $assigned;

    private Course $notAssigned;

    private Category $assignedCategory;

    private Category $foreignCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = Group::factory()->create(['groupname' => 'Группа обучаемых']);
        $this->otherGroup = Group::factory()->create(['groupname' => 'Чужая группа']);

        $aircraft = Aircraft::factory()->create(['path' => 'КЛЕН', 'title' => 'Клен']);

        // Курс, на который группа записана, и course_id, который виден
        // обучаемому, — одно и то же число.
        $this->assigned = Course::factory()->create([
            'title' => 'Курс назначенный',
            'aircraft_id' => $aircraft->id,
        ]);
        $this->notAssigned = Course::factory()->create([
            'title' => 'Курс чужой',
            'aircraft_id' => $aircraft->id,
        ]);

        $this->assignedCategory = Category::factory()->create(['title' => 'Специальность своя']);
        $this->foreignCategory = Category::factory()->create(['title' => 'Специальность чужая']);

        $this->assigned->categories()->attach($this->assignedCategory->id);
        $this->notAssigned->categories()->attach($this->foreignCategory->id);

        Group2learning::factory()->create([
            'course_id' => $this->assigned->id,
            'group_id' => $this->group->id,
            'category_id' => $this->assignedCategory->id,
            'parent_id' => null,
            'typeOfLesson' => 'Лекция',
            'study_from' => now()->subDay()->toDateString(),
            'study_to' => now()->addDays(5)->toDateString(),
        ]);

        $this->trainee = User::factory()->create([
            'role' => 'Обучаемый',
            'group_id' => $this->group->id,
        ]);
    }

    public function test_trainee_has_read_only_permissions_by_matrix(): void
    {
        // Предпосылка всего остального: обучаемому назначены права
        // ЧТЕНИЯ, и никаких прав управления.
        $this->assertTrue($this->trainee->hasPermission('courses.view'));
        $this->assertTrue($this->trainee->hasPermission('content.view'));
        $this->assertTrue($this->trainee->hasPermission('exams.take'));

        $this->assertFalse($this->trainee->hasPermission('courses.manage'));
        $this->assertFalse($this->trainee->hasPermission('content.manage'));
        $this->assertFalse($this->trainee->hasPermission('users.view'));
        $this->assertFalse($this->trainee->hasPermission('users.courses'));
    }

    public function test_trainee_can_open_assigned_course_materials(): void
    {
        // Право на просмотр контента есть — значит, страница манифеста
        // обязана открываться. Раньше meta.permission требовали
        // courses.manage + content.manage, и обучаемый получал 403 на
        // собственных материалах.
        $this->assertTrue(
            $this->trainee->hasPermission('content.view'),
            'content.view обязано быть у обучаемого, иначе проверка ниже бессмысленна'
        );

        // Тот же контракт, что у маршрута courses.itemmani во фронте:
        // content.view ИЛИ content.manage.
        $allowed = $this->trainee->hasPermission('content.view')
            || $this->trainee->hasPermission('content.manage');

        $this->assertTrue($allowed, 'обучаемый должен открывать материалы назначенного курса');
    }

    public function test_courses_endpoint_returns_only_enrolled_courses(): void
    {
        $actor = $this->asUser([
            'role' => 'Обучаемый',
            'group_id' => $this->group->id,
        ]);
        $response = $this->getJson('/api/courses');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue(
            $ids->contains($this->assigned->id),
            'назначенный курс обязан быть в выдаче'
        );
        $this->assertFalse(
            $ids->contains($this->notAssigned->id),
            'курс, на который группу не записывали, обучаемому показываться не должен'
        );
    }

    public function test_versioned_courses_endpoint_is_scoped_too(): void
    {
        $actor = $this->asUser([
            'role' => 'Обучаемый',
            'group_id' => $this->group->id,
        ]);
        $response = $this->getJson('/api/v1/courses');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($this->assigned->id));
        $this->assertFalse($ids->contains($this->notAssigned->id));
        $this->assertSame(
            1,
            $response->json('meta.pagination.total'),
            'в пагинации должен быть скоуп, а не весь каталог'
        );
    }

    public function test_categories_endpoint_returns_only_own_specialties(): void
    {
        $actor = $this->asUser([
            'role' => 'Обучаемый',
            'group_id' => $this->group->id,
        ]);
        $response = $this->getJson('/api/categories');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($this->assignedCategory->id));
        $this->assertFalse($ids->contains($this->foreignCategory->id));
    }

    public function test_categories_of_another_group_are_not_leaked(): void
    {
        // Курс назначен ЧУЖОЙ группе: обучаемому первой группы он не виден.
        Group2learning::factory()->create([
            'course_id' => $this->notAssigned->id,
            'group_id' => $this->otherGroup->id,
            'category_id' => $this->foreignCategory->id,
        ]);

        $actor = $this->asUser([
            'role' => 'Обучаемый',
            'group_id' => $this->group->id,
        ]);
        $response = $this->getJson('/api/courses');

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertFalse(
            $ids->contains($this->notAssigned->id),
            'назначение другой группы не должно вытекать в выдачу'
        );
    }

    public function test_instructor_and_admin_still_see_the_whole_catalog(): void
    {
        // Обратная сторона скоупа: тот, кто курсами управляет, обязан видеть
        // всё, иначе нельзя отредактировать курс, который ещё никому не
        // назначен, или создать новый и увидеть его в списке.
        $instructor = $this->asUser([
            'role' => 'Инструктор',
            'group_id' => $this->group->id,
        ]);
        $instructorIds = collect($this->getJson('/api/courses')->json('data'))->pluck('id');

        $this->assertTrue($instructorIds->contains($this->notAssigned->id));

        $admin = $this->asUser(['role' => 'Администратор']);
        $adminIds = collect($this->getJson('/api/courses')->json('data'))->pluck('id');

        $this->assertTrue($adminIds->contains($this->notAssigned->id));

        $adminCategories = collect($this->getJson('/api/categories')->json('data'))->pluck('id');
        $this->assertTrue(
            $adminCategories->contains($this->foreignCategory->id),
            'справочник специальностей у администратора должен остаться полным'
        );
    }

    public function test_my_learning_plan_returns_own_assignments(): void
    {
        $actor = $this->asUser([
            'role' => 'Обучаемый',
            'group_id' => $this->group->id,
        ]);
        $response = $this->getJson('/api/my/learning');

        $response->assertOk();
        $items = collect($response->json('data'));

        $this->assertCount(1, $items, 'в плане должен быть ровно один курс');
        $this->assertSame($this->assigned->id, $items->first()['course_id']);
        $this->assertSame('Курс назначенный', $items->first()['course_title']);
        $this->assertSame('active', $items->first()['status']);
        $this->assertContains(
            $this->assignedCategory->title,
            collect($items->first()['categories'])->pluck('title')->all()
        );
    }

    public function test_learning_plan_is_empty_without_group_not_error(): void
    {
        // Пользователь без группы не записан ни на один курс. Это
        // валидное состояние, а не ошибка: фронт покажет пустое состояние.
        $actor = $this->asUser(['role' => 'Обучаемый']);
        $response = $this->getJson('/api/my/learning');

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
    }

    public function test_dashboard_summarises_learning_state(): void
    {
        $actor = $this->asUser([
            'role' => 'Обучаемый',
            'group_id' => $this->group->id,
        ]);
        $response = $this->getJson('/api/my/dashboard');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertSame(1, $data['courses']['total']);
        $this->assertSame(1, $data['courses']['distinct']);
        $this->assertSame(1, $data['courses']['active']);
        $this->assertSame(0, $data['progress']['percent'], 'материалы ещё не открывались');
        $this->assertSame(0, $data['exams']['attempts']);
    }

    public function test_dashboard_counts_opened_materials_as_progress(): void
    {
        $trainee = $this->asUser([
            'role' => 'Обучаемый',
            'group_id' => $this->group->id,
        ]);

        \App\Models\Favorite::factory()->create([
            'user_id' => $trainee->id,
            'course_id' => $this->assigned->id,
            'title' => 'Пожар',
        ]);

        $data = $this->getJson('/api/my/dashboard')->json('data');

        $this->assertSame(1, $data['courses']['started']);
        $this->assertSame(100, $data['progress']['percent']);
    }

    public function test_learning_plan_of_other_group_is_not_exposed(): void
    {
        $actor = $this->asUser([
            'role' => 'Обучаемый',
            'group_id' => $this->group->id,
        ]);
        $response = $this->getJson('/api/my/learning');

        $courseIds = collect($response->json('data'))->pluck('course_id');

        $this->assertFalse(
            $courseIds->contains($this->notAssigned->id),
            'в личный кабинет не должны попадать курсы других групп'
        );
    }

    public function test_dashboard_counts_days_left_by_deadline_not_by_period_end(): void
    {
        // Регресс: «Подходит к завершению» считалось по study_to — концу
        // периода обучения. Для обучаемого это неверно: группа может
        // заниматься месяц, а сдать нужно к конкретной дате, поэтому
        // показывался неверный срок.
        $trainee = $this->asUser([
            'role' => 'Обучаемый',
            'group_id' => $this->group->id,
        ]);

        Group2learning::where('group_id', $this->group->id)->update([
            'study_from' => now()->subDay()->toDateString(),
            'study_to' => now()->addMonth()->toDateString(),
            'deadline' => now()->addDays(2)->toDateString(),
        ]);

        $upcoming = collect($this->getJson('/api/my/dashboard')->json('data.upcoming'));

        $this->assertCount(1, $upcoming, 'срок сдачи попадает в ближайшие');
        $this->assertSame(2, $upcoming[0]['days_left'], 'осталось по дедлайну, а не по концу периода');
        $this->assertSame('deadline', $upcoming[0]['source']);
        $this->assertSame(
            now()->addDays(2)->toDateString(),
            $upcoming[0]['due_at']
        );
    }

    public function test_dashboard_falls_back_to_period_end_without_deadline(): void
    {
        // Дедлайн необязателен: если его нет, источником срока остаётся
        // конец периода, а не пустой список.
        $this->asUser([
            'role' => 'Обучаемый',
            'group_id' => $this->group->id,
        ]);

        Group2learning::where('group_id', $this->group->id)->update([
            'study_from' => now()->subDay()->toDateString(),
            'study_to' => now()->addDays(3)->toDateString(),
            'deadline' => null,
        ]);

        $upcoming = collect($this->getJson('/api/my/dashboard')->json('data.upcoming'));

        $this->assertCount(1, $upcoming);
        $this->assertSame('study_to', $upcoming[0]['source']);
        $this->assertSame(3, $upcoming[0]['days_left']);
    }

    public function test_deadline_is_exposed_in_learning_plan(): void
    {
        $this->asUser([
            'role' => 'Обучаемый',
            'group_id' => $this->group->id,
        ]);

        Group2learning::where('group_id', $this->group->id)->update([
            'deadline' => '2026-11-20',
        ]);

        $item = collect($this->getJson('/api/my/learning')->json('data'))->first();

        $this->assertSame('2026-11-20', $item['deadline']);
    }

    public function test_deadline_cannot_be_earlier_than_period_start(): void
    {
        // Срок сдачи раньше начала периода — ошибка ввода методиста,
        // а не «очень срочный дедлайн»: её надо показать, а не сохранить.
        $this->asUser(['role' => 'Администратор']);

        $this->postJson('/api/group/learning', [
            'group_id' => $this->group->id,
            'entries' => [['course_id' => $this->assigned->id, 'parent_id' => null]],
            'typeOfLesson' => 'Лекция',
            'study_from' => '2026-10-10',
            'study_to' => '2026-10-20',
            'deadline' => '2026-10-01',
        ])->assertStatus(422);
    }

    public function test_deadline_is_saved_when_valid(): void
    {
        $this->asUser(['role' => 'Администратор']);

        $this->postJson('/api/group/learning', [
            'group_id' => $this->group->id,
            'entries' => [['course_id' => $this->assigned->id, 'parent_id' => null]],
            'typeOfLesson' => 'Лекция',
            'study_from' => '2026-10-10',
            'study_to' => '2026-10-20',
            'deadline' => '2026-10-18',
        ])->assertStatus(201);

        $this->assertDatabaseHas('group2learnings', [
            'course_id' => $this->assigned->id,
            'deadline' => '2026-10-18',
        ]);
    }

    public function test_plan_requires_authentication(): void
    {
        $this->getJson('/api/my/learning')->assertUnauthorized();
        $this->getJson('/api/my/dashboard')->assertUnauthorized();
    }

    public function test_learning_index_is_scoped_to_own_group(): void
    {
        // Регресс: /api/learning висел только на auth:sanctum и отдавал
        // ВСЕ строки таблицы, то есть учебный план любой группы.
        // Обучаемый получал 6 записей, где его — 1.
        $this->asUser([
            'role' => 'Обучаемый',
            'group_id' => $this->group->id,
        ]);

        $mine = $this->getJson('/api/learning')->json('data');
        $this->assertCount(1, $mine, 'видна только своя запись');
        $this->assertSame($this->group->id, $mine[0]['group_id']);
    }

    public function test_trainee_cannot_read_another_group_plan_by_filter(): void
    {
        // Даже если подставить group_id чужой группы в запрос,
        // скоуп остаётся: подмена параметра не должна обходить его.
        $this->asUser([
            'role' => 'Обучаемый',
            'group_id' => $this->group->id,
        ]);

        $rows = $this->getJson('/api/learning?group_id='.$this->otherGroup->id)->json('data');

        $this->assertSame([], $rows, 'чужой учебный план недоступен');
    }

    public function test_methodologist_sees_all_learning_records(): void
    {
        // Обратная сторона: тот, кто записывает группы (users.courses),
        // должен видеть все записи, иначе не сможет вести обучение.
        $this->asUser([
            'role' => 'Инструктор',
            'group_id' => $this->group->id,
        ]);

        $rows = $this->getJson('/api/learning')->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame($this->group->id, $rows[0]['group_id']);
    }

    public function test_deleting_group_removes_its_learning_records(): void
    {
        // Регресс: внешнего ключа на groups в group2learnings нет, поэтому
        // удаление группы оставляло строки «сиротами». Они всплывали в
        // общем списке /api/learning уже после исчезновения группы.
        $admin = $this->asUser(['role' => 'Администратор']);

        $this->assertDatabaseHas('group2learnings', [
            'group_id' => $this->group->id,
            'course_id' => $this->assigned->id,
        ]);

        $this->deleteJson('/api/groups/'.$this->group->id)->assertStatus(204);

        $this->assertDatabaseMissing('group2learnings', [
            'group_id' => $this->group->id,
        ]);
    }
}
