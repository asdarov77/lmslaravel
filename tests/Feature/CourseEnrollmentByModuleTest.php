<?php

namespace Tests\Feature;

use App\Models\Aircraft;
use App\Models\Aukstructure;
use App\Models\Category;
use App\Models\Course;
use App\Models\Group;
use App\Models\Group2learning;
use App\Models\User;
use App\Support\CourseAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Запись группы на ОТДЕЛЬНЫЕ МАТЕРИАЛЫ курса, а не на курс целиком.
 *
 * Сценарий: у курса несколько специальностей, и группу отмечают галочками
 * по конкретным модулям внутри курса — пилоту его блок, бортинженеру
 * свой. Галочки в форме «Запись на курсы» писались в group2learnings
 * отдельными строками с parent_id, но проверка доступа смотрела только
 * course_id и category_id. В итоге группа, отмеченная на одном материале,
 * получала весь курс целиком, и отметки не ограничивали ничего.
 *
 * Проверяем ровно это: CourseAccess::allowedModuleIds и canOpenModule,
 * включая два случая, где легко ошибиться — запись на раздел (материалы
 * внутри него должны открыться) и запись без parent_id (курс целиком).
 */
class CourseEnrollmentByModuleTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;

    private User $trainee;

    private Course $course;

    private Category $category;

    /** @var Aukstructure тема */
    private Aukstructure $topic;

    /** @var Aukstructure раздел внутри темы */
    private Aukstructure $section;

    /** @var Aukstructure модуль пилота */
    private Aukstructure $pilotModule;

    /** @var Aukstructure модуль бортинженера */
    private Aukstructure $engineerModule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = Group::factory()->create(['groupname' => 'Группа пилотов']);
        $this->category = Category::factory()->create(['title' => 'Специальность']);

        $aircraft = Aircraft::factory()->create(['path' => 'КЛЕН', 'title' => 'Клен']);
        $this->course = Course::factory()->create([
            'title' => 'Курс с двумя блоками',
            'aircraft_id' => $aircraft->id,
        ]);
        $this->course->categories()->attach($this->category->id);

        // type: 1 — тема, 2 — раздел, 3 — материал.
        $this->topic = Aukstructure::factory()->create([
            'course_id' => $this->course->id, 'title' => 'Тема', 'type' => 1, 'parent_id' => null,
        ]);
        $this->section = Aukstructure::factory()->create([
            'course_id' => $this->course->id, 'title' => 'Раздел', 'type' => 2, 'parent_id' => $this->topic->id,
        ]);
        $this->pilotModule = Aukstructure::factory()->create([
            'course_id' => $this->course->id, 'title' => 'Пилотский блок', 'type' => 3, 'parent_id' => $this->section->id,
        ]);
        $this->engineerModule = Aukstructure::factory()->create([
            'course_id' => $this->course->id, 'title' => 'Блок БИ', 'type' => 3, 'parent_id' => $this->section->id,
        ]);

        $this->trainee = User::factory()->create([
            'role' => 'Обучаемый',
            'group_id' => $this->group->id,
        ]);
    }

    private function enroll(?int $parentId): void
    {
        Group2learning::factory()->create([
            'course_id' => $this->course->id,
            'group_id' => $this->group->id,
            'category_id' => $this->category->id,
            'parent_id' => $parentId,
            'typeOfLesson' => 'Лекция',
            'study_from' => now()->subDay()->toDateString(),
            'study_to' => now()->addDays(5)->toDateString(),
        ]);
    }

    public function test_no_enrollment_means_no_module_allowed(): void
    {
        $this->assertSame([], CourseAccess::allowedModuleIds($this->trainee, $this->course));
        $this->assertFalse(CourseAccess::canOpenModule($this->trainee, $this->course, $this->pilotModule->id));
    }

    public function test_enrollment_on_one_module_does_not_open_whole_course(): void
    {
        $this->enroll($this->pilotModule->id);

        $allowed = CourseAccess::allowedModuleIds($this->trainee, $this->course);

        $this->assertNotNull($allowed, 'Запись на модуль не должна открывать весь курс');
        $this->assertContains($this->pilotModule->id, $allowed);
        $this->assertNotContains($this->engineerModule->id, $allowed);

        $this->assertTrue(CourseAccess::canOpenModule($this->trainee, $this->course, $this->pilotModule->id));
        $this->assertFalse(CourseAccess::canOpenModule($this->trainee, $this->course, $this->engineerModule->id));
    }

    public function test_enrollment_on_section_opens_modules_inside_it(): void
    {
        // Отметить можно и раздел: тогда открыты всё, что вложено в него.
        $this->enroll($this->section->id);

        $allowed = CourseAccess::allowedModuleIds($this->trainee, $this->course);

        $this->assertContains($this->pilotModule->id, $allowed);
        $this->assertContains($this->engineerModule->id, $allowed);
        $this->assertNotContains($this->topic->id, $allowed, 'Тема выше отмеченного раздела не открывается');
    }

    public function test_enrollment_without_parent_keeps_whole_course_open(): void
    {
        // Запись без parent_id — это «группа записана на курс целиком».
        $this->enroll(null);

        $this->assertNull(CourseAccess::allowedModuleIds($this->trainee, $this->course));
        $this->assertTrue(CourseAccess::canOpenModule($this->trainee, $this->course, $this->engineerModule->id));
    }

    public function test_course_level_enrollment_wins_over_module_level_one(): void
    {
        // Смешанная запись: одна строка на курс целиком и одна на модуль.
        // Курс открыт целиком, иначе группа лишилась бы части материала.
        $this->enroll($this->pilotModule->id);
        $this->enroll(null);

        $this->assertNull(CourseAccess::allowedModuleIds($this->trainee, $this->course));
        $this->assertTrue(CourseAccess::canOpenModule($this->trainee, $this->course, $this->engineerModule->id));
    }

    public function test_specialty_of_the_group_is_still_enforced(): void
    {
        // Модуль другой специальности не открывается даже при записи на
        // весь курс: ограничение по специальности не weaker ограничения
        // по модулю, а действует вместе с ним.
        $otherCategory = Category::factory()->create(['title' => 'Чужая специальность']);
        $foreignModule = Aukstructure::factory()->create([
            'course_id' => $this->course->id, 'title' => 'Чужой модуль', 'type' => 3,
            'parent_id' => $this->section->id,
        ]);
        $foreignModule->categories()->attach($otherCategory->id);

        $this->enroll(null);

        $this->assertTrue(CourseAccess::canOpenModule($this->trainee, $this->course, $this->pilotModule->id));
        $this->assertFalse(CourseAccess::canOpenModule($this->trainee, $this->course, $foreignModule->id));
    }
}
