<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Group;
use App\Models\Group2learning;
use App\Models\Permission;
use App\Models\Question;
use App\Models\User;
use App\Policies\CoursePolicy;
use App\Policies\ExamPolicy;
use App\Policies\QuestionBankPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Политики курсов, экзаменов и банка вопросов.
 *
 * Общий смысл: «видеть» и «управлять» — разные capability, и оба
 * ограничены областью. У обучаемого есть courses.view и exams.take, но
 * нет courses.manage, exams.manage, questions.view — поэтому он видит
 * только назначенное и не должен получать ни банк вопросов, ни чужие
 * курсы, ни незадачный ему экзамен.
 */
class ResourcePolicyTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->group = Group::factory()->create();
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

    private function course(?Group $enrolledFor = null): Course
    {
        $course = Course::factory()->create();

        if ($enrolledFor !== null) {
            Group2learning::factory()->create([
                'group_id' => $enrolledFor->id,
                'course_id' => $course->id,
            ]);
        }

        return $course;
    }

    // ------------------------------------------------------------- CoursePolicy

    public function test_trainee_sees_only_enrolled_course(): void
    {
        $actor = $this->trainee();
        $mine = $this->course($this->group);
        $foreign = $this->course(Group::factory()->create());

        $policy = app(CoursePolicy::class);

        $this->assertTrue($policy->view($actor, $mine));
        $this->assertFalse(
            $policy->view($actor, $foreign),
            'обучаемый не должен открывать неназначенный курс'
        );
    }

    public function test_manager_sees_every_course_even_without_enrolment(): void
    {
        $manager = User::factory()->create(['role' => 'Инструктор', 'group_id' => $this->group->id]);
        $this->grant($manager, 'courses.view', 'courses.manage');
        $unassigned = $this->course();

        $this->assertTrue(app(CoursePolicy::class)->view($manager, $unassigned));
    }

    public function test_trainee_without_group_sees_no_course(): void
    {
        // Пустая группа — «нет своей части», а не «весь каталог».
        $actor = User::factory()->create(['role' => 'Обучаемый', 'group_id' => null]);
        $course = $this->course($this->group);

        $this->assertFalse(app(CoursePolicy::class)->view($actor, $course));
    }

    public function test_publish_is_separate_from_manage(): void
    {
        $manager = User::factory()->create(['role' => 'Инструктор', 'group_id' => $this->group->id]);
        $manager = $this->grant($manager, 'courses.manage');
        $course = $this->course($this->group);

        $this->assertTrue(app(CoursePolicy::class)->update($manager, $course));
        $this->assertFalse(
            app(CoursePolicy::class)->publish($manager, $course),
            'управлять курсом и публиковать его — разные действия'
        );

        $manager = $this->grant($manager, 'courses.publish');
        $this->assertTrue(app(CoursePolicy::class)->publish($manager, $course));
    }

    public function test_trainee_cannot_manage_courses(): void
    {
        $actor = $this->trainee();
        $course = $this->course($this->group);

        $this->assertFalse(app(CoursePolicy::class)->update($actor, $course));
        $this->assertFalse(app(CoursePolicy::class)->create($actor));
        $this->assertFalse(app(CoursePolicy::class)->delete($actor, $course));
    }

    public function test_course_gate_abilities(): void
    {
        $actor = $this->trainee();
        $mine = $this->course($this->group);
        $foreign = $this->course(Group::factory()->create());

        $this->assertTrue($actor->can('view-course', $mine));
        $this->assertFalse($actor->can('view-course', $foreign));
        $this->assertFalse($actor->can('publish-course', $mine));
    }

    // --------------------------------------------------------------- ExamPolicy

    /**
     * Экзамен создаётся напрямую: фабрики для Exam в проекте нет, а
     * политике важны только адресаты (user_id / group_id) и наличие
     * прав у актора.
     */
    private function exam(?User $assignee = null, ?Group $forGroup = null): Exam
    {
        return Exam::create([
            'title' => 'Экзамен по дисциплине',
            'user_id' => $assignee?->id,
            'group_id' => $forGroup?->id,
            'passing_score' => 0.6,
            'max_attempts' => 1,
        ]);
    }

    public function test_trainee_can_take_exam_assigned_via_group(): void
    {
        $actor = $this->trainee();
        $exam = $this->exam(null, $this->group);

        $this->assertTrue(app(ExamPolicy::class)->take($actor, $exam));
    }

    public function test_trainee_cannot_take_exam_assigned_to_another_group(): void
    {
        $actor = $this->trainee();
        $exam = $this->exam(null, Group::factory()->create());

        $this->assertFalse(app(ExamPolicy::class)->take($actor, $exam));
    }

    public function test_exam_without_addressee_is_not_takeable(): void
    {
        // Ни user_id, ни group_id — это просто набор вопросов. Открыть его
        // может только управляющий.
        $actor = $this->trainee();
        $exam = $this->exam();

        $this->assertFalse(app(ExamPolicy::class)->take($actor, $exam));

        $manager = User::factory()->create(['role' => 'Инструктор', 'group_id' => $this->group->id]);
        $manager = $this->grant($manager, 'exams.manage');
        $this->assertTrue(app(ExamPolicy::class)->take($manager, $exam));
    }

    public function test_exams_take_requires_permission(): void
    {
        // Роль обучаемого по role_matrix сама даёт exams.take, поэтому
        // «нет права» здесь означает явно назначенную роль без прав:
        // матрица отключается, как только у пользователя есть хоть одна
        // роль или право.
        $actor = User::factory()->create(['role' => 'Обучаемый', 'group_id' => $this->group->id]);
        $this->grant($actor, 'content.view');

        $exam = $this->exam(null, $this->group);

        $this->assertFalse(app(ExamPolicy::class)->take($actor, $exam));
    }

    public function test_trainee_cannot_manage_exams(): void
    {
        $actor = $this->trainee();
        $exam = $this->exam(null, $this->group);

        $this->assertFalse(app(ExamPolicy::class)->manage($actor));
        $this->assertFalse(app(ExamPolicy::class)->delete($actor, $exam));
        $this->assertFalse(app(ExamPolicy::class)->create($actor));
    }

    public function test_exam_gate_abilities(): void
    {
        $actor = $this->trainee();
        $mine = $this->exam(null, $this->group);
        $foreign = $this->exam(null, Group::factory()->create());

        $this->assertTrue($actor->can('take-exam', $mine));
        $this->assertFalse($actor->can('take-exam', $foreign));
        $this->assertFalse($actor->can('manage-exams'));
    }

    // ------------------------------------------------------ QuestionBankPolicy

    public function test_trainee_has_no_access_to_question_bank(): void
    {
        $actor = $this->trainee();
        $policy = app(QuestionBankPolicy::class);

        $this->assertFalse($policy->viewAny($actor));
        $this->assertFalse($policy->statistics($actor));
        $this->assertFalse($policy->create($actor));
        $this->assertFalse($policy->manageCategory($actor, Category::factory()->create()));
    }

    public function test_methodologist_can_read_bank_but_not_write_without_manage(): void
    {
        $actor = User::factory()->create(['role' => 'Инструктор', 'group_id' => $this->group->id]);
        $actor = $this->grant($actor, 'questions.view');
        $policy = app(QuestionBankPolicy::class);

        $this->assertTrue($policy->viewAny($actor));
        $this->assertTrue($policy->statistics($actor));
        $this->assertFalse($policy->create($actor));
        $this->assertFalse($policy->delete($actor, Question::factory()->create()));
    }

    public function test_question_manage_grants_write(): void
    {
        $actor = User::factory()->create(['role' => 'Инструктор', 'group_id' => $this->group->id]);
        $actor = $this->grant($actor, 'questions.view', 'questions.manage');
        $policy = app(QuestionBankPolicy::class);

        $this->assertTrue($policy->create($actor));
        $this->assertTrue($policy->update($actor, Question::factory()->create()));
        $this->assertTrue($policy->delete($actor, Question::factory()->create()));
    }

    public function test_question_bank_endpoint_still_closed_to_trainee(): void
    {
        // Политика не заменяет middleware, а дополняет его: маршрут и
        // политика должны говорить одно и то же.
        Question::factory()->create();

        $this->asUser(['role' => 'Обучаемый']);
        $this->getJson('/api/questions')->assertStatus(403);
        $this->getJson('/api/questions/statistics')->assertStatus(403);
    }
}
