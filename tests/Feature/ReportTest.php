<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Group;
use App\Models\Group2learning;
use App\Models\LessonProgress;
use App\Models\Role;
use App\Models\RoleUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Отчёты и аналитика.
 *
 * Проверяется то, что отчёт не становится обходом прав: у методиста
 * без groups.manage цифры своей группы, чужой group_id подменяется его
 * собственным, а без reports.view отчёта нет вовсе.
 *
 * Второе, не менее важное, — честность пустых значений: группа без
 * попыток отдаёт прочерк (null), а не 0%. «0% успеваемости» читается
 * как «все провалили», и это неправда.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(string $slug, array $attrs = []): User
    {
        $user = User::factory()->create($attrs + ['role' => 'Инструктор']);
        $role = Role::where('slug', $slug)->first();

        if ($role !== null) {
            RoleUser::create(['user_id' => $user->id, 'role_id' => $role->id]);
        }

        return $user;
    }

    private function enroll(Course $course, Group $group, array $dates = []): Group2learning
    {
        $record = new Group2learning([
            'group_id' => $group->id,
            'course_id' => $course->id,
        ]);
        $record->study_from = $dates['from'] ?? now()->subMonth()->toDateString();
        $record->study_to = $dates['to'] ?? now()->addMonth()->toDateString();
        $record->save();

        return $record;
    }

    public function test_guest_and_trainee_have_no_reports(): void
    {
        $this->getJson('/api/reports')->assertUnauthorized();

        $trainee = User::factory()->create(['role' => 'Обучаемый']);
        $role = Role::where('slug', 'trainee')->first();

        if ($role !== null) {
            RoleUser::create(['user_id' => $trainee->id, 'role_id' => $role->id]);
        }

        $this->actingAs($trainee, 'sanctum')->getJson('/api/reports')->assertForbidden();
    }

    public function test_admin_gets_report_with_all_sections(): void
    {
        $admin = $this->userWith('admin', ['fio' => 'Администратор']);
        $course = Course::factory()->create(['title' => 'Курс А']);
        $group = Group::factory()->create(['groupname' => 'Группа А']);
        $this->enroll($course, $group);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/reports')->assertOk();

        $response->assertJsonStructure([
            'data' => ['enrollment', 'exams', 'progress', 'activity', 'timeline', 'groups', 'courses'],
        ]);

        $this->assertSame(1, $response->json('data.enrollment.total'));
        $this->assertCount(6, $response->json('data.timeline'), 'шесть месяцев динамики');
    }

    public function test_exam_stats_reflect_attempts(): void
    {
        $admin = $this->userWith('admin');
        $course = Course::factory()->create();
        $exam = Exam::create([
            'title' => 'Экзамен',
            'course_id' => $course->id,
            'passing_score' => 0.7,
        ]);

        $passer = User::factory()->create();
        $failing = User::factory()->create();

        ExamAttempt::create([
            'user_id' => $passer->id, 'exam_id' => $exam->id,
            'total_count' => 10, 'correct_count' => 9, 'score' => 0.9, 'passed' => true,
        ]);
        ExamAttempt::create([
            'user_id' => $failing->id, 'exam_id' => $exam->id,
            'total_count' => 10, 'correct_count' => 4, 'score' => 0.4, 'passed' => false,
        ]);

        $data = $this->actingAs($admin, 'sanctum')->getJson('/api/reports')->assertOk()->json('data');

        $this->assertSame(2, $data['exams']['attempts']);
        $this->assertSame(1, $data['exams']['passed']);
        $this->assertSame(50, $data['exams']['percent']);
    }

    /**
     * Пустая группа — это не «все провалили».
     *
     * Проверка намеренно жёсткая: подмена null на 0 делает вид, что
     * группа провалила экзамены, которых у неё не было.
     */
    public function test_empty_exam_stats_are_null_not_zero(): void
    {
        $admin = $this->userWith('admin');

        $data = $this->actingAs($admin, 'sanctum')->getJson('/api/reports')->assertOk()->json('data');

        $this->assertNull($data['exams']['percent'], 'без попыток процент не определён');
        $this->assertNull($data['progress']['percent']);
    }

    public function test_progress_counts_closed_lessons(): void
    {
        $admin = $this->userWith('admin');
        $course = Course::factory()->create();
        $user = User::factory()->create();

        $lesson = \App\Models\Aukstructure::create([
            'course_id' => $course->id,
            'title' => 'Урок',
            'type' => 1,
            'identifier' => 'RPT-1',
        ]);

        LessonProgress::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'percent' => 100,
            'completed_at' => now(),
        ]);

        $data = $this->actingAs($admin, 'sanctum')->getJson('/api/reports')->assertOk()->json('data');

        $this->assertSame(1, $data['progress']['lessons_done']);
        $this->assertSame(1, $data['progress']['lessons_started']);
        $this->assertSame(100, $data['progress']['percent']);
    }

    /**
     * Границы видимости.
     *
     * Инструктор без groups.manage не должен ни видеть чужие группы в
     * фильтре, ни выбрать их: подмена group_id — единственный способ
     * узнать цифры соседней группы.
     */
    public function test_scoped_user_cannot_read_other_groups(): void
    {
        // Роль ровно с одним правом — reports.view. Ни groups.manage,
        // ни users.courses: остаётся отчёт только по своей группе.
        //
        // Право создаётся здесь: RefreshDatabase прогоняет миграции, но
        // не сидеры, поэтому каталога прав в тестовой базе нет.
        $permission = \App\Models\Permission::firstOrCreate(
            ['slug' => 'reports.view'],
            ['name' => 'Просмотр отчётов', 'group' => 'reports']
        );

        $role = Role::firstOrCreate(['slug' => 'reporter'], ['rolename' => 'Репортёр']);
        $role->permissions()->sync([$permission->id]);

        $user = User::factory()->create(['role' => 'Репортёр']);
        RoleUser::create(['user_id' => $user->id, 'role_id' => $role->id]);

        $myGroup = Group::factory()->create(['groupname' => 'Моя']);
        $user->group_id = $myGroup->id;
        $user->save();

        $otherGroup = Group::factory()->create(['groupname' => 'Чужая']);

        $report = $this->actingAs($user, 'sanctum')->getJson('/api/reports')->assertOk();
        $this->assertCount(1, $report->json('data.groups'));

        // Запрос чужой группы молча подменяется своей.
        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/reports?group_id={$otherGroup->id}")
            ->assertOk();

        $this->assertSame($myGroup->id, $response->json('data.filters.group_id'));
    }

    public function test_export_returns_csv_with_bom(): void
    {
        $admin = $this->userWith('admin');
        $course = Course::factory()->create(['title' => 'Курс для CSV']);
        $group = Group::factory()->create(['groupname' => 'Группа CSV']);
        $this->enroll($course, $group);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/reports/export?report=enrollment')
            ->assertOk();

        $csv = $response->getContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Курс для CSV', $csv);
        $this->assertStringContainsString('Группа CSV', $csv);
    }

    /** Экспорт в XLSX: реальный zip-контейнер xlsx. */
    public function test_export_xlsx_returns_excel_file(): void
    {
        $admin = $this->userWith('admin');
        $course = Course::factory()->create(['title' => 'Курс для XLSX']);
        $group = Group::factory()->create(['groupname' => 'Группа XLSX']);
        $this->enroll($course, $group);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/reports/export?report=enrollment&format=xlsx');

        $response->assertOk();
        $this->assertStringStartsWith('PK', (string) $response->getContent());
    }

    /** Экспорт в PDF: валидный pdf-заголовок. */
    public function test_export_pdf_returns_pdf_file(): void
    {
        $admin = $this->userWith('admin');
        $course = Course::factory()->create(['title' => 'Курс для PDF']);
        $group = Group::factory()->create(['groupname' => 'Группа PDF']);
        $this->enroll($course, $group);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/reports/export?report=enrollment&format=pdf');

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_unknown_report_is_rejected(): void
    {
        $admin = $this->userWith('admin');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/reports/export?report=nonsense')
            ->assertStatus(422);
    }

    /**
     * Период длиннее года обрезается.
     *
     * Без обрезки отчёт «за всё время» вытягивает все попытки за годы
     * и упирается в память. Обрезка должна быть объявлена, а не
     * молчаливой.
     */
    public function test_long_period_is_trimmed(): void
    {
        $admin = $this->userWith('admin');

        $from = now()->subYears(5)->toDateString();

        $filters = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/reports?from={$from}")
            ->assertOk()
            ->json('data.filters');

        $this->assertGreaterThanOrEqual(
            now()->subYear()->toDateString(),
            $filters['from'],
            'период должен быть обрезан до года'
        );
    }
}