<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\GradeBoundary;
use App\Models\GradeOverride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Грейдбук преподавателя.
 *
 * Проверяются три вещи, на которых держится смысл журнала:
 *  - оценка считается из порогов, а не хранится готовой цифрой
 *    (иначе смена границ делает весь журнал неверным);
 *  - ручная оценка перекрывает автоматическую, и её можно снять;
 *  - обучаемый и посторонний в журнал не попадают.
 */
class GradebookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Преподаватель — инструктор с ролью, которой калибровщик ролей
     * уже выдал grading.manage. Права висят на связи role_user, а не
     * на строке users.role, поэтому роль надо назначить явно.
     */
    private function teacher(): User
    {
        $teacher = User::factory()->create(['fio' => 'Преподаватель Преподаватель', 'role' => 'Инструктор']);

        $role = \App\Models\Role::where('slug', 'instructor')->first();

        if ($role !== null) {
            \App\Models\RoleUser::create(['user_id' => $teacher->id, 'role_id' => $role->id]);
        }

        return $teacher;
    }

    private function group()
    {
        return \App\Models\Group::factory()->create(['groupname' => 'Группа 1']);
    }

    public function test_boundaries_are_seeded_for_conversion(): void
    {
        // Границы живут в grade_boundaries и правятся преподавателем.
        // Без них журнал отдавал бы оценку null в каждой ячейке.
        GradeBoundary::create(['boundary' => 0, 'grade' => 2]);
        GradeBoundary::create(['boundary' => 85, 'grade' => 5]);

        $this->assertEquals(
            ['0' => 2, '85' => 5],
            (new \App\Http\Controllers\GradebookController)->boundaries()
        );
    }

    public function test_trainee_cannot_read_gradebook(): void
    {
        $trainee = User::factory()->create(['role' => 'Обучаемый']);

        $this->actingAs($trainee, 'sanctum')->getJson('/api/gradebook')->assertForbidden();
        $this->actingAs($trainee, 'sanctum')
            ->getJson('/api/gradebook/export?group_id=1')
            ->assertForbidden();
    }

    public function test_empty_group_id_returns_group_choices_instead_of_empty_page(): void
    {
        $teacher = $this->teacher();
        $this->group();

        $response = $this->actingAs($teacher, 'sanctum')
            ->getJson('/api/gradebook')
            ->assertOk()
            ->assertJsonPath('meta.requires_group', true);

        $this->assertCount(1, $response->json('data.groups'));
        $this->assertSame([], $response->json('data.students'));
    }

    public function test_score_is_converted_to_grade_by_boundaries(): void
    {
        foreach ([[0, 2], [50, 3], [80, 4], [90, 5]] as [$boundary, $grade]) {
            GradeBoundary::create(['boundary' => $boundary, 'grade' => $grade]);
        }

        $teacher = $this->teacher();
        $group = $this->group();
        $student = User::factory()->create(['role' => 'Обучаемый', 'fio' => 'Иванов Иван', 'group_id' => $group->id]);

        $exam = Exam::create([
            'title' => 'Экзамен 1',
            'group_id' => $group->id,
            'passing_score' => 0.7,
        ]);

        // 0.9 → 90% → «5» при границе 90.
        ExamAttempt::create([
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'total_count' => 10,
            'correct_count' => 9,
            'score' => 0.9,
            'passed' => true,
        ]);

        $cell = $this->actingAs($teacher, 'sanctum')
            ->getJson("/api/gradebook?group_id={$group->id}")
            ->assertOk()
            ->json('data.cells.' . $exam->id . ':' . $student->id);

        $this->assertSame(5, $cell['grade']);
        $this->assertSame(5, $cell['auto_grade']);
        $this->assertFalse($cell['manual']);
        $this->assertSame(9, $cell['correct']);
        $this->assertSame(10, $cell['total']);
    }

    public function test_best_attempt_is_used_not_the_last_one(): void
    {
        foreach ([[0, 2], [90, 5]] as [$boundary, $grade]) {
            GradeBoundary::create(['boundary' => $boundary, 'grade' => $grade]);
        }

        $teacher = $this->teacher();
        $group = $this->group();
        $student = User::factory()->create(['role' => 'Обучаемый', 'group_id' => $group->id]);

        $exam = Exam::create(['title' => 'Экзамен', 'group_id' => $group->id, 'passing_score' => 0.7]);

        // Сначала удачная попытка, потом неудачная пересдача.
        ExamAttempt::create([
            'user_id' => $student->id, 'exam_id' => $exam->id,
            'total_count' => 10, 'correct_count' => 10, 'score' => 1.0, 'passed' => true,
        ]);
        ExamAttempt::create([
            'user_id' => $student->id, 'exam_id' => $exam->id,
            'total_count' => 10, 'correct_count' => 3, 'score' => 0.3, 'passed' => false,
        ]);

        $cell = $this->actingAs($teacher, 'sanctum')
            ->getJson("/api/gradebook?group_id={$group->id}")
            ->json('data.cells.' . $exam->id . ':' . $student->id);

        $this->assertSame(5, $cell['grade'], 'неудачная пересдача не должна стирать результат');
    }

    public function test_manual_grade_overrides_automatic_one_and_can_be_cleared(): void
    {
        foreach ([[0, 2], [90, 5]] as [$boundary, $grade]) {
            GradeBoundary::create(['boundary' => $boundary, 'grade' => $grade]);
        }

        $teacher = $this->teacher();
        $group = $this->group();
        $student = User::factory()->create(['role' => 'Обучаемый', 'group_id' => $group->id]);
        $exam = Exam::create(['title' => 'Устный экзамен', 'group_id' => $group->id, 'passing_score' => 0.7]);

        ExamAttempt::create([
            'user_id' => $student->id, 'exam_id' => $exam->id,
            'total_count' => 10, 'correct_count' => 4, 'score' => 0.4, 'passed' => false,
        ]);

        $key = $exam->id . ':' . $student->id;

        // Автоматически за низкий счёт — «2».
        $this->assertSame(
            2,
            $this->actingAs($teacher, 'sanctum')->getJson("/api/gradebook?group_id={$group->id}")->json("data.cells.{$key}.grade")
        );

        $this->actingAs($teacher, 'sanctum')->putJson('/api/gradebook/cell', [
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'grade' => 4,
            'comment' => 'Ответил устно, материал закрыт',
        ])->assertOk()->assertJsonPath('data.grade', 4);

        $cell = $this->actingAs($teacher, 'sanctum')
            ->getJson("/api/gradebook?group_id={$group->id}")
            ->json("data.cells.{$key}");

        $this->assertTrue($cell['manual']);
        $this->assertSame(4, $cell['grade']);
        // Автоматическая оценка сохраняется: видно, что перекрыл преподаватель.
        $this->assertSame(2, $cell['auto_grade']);
        $this->assertSame('Ответил устно, материал закрыт', $cell['comment']);

        // Повторная запись не плодит дубликаты.
        $this->actingAs($teacher, 'sanctum')->putJson('/api/gradebook/cell', [
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'grade' => 5,
        ])->assertOk();

        $this->assertSame(1, GradeOverride::where('user_id', $student->id)->count());
        $this->assertSame(5, GradeOverride::where('user_id', $student->id)->first()->grade);

        // Пустая оценка снимает перекрытие.
        $this->actingAs($teacher, 'sanctum')->putJson('/api/gradebook/cell', [
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'grade' => null,
        ])->assertOk()->assertJsonPath('data.cleared', true);

        $this->assertSame(0, GradeOverride::count());

        $cell = $this->actingAs($teacher, 'sanctum')
            ->getJson("/api/gradebook?group_id={$group->id}")
            ->json("data.cells.{$key}");

        $this->assertFalse($cell['manual']);
        $this->assertSame(2, $cell['grade']);
    }

    public function test_teacher_cannot_grade_student_of_another_group(): void
    {
        $teacher = $this->teacher();
        $group = $this->group();
        $other = $this->group();

        $outsider = User::factory()->create(['role' => 'Обучаемый', 'group_id' => $other->id]);
        $exam = Exam::create(['title' => 'Экзамен группы', 'group_id' => $group->id, 'passing_score' => 0.7]);

        $this->actingAs($teacher, 'sanctum')->putJson('/api/gradebook/cell', [
            'user_id' => $outsider->id,
            'exam_id' => $exam->id,
            'grade' => 5,
        ])->assertForbidden();

        $this->assertSame(0, GradeOverride::count());
    }

    public function test_grade_out_of_scale_is_rejected(): void
    {
        $teacher = $this->teacher();
        $group = $this->group();
        $student = User::factory()->create(['role' => 'Обучаемый', 'group_id' => $group->id]);
        $exam = Exam::create(['title' => 'Экзамен', 'group_id' => $group->id, 'passing_score' => 0.7]);

        // Шкала 2..5 по grade_boundaries: «6» и «1» невозможны.
        $this->actingAs($teacher, 'sanctum')->putJson('/api/gradebook/cell', [
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'grade' => 6,
        ])->assertStatus(422);

        $this->actingAs($teacher, 'sanctum')->putJson('/api/gradebook/cell', [
            'user_id' => $student->id,
            'exam_id' => $exam->id,
            'grade' => 1,
        ])->assertStatus(422);
    }

    public function test_export_returns_csv_with_bom_and_quotes(): void
    {
        $teacher = $this->teacher();
        $group = $this->group();
        $student = User::factory()->create(['role' => 'Обучаемый', 'fio' => 'Петров, Пётр', 'group_id' => $group->id]);

        // Название с запятой: без кавычек CSV разъезжался бы на столбцы.
        $exam = Exam::create(['title' => 'Экзамен, часть 1', 'group_id' => $group->id, 'passing_score' => 0.7]);

        ExamAttempt::create([
            'user_id' => $student->id, 'exam_id' => $exam->id,
            'total_count' => 10, 'correct_count' => 8, 'score' => 0.8, 'passed' => true,
        ]);

        $response = $this->actingAs($teacher, 'sanctum')
            ->get("/api/gradebook/export?group_id={$group->id}")
            ->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));

        // Ответ не потоковый: CSV собирается целиком, поэтому
        // streamedContent() здесь бросал бы исключение.
        $csv = $response->getContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'без BOM Excel не откроет кириллицу');
        $this->assertStringContainsString('"Петров, Пётр"', $csv);
        $this->assertStringContainsString('"Экзамен, часть 1"', $csv);
    }
}