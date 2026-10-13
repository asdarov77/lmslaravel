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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Домашние задания: сдача, проверка и приватность файлов.
 *
 * Отдельно проверяется приватность: работа лежит на отдельном диске и
 * отдаётся только через проверяемый эндпоинт. На public disk путь
 * вида /storage/assignments/1/12/имя открывался бы всем, кто его
 * угадает, — а это персональные данные обучаемого.
 *
 * Права и роли создаются прямо в тесте: RefreshDatabase выполняет
 * миграции, но не сидеры, поэтому каталога прав в тестовой базе нет.
 */
class AssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function teacher(): User
    {
        $user = User::factory()->create(['role' => 'Инструктор', 'fio' => 'Иванов Иван Иванович']);
        $role = Role::firstOrCreate(['slug' => 'instructor'], ['rolename' => 'Инструктор']);

        $permissionIds = collect(['grading.manage', 'groups.manage', 'courses.manage'])
            ->map(fn ($slug) => Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'group' => 'grading'])->id)
            ->all();

        $role->permissions()->sync($permissionIds);
        RoleUser::create(['user_id' => $user->id, 'role_id' => $role->id]);

        return $user;
    }

    private function trainee(Group $group, string $fio = 'Петров Иван Иванович'): User
    {
        $user = User::factory()->create(['role' => 'Обучаемый', 'fio' => $fio]);
        $user->group_id = $group->id;
        $user->save();

        return $user;
    }

    private function courseFor(Group $group): Course
    {
        $course = Course::factory()->create(['title' => 'Курс А']);

        $record = new Group2learning(['group_id' => $group->id, 'course_id' => $course->id]);
        $record->study_from = now()->subDay()->toDateString();
        $record->study_to = now()->addMonth()->toDateString();
        $record->save();

        return $course;
    }

    private function assignment(Group $group, Course $course, array $attrs = []): Assignment
    {
        return Assignment::create(array_merge([
            'title' => 'Расчёт по заданию',
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

    public function test_trainee_sees_only_published_tasks_of_own_group(): void
    {
        $group = Group::factory()->create(['groupname' => 'Моя']);
        $other = Group::factory()->create(['groupname' => 'Чужая']);
        $course = $this->courseFor($group);
        $trainee = $this->trainee($group);

        $this->assignment($group, $course, ['title' => 'Опубликовано']);
        // Черновик не должен попадать к обучаемому: он увидел бы
        // задание раньше преподавателя.
        $this->assignment($group, $course, ['title' => 'Черновик', 'published_at' => null]);
        $this->assignment($other, $course, ['title' => 'Чужая группа']);

        $response = $this->actingAs($trainee, 'sanctum')->getJson('/api/assignments')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Опубликовано', $response->json('data.0.title'));
    }

    public function test_draft_is_not_visible_to_student(): void
    {
        $group = Group::factory()->create();
        $course = $this->courseFor($group);
        $trainee = $this->trainee($group);
        $assignment = $this->assignment($group, $course, ['published_at' => null]);

        $this->actingAs($trainee, 'sanctum')
            ->getJson("/api/assignments/{$assignment->id}")
            ->assertForbidden();
    }

    public function test_teacher_sees_drafts_and_all_groups(): void
    {
        $teacher = $this->teacher();
        $group = Group::factory()->create();
        $course = $this->courseFor($group);

        $this->assignment($group, $course, ['title' => 'Черновик', 'published_at' => null]);

        // Преподаватель создаёт задания и публикует их сам, поэтому
        // черновик для него виден.
        $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/assignments')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Черновик');
    }

    public function test_trainee_submits_work_and_sees_own_grade(): void
    {
        $group = Group::factory()->create();
        $course = $this->courseFor($group);
        $teacher = $this->teacher();
        $trainee = $this->trainee($group);
        $assignment = $this->assignment($group, $course);

        $this->actingAs($trainee, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions", ['comment' => 'Готово'])
            ->assertCreated();

        $submission = AssignmentSubmission::firstOrFail();

        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions/{$submission->id}", [
                'score' => 4,
                'feedback' => 'Хорошо, но есть недочёт',
            ])
            ->assertOk();

        $data = $this->actingAs($trainee, 'sanctum')
            ->getJson("/api/assignments/{$assignment->id}")
            ->assertOk()
            ->json('data');

        $this->assertSame(4, $data['own_submission']['score']);
        $this->assertSame('Хорошо, но есть недочёт', $data['own_submission']['feedback']);
    }

    public function test_student_does_not_see_other_students_works(): void
    {
        $group = Group::factory()->create();
        $course = $this->courseFor($group);
        $assignment = $this->assignment($group, $course);

        $first = $this->trainee($group, 'Петров Иван Иванович');
        $second = $this->trainee($group, 'Сидоров Иван Иванович');

        $this->actingAs($first, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions", ['comment' => 'Моя работа']);

        // Список работ скрыт от обучаемого: чужое ФИО и файлы — это
        // персональные данные.
        $response = $this->actingAs($second, 'sanctum')
            ->getJson("/api/assignments/{$assignment->id}")
            ->assertOk();

        $this->assertCount(0, $response->json('data.submissions'));
    }

    public function test_empty_submission_is_rejected(): void
    {
        $group = Group::factory()->create();
        $course = $this->courseFor($group);
        $trainee = $this->trainee($group);
        $assignment = $this->assignment($group, $course);

        $this->actingAs($trainee, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions", [])
            ->assertStatus(422);

        $this->assertSame(0, AssignmentSubmission::count());
    }

    public function test_resubmission_keeps_one_row_and_clears_grade(): void
    {
        $group = Group::factory()->create();
        $course = $this->courseFor($group);
        $teacher = $this->teacher();
        $trainee = $this->trainee($group);
        $assignment = $this->assignment($group, $course);

        $this->actingAs($trainee, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions", ['comment' => 'Первая версия']);

        $submission = AssignmentSubmission::firstOrFail();

        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions/{$submission->id}", ['score' => 3]);

        $this->actingAs($trainee, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions", ['comment' => 'Исправлено'])
            ->assertCreated();

        $this->assertSame(1, AssignmentSubmission::count(), 'пересдача не плодит дубликаты');

        $fresh = $submission->fresh();
        $this->assertSame('Исправлено', $fresh->comment);
        $this->assertNull($fresh->score, 'оценка за прошлую версию работы не должна висеть');
        $this->assertFalse($fresh->isReviewed());
    }

    public function test_submission_after_deadline_is_rejected(): void
    {
        $group = Group::factory()->create();
        $course = $this->courseFor($group);
        $trainee = $this->trainee($group);
        $assignment = $this->assignment($group, $course, ['due_at' => now()->subDay()]);

        // Форма после срока скрыта, но запрос мимо интерфейса должен
        // отклоняться сервером: иначе в журнале появилась бы работа,
        // сданная вовремя.
        $this->actingAs($trainee, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions", ['comment' => 'Опоздал'])
            ->assertForbidden();

        $this->assertSame(0, AssignmentSubmission::count());
    }

    public function test_score_above_maximum_is_rejected(): void
    {
        $group = Group::factory()->create();
        $course = $this->courseFor($group);
        $teacher = $this->teacher();
        $trainee = $this->trainee($group);
        $assignment = $this->assignment($group, $course, ['max_score' => 5]);

        $this->actingAs($trainee, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions", ['comment' => 'Работа']);

        $submission = AssignmentSubmission::firstOrFail();

        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions/{$submission->id}", ['score' => 12])
            ->assertStatus(422);

        $this->assertNull($submission->fresh()->score);
    }

    public function test_max_score_cannot_drop_below_existing_grades(): void
    {
        $group = Group::factory()->create();
        $course = $this->courseFor($group);
        $teacher = $this->teacher();
        $trainee = $this->trainee($group);
        $assignment = $this->assignment($group, $course, ['max_score' => 10]);

        $this->actingAs($trainee, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions", ['comment' => 'Работа']);

        $submission = AssignmentSubmission::firstOrFail();
        $this->actingAs($teacher, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions/{$submission->id}", ['score' => 8]);

        // Уменьшение максимума ниже выставленной оценки ломает журнал.
        $this->actingAs($teacher, 'sanctum')
            ->patchJson("/api/assignments/{$assignment->id}", ['max_score' => 5])
            ->assertStatus(422);
    }

    public function test_trainee_cannot_review_or_delete(): void
    {
        $group = Group::factory()->create();
        $course = $this->courseFor($group);
        $trainee = $this->trainee($group);
        $assignment = $this->assignment($group, $course);

        $this->actingAs($trainee, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions", ['comment' => 'Работа']);

        $submission = AssignmentSubmission::firstOrFail();

        $this->actingAs($trainee, 'sanctum')
            ->postJson("/api/assignments/{$assignment->id}/submissions/{$submission->id}", ['score' => 5])
            ->assertForbidden();

        $this->actingAs($trainee, 'sanctum')
            ->deleteJson("/api/assignments/{$assignment->id}")
            ->assertForbidden();
    }

    public function test_student_cannot_see_other_groups_assignment(): void
    {
        $group = Group::factory()->create();
        $other = Group::factory()->create();
        $course = $this->courseFor($group);
        $trainee = $this->trainee($group);
        $assignment = $this->assignment($other, $course);

        $this->actingAs($trainee, 'sanctum')
            ->getJson("/api/assignments/{$assignment->id}")
            ->assertForbidden();
    }

    /**
     * Файл работы лежит на приватном диске и отдаётся только через
     * проверяемый эндпоинт.
     */
    public function test_uploaded_file_is_private_and_downloadable_by_owner(): void
    {
        Storage::fake('submissions');

        $group = Group::factory()->create();
        $course = $this->courseFor($group);
        $trainee = $this->trainee($group);
        $assignment = $this->assignment($group, $course);

        $this->actingAs($trainee, 'sanctum')
            ->post("/api/assignments/{$assignment->id}/submissions", [
                'comment' => 'Файл прилагаю',
                'file' => UploadedFile::fake()->create('Курсовая работа.docx', 40, 'application/msword'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $submission = AssignmentSubmission::firstOrFail();

        Storage::disk('submissions')->assertExists($submission->file_path);

        // Ссылка ведёт на наш эндпоинт, а не на публичный /storage.
        $data = $this->actingAs($trainee, 'sanctum')
            ->getJson("/api/assignments/{$assignment->id}")
            ->json('data.own_submission');

        $this->assertStringNotContainsString('/storage/', (string) $data['file_url']);
        $this->assertStringContainsString('/download', (string) $data['file_url']);

        $this->actingAs($trainee, 'sanctum')
            ->get("/api/assignments/{$assignment->id}/submissions/{$submission->id}/download")
            ->assertOk();
    }

    public function test_other_student_cannot_download_work(): void
    {
        Storage::fake('submissions');

        $group = Group::factory()->create();
        $course = $this->courseFor($group);
        $owner = $this->trainee($group, 'Петров Иван Иванович');
        $stranger = $this->trainee($group, 'Сидоров Иван Иванович');
        $assignment = $this->assignment($group, $course);

        $this->actingAs($owner, 'sanctum')
            ->post("/api/assignments/{$assignment->id}/submissions", [
                'file' => UploadedFile::fake()->create('secret.docx', 10),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $submission = AssignmentSubmission::firstOrFail();

        $this->actingAs($stranger, 'sanctum')
            ->get("/api/assignments/{$assignment->id}/submissions/{$submission->id}/download")
            ->assertForbidden();
    }

    /**
     * Путь в имени файла не должен выйти за пределы каталога.
     *
     * Имя берётся из базы (а значит, из клиента), и в заголовке ответа
     * перевод строки или «../..» сломали бы ответ либо указали бы на
     * файл вне каталога работ.
     */
    public function test_file_name_cannot_break_response_headers(): void
    {
        Storage::fake('submissions');

        $group = Group::factory()->create();
        $course = $this->courseFor($group);
        $trainee = $this->trainee($group);
        $assignment = $this->assignment($group, $course);

        $this->actingAs($trainee, 'sanctum')
            ->post("/api/assignments/{$assignment->id}/submissions", [
                'file' => UploadedFile::fake()->create('work.docx', 10),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $submission = AssignmentSubmission::firstOrFail();
        $submission->forceFill(['file_name' => '../../secret.txt'])->save();

        $response = $this->actingAs($trainee, 'sanctum')
            ->get("/api/assignments/{$assignment->id}/submissions/{$submission->id}/download")
            ->assertOk();

        $disposition = (string) $response->headers->get('content-disposition');

        $this->assertStringContainsString('secret.txt', $disposition);
        $this->assertStringNotContainsString('..', $disposition);
    }

    public function test_teacher_creates_publishes_and_deletes(): void
    {
        Storage::fake('submissions');

        $teacher = $this->teacher();
        $group = Group::factory()->create(['groupname' => 'Группа преподавателя']);
        $course = $this->courseFor($group);

        $id = $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/assignments', [
                'title' => 'Новое задание',
                'description' => 'Описание',
                'course_id' => $course->id,
                'group_id' => $group->id,
                'max_score' => 10,
                'published_at' => now()->toDateTimeString(),
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($teacher, 'sanctum')
            ->deleteJson("/api/assignments/{$id}")
            ->assertOk();

        $this->assertNull(Assignment::find($id));
    }
}