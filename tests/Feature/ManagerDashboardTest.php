<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\Category;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Group;
use App\Models\Group2learning;
use App\Models\Permission;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Сводка для администратора и инструктора.
 *
 * Закрывает то, что делало дашборд бессмысленным для управляющих:
 * /dashboard отдавал кабинет обучаемого всем, и администратор видел
 * «Состояние вашего обучения на сегодня».
 *
 * Проверяется главное — область:
 *  - инструктор получает данные СВОЕЙ группы, а не всей системы;
 *    пустая группа — это «нет своей части», а не «вся система»;
 *  - показатели банка вопросов появляются только у того, кому банк
 *    виден: у обучаемого questions.view нет, и чтение банка уносит
 *    в браузер правильные ответы;
 *  - список требующего внимания — про группы без учебного плана, то
 *    есть подсказывает действие, а не просто суммирует строки.
 */
class ManagerDashboardTest extends TestCase
{
    use AuthenticatesApi;
    use RefreshDatabase;

    private Group $own;

    private Group $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->own = Group::factory()->create(['groupname' => 'Своя группа']);
        $this->other = Group::factory()->create(['groupname' => 'Чужая группа']);

        // Курс записан только своей группе.
        $course = Course::factory()->create(['title' => 'Курс для сводки']);
        Group2learning::factory()->create([
            'group_id' => $this->own->id,
            'course_id' => $course->id,
        ]);
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

    /** Значения показателей по ключу. */
    private function stats(array $data): array
    {
        return collect($data['stats'])->pluck('value', 'key')->all();
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/dashboard/summary')->assertUnauthorized();
    }

    public function test_admin_sees_whole_system(): void
    {
        $this->traineeIn($this->other);
        $this->admin();

        $data = $this->getJson('/api/dashboard/summary')->assertOk()->json('data');

        $this->assertSame('admin', $data['role']);

        $stats = $this->stats($data);
        // Все группы, включая те, что не его собственные.
        $this->assertSame(2, $stats['groups']);
        $this->assertSame(2, $stats['users']);
        $this->assertSame(1, $stats['assignments']);
    }

    public function test_instructor_sees_only_own_group(): void
    {
        // Инструктор своей группы: чужие люди и группы — не его часть
        // работы, даже если у него есть права на людей и группы.
        $this->traineeIn($this->own);
        $this->traineeIn($this->other);

        $instructor = User::factory()->create([
            'role' => 'Инструктор',
            'group_id' => $this->own->id,
        ]);
        $this->grant($instructor, 'users.view', 'groups.view', 'courses.view');
        $this->asExistingUser($instructor);

        $data = $this->getJson('/api/dashboard/summary')->assertOk()->json('data');
        $stats = $this->stats($data);

        $this->assertSame('instructor', $data['role']);
        $this->assertSame(1, $stats['groups'], 'только своя группа');
        // Два человека: обучаемый группы и сам инструктор — он тоже её
        // участник, иначе он не видел бы собственный профиль.
        $this->assertSame(2, $stats['users'], 'люди своей группы вместе с инструктором');
        $this->assertSame(1, $stats['assignments'], 'записи своей группы');
    }

    public function test_manager_without_group_sees_nothing(): void
    {
        // Пустая группа — «нет своей части», а не «вся система».
        $this->traineeIn($this->other);

        $instructor = User::factory()->create(['role' => 'Инструктор', 'group_id' => null]);
        $this->grant($instructor, 'users.view', 'groups.view');
        $this->asExistingUser($instructor);

        $stats = $this->stats(
            $this->getJson('/api/dashboard/summary')->assertOk()->json('data')
        );

        $this->assertSame(0, $stats['groups']);
        $this->assertSame(0, $stats['users']);
    }

    public function test_trainee_gets_no_people_or_group_metrics(): void
    {
        // Обучаемому сводка показывается в другой виде (его кабинет),
        // и лишних счётчиков про «людей и группы» он получать не должен.
        $this->asExistingUser(User::factory()->create([
            'role' => 'Обучаемый',
            'group_id' => $this->own->id,
        ]));

        $stats = $this->stats(
            $this->getJson('/api/dashboard/summary')->assertOk()->json('data')
        );

        $this->assertArrayNotHasKey('users', $stats);
        $this->assertArrayNotHasKey('groups', $stats);
    }

