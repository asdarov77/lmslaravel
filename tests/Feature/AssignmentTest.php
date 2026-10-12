<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Group;
use App\Models\Group2learning;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Домашние задания и работы.
 *
 * Главное, что тут ломается тихо, — границы видимости: чужая группа,
 * чужая работа и оценка выше максимума. Каждая из этих ошибок выглядит
 * как «работает», пока не попадёт в чужую группу.
 */
class AssignmentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Преподаватель с правами grading.manage и groups.manage.
     *
     * Роль и права создаются здесь, а не берутся из сидеров: под
     * RefreshDatabase выполняются только миграции, и каталога прав в
     * тестовой базе нет. Без этого проверка прав тихо возвращала бы
     * 403 на всё подряд и тесты врали бы.
     */
    private function teacher(): User
    {
        $user = User::factory()->create(['role' => 'Инструктор', 'fio' => 'Иванов Иван Иванович']);

        $role = Role::firstOrCreate(['slug' => 'instructor'], ['rolename' => 'Инструктор']);
        $permissionIds = collect(['grading.manage', 'groups.manage'])
            ->map(fn ($slug) => Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'group' => 'grading'])->id)
            ->all();

        $role->permissions()->sync($permissionIds);
        RoleUser::create(['user_id' => $user->id, 'role_id' => $role->id]);

        return $user;
    }

    private function trainee(Group $group): User
    {
        $user = User::factory()->create(['role' => 'Обучаемый', 'fio' => 'Петров Иван Иванович']);
        $user->group_id = $group->id;
        $user->save();

        return $user;
    }

    private function courseFor(Group $group): Course
    {
        $course = Course::factory()->create(['title' => 'Курс А']);

        $record = new Group2learning([
            'group_id' => $group->id,
            'course_id' => $course->id,
        ]);
        $record->study_from = now()->subDay()->toDateString();
        $record->study_to = now()->addMonth()->toDateString();
        $record->save();

        return $course;
    }

    private function makeAssignment(Group $group, Course $course, array $attrs = []): Assignment
    {
        return Assignment::create(array_merge([
            'title' => 'Расчёт',
            'course_id' => $course->id,
            'group_id' => $group->id,
            'max_score' => 5,
            'published_at' => now(),
        ], $attrs));
    }

    public function test_guest_has_no_assignments(): void
    {
        $this->getJson('/api/assignments')->assertUnauthorized();
    }

    public function test_trainee_sees_only_own_group_published_tasks(): void
    {
        $group = Group::factory()->create(['groupname' => 'Моя']);
        $other = Group::factory()->create(['groupname' => 'Чужая']);
        $course = $this->courseFor($group);
        $trainee = $this->trainee($group);

        $this->makeAssignment($group, $course, ['title' => 'Опубликовано']);
        // Черновик обучаемому не показывается: он не должен видеть
        // задание раньше преподавателя.
        $this->makeAssignment($group, $course, ['title' => 'Черновик', 'published_at' => null]);
        $this->makeAssignment($other, $course, ['title' => 'Чужая группа']);

        $response = $this->actingAs($trainee, 'sanctum')->getJson('/api/assignments')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Опубликовано', $response->json('data.0.title'));
    }

    public function test_trainee_submits_work(): void
    {
        $group = Group::factory()->create();
        $course = $this->courseFor($group);
        $trainee = $this->trainee($group);
        $assignment = $this->makeAssignment($group, $course);

        $this->actingAs($trainee, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions", ['comment' => 'Готово'])
            ->assertCreated()
            ->assertJsonPath('data.comment', 'Готово');

        $this->assertDatabaseHas('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'user_id' => $trainee->id,
            'status' => 'submitted',
        ]);
    }

    public function test_empty_submission_is_rejected(): void
    {
        $group = Group::factory()->create();
        $course = $this->courseFor($group);
        $trainee = $this->trainee($group);
        $assignment = $this->makeAssignment($group, $course);

        // Пустая сдача без файла и комментария — это не сдача.
        $this->actingAs($trainee, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions", [])
            ->assertStatus(422);
    }

    public function test_resubmission_updates_row_and_clears_grade(): void
    {
        $group = Group::factory()->create();
        $course = $this->courseFor($group);
        $teacher = $this->teacher();
        $trainee = $this->trainee($group);
        $assignment = $this->makeAssignment($group, $course);

        $this->actingAs($trainee, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions", ['comment' => 'Первая версия']);

        $submission = AssignmentSubmission::where('assignment_id', $assignment->id)->first();

        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions/{$submission->id}", [
                'score' => 3,
                'feedback' => 'Есть недочёты',
            ])
            ->assertOk();

        $this->assertSame(3, $submission->fresh()->score);

        // Пересдача: строка та же, оценка снята.
        $this->actingAs($trainee, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions", ['comment' => 'Исправлено'])
            ->assertCreated();

        $this->assertSame(1, AssignmentSubmission::count(), 'пересдача не плодит дубликаты');

        $fresh = $submission->fresh();
        $this->assertSame('Исправлено', $fresh->comment);
        $this->assertNull($fresh->score, 'оценка за прошлую версию работы не должна висеть');
        $this->assertFalse($fresh->isReviewed());
    }

    public function test_score_above_maximum_is_rejected(): void
    {
        $group = Group::factory()->create();
        $course = $this->courseFor($group);
        $teacher = $this->teacher();
        $trainee = $this->trainee($group);
        $assignment = $this->makeAssignment($group, $course, ['max_score' => 5]);

        $this->actingAs($trainee, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions", ['comment' => 'Работа']);
        $submission = AssignmentSubmission::first();

        // «12 из 5» повесило бы средний балл и порог зачёта.
        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions/{$submission->id}", ['score' => 12])
            ->assertStatus(422);

        $this->assertNull($submission->fresh()->score);
    }

    public function test_trainee_cannot_review(): void
    {
        $group = Group::factory()->create();
        $course = $this->courseFor($group);
        $trainee = $this->trainee($group);
        $assignment = $this->makeAssignment($group, $course);

        $this->actingAs($trainee, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions", ['comment' => 'Работа']);
        $submission = AssignmentSubmission::first();

        $this->actingAs($trainee, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions/{$submission->id}", ['score' => 5])
            ->assertForbidden();
    }

    public function test_student_cannot_see_other_groups_assignment(): void
    {
        $group = Group::factory()->create();
        $other = Group::factory()->create();
        $course = $this->courseFor($group);
        $trainee = $this->trainee($group);
        $assignment = $this->makeAssignment($other, $course);

        $this->actingAs($trainee, 'sanctum')
            ->getJson("/api/assignments/{$assignment->id}")
            ->assertForbidden();
    }

    public function test_max_score_cannot_drop_below_existing_grades(): void
    {
        $group = Group::factory()->create();
        $course = $this->courseFor($group);
        $teacher = $this->teacher();
        $trainee = $this->trainee($group);
        $assignment = $this->makeAssignment($group, $course, ['max_score' => 10]);

        $this->actingAs($trainee, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions", ['comment' => 'Работа']);
        $submission = AssignmentSubmission::first();

        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions/{$submission->id}", ['score' => 8]);

        // Уменьшение максимума ниже выставленной оценки ломает журнал.
        $this->actingAs($teacher, 'sanctum')
            ->patchJson("/api/assignments/{$assignment->id}", ['max_score' => 5])
            ->assertStatus(422);
    }

    /**
     * Область преподавателя задаётся правом, а не «его группой».
     *
     * Отдельного назначения преподавателя на группу в системе нет.
     * Если бы оно появилось, правило стоило бы вернуть — но сейчас
     * «преподаватель ведёт только свою группу» означало бы, что
     * инструктор не может выдать задание половине групп, и списки
     * заданий на странице и в отчётах считались бы по-разному.
     */
    public function test_teacher_with_grading_permission_can_issue_for_any_group(): void
    {
        $teacher = $this->teacher();
        $course = Course::factory()->create();
        $other = Group::factory()->create(['groupname' => 'Другая группа']);

        $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/assignments', [
                'title' => 'Задание для другой группы',
                'course_id' => $course->id,
                'group_id' => $other->id,
                'published_at' => now()->toDateTimeString(),
            ])
            ->assertCreated();
    }

    public function test_teacher_sees_submissions_of_students(): void
    {
        $group = Group::factory()->create();
        $course = $this->courseFor($group);
        $teacher = $this->teacher();
        $trainee = $this->trainee($group);
        $assignment = $this->makeAssignment($group, $course);

        $this->actingAs($trainee, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions", ['comment' => 'Работа']);

        $response = $this->actingAs($teacher, 'sanctum')
            ->getJson("/api/assignments/{$assignment->id}")
            ->assertOk();

        $response->assertJsonPath('meta.can_review', true);
        $this->assertCount(1, $response->json('data.submissions'));
        $this->assertSame('Петров Иван Иванович', $response->json('data.submissions.0.user'));
    }

    public function test_draft_assignment_is_hidden_from_students(): void
    {
        $group = Group::factory()->create();
        $course = $this->courseFor($group);
        $trainee = $this->trainee($group);
        $assignment = $this->makeAssignment($group, $course, ['published_at' => null]);

        $this->actingAs($trainee, 'sanctum')
            ->getJson("/api/assignments/{$assignment->id}")
            ->assertForbidden();
    }

    public function test_overdue_assignment_cannot_accept_submission(): void
    {
        $group = Group::factory()->create();
        $course = $this->courseFor($group);
        $trainee = $this->trainee($group);
        $assignment = $this->makeAssignment($group, $course, ['due_at' => now()->subDay()]);

        $this->assertTrue($assignment->isOverdue());

        $this->actingAs($trainee, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions", ['comment' => 'Опоздал'])
            ->assertStatus(403);
    }
}