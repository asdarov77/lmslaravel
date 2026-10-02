<?php

namespace Tests\Feature;

use App\Models\Aukstructure;
use App\Models\Category;
use App\Models\Course;
use App\Models\Group;
use App\Models\Group2learning;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Календарь учебного процесса: GET /api/calendar.
 *
 * Что закрывает файл.
 *
 *  1. Календарь отдавал периоды обучения всем одинаково. Обучаемый видел
 *     расписание чужих групп — учебные данные других людей. Область
 *     видимости задаётся сервером: своя группа для обучаемого, все группы
 *     для методиста (users.courses). Попытка подменить group_id в запросе
 *     не должна обходить скоуп.
 *
 *  2. Фильтр по состоянию молча не работал: переменная $validated не была
 *     захвачена замыканием map(), условие проверялось по undefined, и
 *     ?status=completed отдавал все периоды вместо нуля. Тест фиксирует
 *     именно этот случай: он проходил «успешно» при сломанной логике.
 *
 *  3. Границы окна просмотра должны браться по ПЕРЕСЕЧЕНИЮ с периодом, а
 *     не по полному вхождению внутрь. Период, начавшийся до from и
 *     продолжающийся после него, обязан быть виден — иначе в календаре
 *     были бы дыры.
 *
 *  4. Формат события — формат FullCalendar: у событий «день» end не
 *     входит в диапазон, поэтомуstudy_to + 1 день. Без этого последний
 *     день обучения не отображался.
 */
class CalendarFeedTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    private Group $group;

    private Group $otherGroup;

    private Course $course;

    private Course $otherCourse;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = Group::factory()->create(['groupname' => 'Группа А']);
        $this->otherGroup = Group::factory()->create(['groupname' => 'Группа Б']);

        $this->course = Course::factory()->create(['title' => 'Курс А']);
        $this->otherCourse = Course::factory()->create(['title' => 'Курс Б']);
        $this->category = Category::factory()->create(['title' => 'Специальность А']);

        $this->course->categories()->attach($this->category->id);
    }

    private function enroll(array $attrs = []): Group2learning
    {
        return Group2learning::factory()->create(array_merge([
            'course_id' => $this->course->id,
            'group_id' => $this->group->id,
            'category_id' => $this->category->id,
            'parent_id' => null,
            'study_from' => '2026-10-02',
            'study_to' => '2026-10-11',
        ], $attrs));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/calendar')->assertUnauthorized();
    }

    public function test_returns_event_in_fullcalendar_shape(): void
    {
        $row = $this->enroll();
        $this->asUser(['role' => 'Администратор']);

        $response = $this->getJson('/api/calendar')->assertOk();

        $events = $response->json('data.events');
        $this->assertCount(1, $events);

        $event = $events[0];
        $this->assertSame('learning-'.$row->id, $event['id']);
        $this->assertSame('Курс А', $event['title']);
        $this->assertTrue($event['allDay']);
        $this->assertSame('2026-10-02', $event['start']);
        // end в FullCalendar не включается: последний день обучения
        // должен попасть в диапазон, поэтому +1 день.
        $this->assertSame('2026-10-12', $event['end']);

        $props = $event['extendedProps'];
        $this->assertSame($this->group->id, $props['group_id']);
        $this->assertSame('Группа А', $props['group_name']);
        $this->assertSame('2026-10-02', $props['study_from']);
        $this->assertSame('2026-10-11', $props['study_to']);
        $this->assertSame('active', $props['status']);
        $this->assertSame(
            [$this->category->title],
            collect($props['categories'])->pluck('title')->all()
        );
    }

    public function test_module_title_is_resolved(): void
    {
        $module = Aukstructure::factory()->create([
            'course_id' => $this->course->id,
            'title' => 'Пожар',
        ]);

        $this->enroll(['parent_id' => $module->id]);
        $this->asUser(['role' => 'Администратор']);

        $events = $this->getJson('/api/calendar')->json('data.events');

        $this->assertSame('Пожар', $events[0]['extendedProps']['module_title']);
    }

    public function test_trainee_sees_only_own_group_periods(): void
    {
        $this->enroll(['group_id' => $this->group->id]);
        $this->enroll(['group_id' => $this->otherGroup->id, 'course_id' => $this->otherCourse->id]);

        $this->asUser(['role' => 'Обучаемый', 'group_id' => $this->group->id]);

        $events = $this->getJson('/api/calendar')->json('data.events');

        $this->assertCount(1, $events, 'виден только период своей группы');
        $this->assertSame($this->group->id, $events[0]['extendedProps']['group_id']);
    }

    public function test_trainee_cannot_request_another_group_by_filter(): void
    {
        $this->enroll(['group_id' => $this->group->id]);
        $this->enroll(['group_id' => $this->otherGroup->id, 'course_id' => $this->otherCourse->id]);

        $this->asUser(['role' => 'Обучаемый', 'group_id' => $this->group->id]);

        $events = $this->getJson('/api/calendar?group_id='.$this->otherGroup->id)->json('data.events');

        $this->assertCount(1, $events);
        $this->assertSame($this->group->id, $events[0]['extendedProps']['group_id']);
    }

    public function test_trainee_filter_options_expose_only_own_group(): void
    {
        $this->asUser(['role' => 'Обучаемый', 'group_id' => $this->group->id]);

        $filters = $this->getJson('/api/calendar')->json('data.filters');

        $this->assertSame([$this->group->id], collect($filters['groups'])->pluck('id')->all());
    }

    public function test_user_with_enrolment_permission_sees_all_groups(): void
    {
        // Записывать группы на курсы (users.courses) может не всякий
        // инструктор — в role_matrix это право есть только у
        // администратора. Кто им обладает, видит расписание всех групп.
        $this->enroll(['group_id' => $this->group->id]);
        $this->enroll(['group_id' => $this->otherGroup->id, 'course_id' => $this->otherCourse->id]);

        $actor = $this->asUser(['role' => 'Инструктор', 'group_id' => $this->group->id]);
        $this->grant($actor, 'users.courses');

        $events = $this->getJson('/api/calendar')->json('data.events');

        $this->assertCount(2, $events);
    }

    public function test_instructor_without_enrolment_permission_sees_only_own_group(): void
    {
        // У инструктора в role_matrix нет users.courses, поэтому в календаре
        // он видит периоды своей группы, а не всех.
        $this->enroll(['group_id' => $this->group->id]);
        $this->enroll(['group_id' => $this->otherGroup->id, 'course_id' => $this->otherCourse->id]);

        $this->asUser(['role' => 'Инструктор', 'group_id' => $this->group->id]);

        $events = $this->getJson('/api/calendar')->json('data.events');

        $this->assertCount(1, $events);
        $this->assertSame($this->group->id, $events[0]['extendedProps']['group_id']);
    }

    /** Выдаёт пользователю право напрямую, минуя матрицу роли. */
    private function grant(User $user, string $slug): void
    {
        $permission = \App\Models\Permission::firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug]
        );

        $user->permissions()->syncWithoutDetaching([$permission->id]);
        $user->unsetRelation('permissions');
    }

    public function test_filter_by_course_and_category(): void
    {
        // Фильтр по специальности идёт по колонке самой записи
        // (в какой специальности группа записана), а не по связям курса.
        $otherCategory = Category::factory()->create(['title' => 'Специальность Б']);

        $this->enroll();
        $this->enroll([
            'course_id' => $this->otherCourse->id,
            'category_id' => $otherCategory->id,
        ]);

        $this->asUser(['role' => 'Администратор']);

        $this->assertCount(1, $this->getJson('/api/calendar?course_id='.$this->course->id)->json('data.events'));
        $this->assertCount(1, $this->getJson('/api/calendar?category_id='.$this->category->id)->json('data.events'));

        // Курс Б записан по специальности Б, поэтому комбинация
        // «курс Б + специальность А» не должна ничего находить.
        $this->assertCount(0, $this->getJson(
            '/api/calendar?course_id='.$this->otherCourse->id.'&category_id='.$this->category->id
        )->json('data.events'));
    }

    public function test_status_filter_actually_filters(): void
    {
        // Регресс: $validated не была захвачена замыканием map(), условие
        // проверялось по undefined, и любой статус отдавал все периоды.
        $this->enroll([
            'study_from' => now()->subDay()->toDateString(),
            'study_to' => now()->addDays(5)->toDateString(),
        ]);
        $this->enroll([
            'study_from' => now()->subMonth()->toDateString(),
            'study_to' => now()->subWeek()->toDateString(),
        ]);
        $this->enroll([
            'study_from' => now()->addMonth()->toDateString(),
            'study_to' => now()->addMonths(2)->toDateString(),
        ]);

        $this->asUser(['role' => 'Администратор']);

        $this->assertCount(1, $this->getJson('/api/calendar?status=active')->json('data.events'));
        $this->assertCount(1, $this->getJson('/api/calendar?status=completed')->json('data.events'));
        $this->assertCount(1, $this->getJson('/api/calendar?status=planned')->json('data.events'));
        $this->assertCount(3, $this->getJson('/api/calendar')->json('data.events'));
    }

    public function test_window_filter_uses_intersection_not_containment(): void
    {
        // Период 02.10–11.10 пересекается с окном 10.10–12.10, хотя внутрь
        // окна целиком не входит. По «полному вхождению» его бы потеряли.
        $this->enroll();

        $this->asUser(['role' => 'Администратор']);

        $this->assertCount(
            1,
            $this->getJson('/api/calendar?from=2026-10-10&to=2026-10-12')->json('data.events'),
            'период, пересекающий окно просмотра, обязан быть виден'
        );

        $this->assertCount(
            0,
            $this->getJson('/api/calendar?from=2026-11-01&to=2026-11-30')->json('data.events')
        );
    }

    public function test_statuses_are_computed_from_today(): void
    {
        $this->enroll([
            'study_from' => now()->subDay()->toDateString(),
            'study_to' => now()->addDays(5)->toDateString(),
        ]);

        $this->asUser(['role' => 'Администратор']);

        $this->assertSame(
            'active',
            $this->getJson('/api/calendar')->json('data.events.0.extendedProps.status')
        );
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->asUser(['role' => 'Администратор']);

        $this->getJson('/api/calendar?status=xyz')->assertStatus(422);
        $this->getJson('/api/calendar?course_id=abc')->assertStatus(422);
        $this->getJson('/api/calendar?from=2026-12-01&to=2026-11-01')->assertStatus(422);
    }

    public function test_filter_options_only_offer_courses_present_in_periods(): void
    {
        // «Все курсы системы» в фильтре вводит в заблуждение: курсов, по
        // которым нет ни одного периода, в календаре всё равно не будет.
        Course::factory()->create(['title' => 'Курс без записей']);

        $this->enroll();
        $this->asUser(['role' => 'Администратор']);

        $courses = collect($this->getJson('/api/calendar')->json('data.filters.courses'));

        $this->assertTrue($courses->contains('id', $this->course->id));
        $this->assertFalse($courses->contains('title', 'Курс без записей'));
    }

    public function test_total_is_reported_in_meta(): void
    {
        $this->enroll();
        $this->enroll(['course_id' => $this->otherCourse->id]);
        $this->asUser(['role' => 'Администратор']);

        $this->assertSame(2, $this->getJson('/api/calendar')->json('meta.total'));
    }

    public function test_deadline_is_separate_event(): void
    {
        // Дедлайн показывается ОТДЕЛЬНЫМ событием, а не украшением полосы
        // периода: в месячном и недельном виде полосы сливаются, и срок
        // сдачи не найти взглядом.
        $this->enroll(['deadline' => '2026-10-20']);
        $this->asUser(['role' => 'Администратор']);

        $events = collect($this->getJson('/api/calendar')->json('data.events'));

        $this->assertCount(2, $events, 'период и дедлайн — два события');

        $deadline = $events->firstWhere('extendedProps.kind', 'deadline');
        $this->assertNotNull($deadline, 'метка срока сдачи присутствует');
        $this->assertSame('2026-10-20', $deadline['start']);
        $this->assertArrayNotHasKey(
            'end',
            $deadline,
            'у однодневного события end не указывается: в FullCalendar он не включается'
        );
        // У этой записи parent_id = null, модуля нет, поэтому в подписи
        // только курс.
        $this->assertStringContainsString('Курс А', $deadline['title']);
        $this->assertNull($deadline['extendedProps']['module_title']);
        $this->assertSame($this->group->id, $deadline['extendedProps']['group_id']);

        $period = $events->firstWhere('extendedProps.kind', 'period');
        $this->assertSame('2026-10-12', $period['end'], 'полоса периода остаётся много­дневной');
    }

    public function test_period_without_deadline_produces_single_event(): void
    {
        $this->enroll();
        $this->asUser(['role' => 'Администратор']);

        $events = $this->getJson('/api/calendar')->json('data.events');

        $this->assertCount(1, $events);
        $this->assertSame('period', $events[0]['extendedProps']['kind']);
        $this->assertNull($events[0]['extendedProps']['deadline']);
    }

    public function test_kind_filter_splits_periods_and_deadlines(): void
    {
        $this->enroll(['deadline' => '2026-10-20']);
        $this->asUser(['role' => 'Администратор']);

        $both = $this->getJson('/api/calendar')->json('data.events');
        $periods = $this->getJson('/api/calendar?kind=period')->json('data.events');
        $deadlines = $this->getJson('/api/calendar?kind=deadline')->json('data.events');

        $this->assertCount(2, $both);
        $this->assertCount(1, $periods, 'kind=period возвращает только полосы');
        $this->assertSame('period', $periods[0]['extendedProps']['kind']);
        $this->assertCount(1, $deadlines, 'kind=deadline возвращает только сроки сдачи');
        $this->assertSame('deadline', $deadlines[0]['extendedProps']['kind']);
    }

    public function test_status_filter_applies_to_deadlines_too(): void
    {
        // Регресс: фильтр по состоянию проверялся только при построении
        // полос периодов, а метки срока сдачи собирались отдельным map()
        // без этой проверки. Итог: ?status=completed возвращал метки
        // будущих дедлайнов вместе с «подходящими» периодами.
        $this->enroll([
            'study_from' => now()->subDay()->toDateString(),
            'study_to' => now()->addDays(30)->toDateString(),
            'deadline' => now()->addDays(10)->toDateString(),
        ]);

        $this->asUser(['role' => 'Администратор']);

        // «В процессе» — это и полоса периода, и её дедлайн: оба типа
        // живут по датам, и фильтр применяется к каждому.
        $active = collect($this->getJson('/api/calendar?status=active')->json('data.events'));

        $this->assertCount(2, $active);
        $this->assertSame(
            ['deadline', 'period'],
            $active->pluck('extendedProps.kind')->sort()->values()->all()
        );

        $this->assertCount(
            0,
            $this->getJson('/api/calendar?status=completed')->json('data.events'),
            'метка будущего дедлайна не должна проходить фильтр «завершено»'
        );

        $this->assertCount(
            0,
            $this->getJson('/api/calendar?kind=deadline&status=completed')->json('data.events')
        );
    }

    public function test_status_filter_applies_to_overdue_deadline(): void
    {
        // Просроченный срок сдачи — это «завершено» по дате, и он обязан
        // находиться фильтром, иначе просроченная работа не видна.
        $this->enroll([
            'study_from' => now()->subMonths(2)->toDateString(),
            'study_to' => now()->subMonth()->toDateString(),
            'deadline' => now()->subDay()->toDateString(),
        ]);

        $this->asUser(['role' => 'Администратор']);

        $events = collect($this->getJson('/api/calendar?status=completed')->json('data.events'));

        $deadlines = $events->where('extendedProps.kind', 'deadline');
        $this->assertCount(1, $deadlines);
        $this->assertSame('completed', $deadlines->first()['extendedProps']['status']);
    }

    public function test_kind_filter_is_validated(): void
    {
        $this->asUser(['role' => 'Администратор']);

        $this->getJson('/api/calendar?kind=wrong')->assertStatus(422);
    }

    public function test_deadline_respects_scoping(): void
    {
        $this->enroll(['deadline' => '2026-10-20']);
        $this->enroll([
            'group_id' => $this->otherGroup->id,
            'course_id' => $this->otherCourse->id,
            'deadline' => '2026-10-21',
        ]);

        $this->asUser(['role' => 'Обучаемый', 'group_id' => $this->group->id]);

        $events = $this->getJson('/api/calendar')->json('data.events');

        $this->assertCount(2, $events);
        foreach ($events as $event) {
            $this->assertSame(
                $this->group->id,
                $event['extendedProps']['group_id'],
                'срок сдачи чужой группы не должен попадать в выдачу'
            );
        }
    }

    public function test_user_without_group_does_not_see_foreign_calendar(): void
    {
        // Регресс на утечку: у пользователя без группы group_id === null,
        // и условие «если группа задана — отфильтровать» не срабатывало.
        // Запрос уходил дальше БЕЗ скоупа и отдавал расписание всех групп.
        $this->enroll();
        $this->enroll(['group_id' => $this->otherGroup->id, 'course_id' => $this->otherCourse->id]);

        $this->asUser(['role' => 'Обучаемый']);

        $response = $this->getJson('/api/calendar')->assertOk();

        $this->assertSame([], $response->json('data.events'));
        $this->assertSame(0, $response->json('meta.total'));
    }
}