    public function test_question_metrics_only_for_those_who_see_the_bank(): void
    {
        Question::factory()->create(['question_text' => 'Вопрос без верного ответа']);

        // Обучаемый: банк закрыт, значит и метрики банка не его.
        $this->asExistingUser(User::factory()->create([
            'role' => 'Обучаемый',
            'group_id' => $this->own->id,
        ]));
        $traineeStats = $this->stats(
            $this->getJson('/api/dashboard/summary')->assertOk()->json('data')
        );
        $this->assertArrayNotHasKey('questions', $traineeStats);

        // Методист с questions.view: метрики появляются.
        $methodologist = User::factory()->create([
            'role' => 'Инструктор',
            'group_id' => $this->own->id,
        ]);
        $this->grant($methodologist, 'questions.view');
        $this->asExistingUser($methodologist);

        $stats = $this->stats(
            $this->getJson('/api/dashboard/summary')->assertOk()->json('data')
        );

        $this->assertSame(1, $stats['questions']);
        $this->assertSame(1, $stats['questionsBroken'], 'вопрос без верного ответа считается');
    }

    public function test_question_with_correct_answer_is_not_broken(): void
    {
        $question = Question::factory()->create(['question_text' => 'Вопрос с верным ответом']);
        Answer::factory()->create(['question_id' => $question->id, 'is_correct' => true]);

        $manager = User::factory()->create(['role' => 'Инструктор', 'group_id' => $this->own->id]);
        $this->grant($manager, 'questions.view');
        $this->asExistingUser($manager);

        $stats = $this->stats(
            $this->getJson('/api/dashboard/summary')->assertOk()->json('data')
        );

        $this->assertSame(1, $stats['questions']);
        $this->assertSame(0, $stats['questionsBroken']);
    }

    public function test_attention_lists_groups_without_plan(): void
    {
        // Сумма «групп: 10» ничего не подсказывает. Список отвечает на
        // вопрос «что делать».
        $data = $this->getAsAdmin();

        $this->assertNotEmpty($data['attention'], 'есть группа без учебного плана');

        $row = collect($data['attention'])->firstWhere('key', 'groupWithoutPlan');
        $this->assertNotNull($row);
        $this->assertSame('Чужая группа', $row['text']);
        $this->assertStringContainsString('/groups/edit/', $row['to']);
        $this->assertSame('manager.attention.groupWithoutPlan', $row['hint_key']);
    }

    public function test_attention_empty_when_every_group_has_plan(): void
    {
        Group2learning::factory()->create([
            'group_id' => $this->other->id,
            'course_id' => Course::factory()->create()->id,
        ]);

        $data = $this->getAsAdmin();

        $this->assertSame([], $data['attention']);
    }

    public function test_recent_attempts_carry_no_questions_or_answers(): void
    {
        // Сводка не должна утекать материал банка: только итог попытки.
        $exam = $this->makeExam('Экзамен по разделу');
        $student = $this->traineeIn($this->own);

        ExamAttempt::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'correct_count' => 2,
            'total_count' => 3,
            'score' => 2 / 3,
            'passed' => true,
            'submitted_at' => now(),
        ]);

        $data = $this->getAsAdmin();

        $this->assertCount(1, $data['recent']);
        $row = $data['recent'][0];

        $this->assertSame('Экзамен по разделу', $row['exam']);
        $this->assertSame(2, $row['correct_count']);
        $this->assertTrue($row['passed']);

        $encoded = json_encode($row, JSON_UNESCAPED_UNICODE);
        foreach (['answers', 'is_correct', 'question_text'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $encoded);
        }
    }

    public function test_recent_attempts_are_scoped_to_own_group(): void
    {
        $mine = $this->makeExam('Экзамен своей группы');
        $foreign = $this->makeExam('Экзамен чужой группы');

        $studentMine = $this->traineeIn($this->own);
        $studentForeign = $this->traineeIn($this->other);

        foreach ([[$mine, $studentMine], [$foreign, $studentForeign]] as [$exam, $student]) {
            ExamAttempt::create([
                'exam_id' => $exam->id,
                'user_id' => $student->id,
                'correct_count' => 1,
                'total_count' => 1,
                'score' => 1.0,
                'passed' => true,
                'submitted_at' => now(),
            ]);
        }

        $instructor = User::factory()->create(['role' => 'Инструктор', 'group_id' => $this->own->id]);
        $this->grant($instructor, 'exams.manage', 'groups.view');
        $this->asExistingUser($instructor);

        $data = $this->getJson('/api/dashboard/summary')->assertOk()->json('data');

        $exams = collect($data['recent'])->pluck('exam')->all();
        $this->assertContains('Экзамен своей группы', $exams);
        $this->assertNotContains('Экзамен чужой группы', $exams);
    }

    /** Ответ сводки под администратором. */
    private function getAsAdmin(): array
    {
        $this->admin();

        return $this->getJson('/api/dashboard/summary')->assertOk()->json('data');
    }

    /** Фабрики для экзаменов в проекте нет, а политике важны только поля. */
    private function makeExam(string $title): Exam
    {
        return Exam::create([
            'title' => $title,
            'passing_score' => 0.6,
            'max_attempts' => 1,
        ]);
    }

    private function traineeIn(Group $group): User
    {
        return User::factory()->create([
            'role' => 'Обучаемый',
            'group_id' => $group->id,
        ]);
    }
}
